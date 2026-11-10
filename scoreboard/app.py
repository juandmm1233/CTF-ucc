"""
Scoreboard simple para el CTF UCC (15 vs 15) - modelo Attack & Defend.

- Dos equipos: IDS (opera Ibague Data Services) y UWS (opera UCC Web Services).
  Cada equipo defiende SU VM y ataca la del rival.
- El instructor inicia, detiene y reinicia la partida. Al iniciar (desde cero)
  o reiniciar se registran flags aleatorias. Parar conserva ese juego.
- Las VMs de los labs consultan el estado y, con un token, descargan solo
  las flags de su lab.
- Banderas honeypot con penalizacion (no informa al jugador que existen).
- API minima: GET / (tablero), POST /submit (enviar bandera),
  GET /api/exercise, GET /api/exercise/flags.
- Almacenamiento: SQLite en /data/scoreboard.db (montar volumen para persistir).

Pensado para clase: cada estudiante elige un equipo y un alias al primer envio,
luego desde la misma sesion sigue sumando.

NO es codigo de produccion: pensado para el aula, sin auth fuerte ni rate limiting.
"""

import hmac
import os
import secrets
import sqlite3
import string
from datetime import datetime, timedelta
from flask import Flask, g, render_template, request, redirect, url_for, jsonify, abort

DB_PATH = os.environ.get("SCOREBOARD_DB", "/data/scoreboard.db")

# Password del panel de instructor. Se carga SOLO desde env var para no dejarla
# en el codigo ni en git. Si no esta definida, el panel queda inoperable.
RESET_PASSWORD = os.environ.get("SCOREBOARD_RESET_PASSWORD", "")

# Secreto compartido con las VMs de los labs. Sin el, no hay descarga de flags.
LAB_SYNC_TOKEN = os.environ.get("LAB_SYNC_TOKEN", "")

# Ban tras 2 intentos fallidos, durante 3 horas.
RESET_MAX_FAILED_ATTEMPTS = 2
RESET_BAN_SECONDS = 3 * 60 * 60

_FLAG_ALPHABET = string.ascii_lowercase + string.digits

# Catalogo fijo (puntos, tipo, lab, vector). El valor FLAG{...} se genera
# al iniciar o reiniciar y se guarda en la tabla flags.
FLAG_CATALOG = [
    {"slot": "sqli", "points": 50, "type": "real", "lab": "IDS", "vector": "SQL Injection"},
    {"slot": "cookie", "points": 75, "type": "real", "lab": "IDS", "vector": "Cookie tampering -> /admin.php"},
    {"slot": "lfi", "points": 60, "type": "real", "lab": "IDS", "vector": "LFI / Path traversal"},
    {"slot": "config", "points": 70, "type": "real", "lab": "IDS", "vector": "LFI -> config/app.ini"},
    {"slot": "cmd", "points": 80, "type": "real", "lab": "IDS", "vector": "Command Injection en /network.php"},
    {"slot": "upload", "points": 120, "type": "real", "lab": "IDS", "vector": "Webshell via /upload.php"},
    {"slot": "hash", "points": 100, "type": "real", "lab": "IDS", "vector": "Hash cracking (MD5)"},
    {"slot": "ssh", "points": 150, "type": "real", "lab": "IDS", "vector": "SSH pivot -> /home/admin/flag.txt"},

    {"slot": "sqli", "points": 50, "type": "real", "lab": "UWS", "vector": "SQL Injection"},
    {"slot": "cookie", "points": 75, "type": "real", "lab": "UWS", "vector": "Cookie tampering -> /admin.php"},
    {"slot": "lfi", "points": 60, "type": "real", "lab": "UWS", "vector": "LFI / Path traversal"},
    {"slot": "config", "points": 70, "type": "real", "lab": "UWS", "vector": "LFI -> config/app.ini"},
    {"slot": "cmd", "points": 80, "type": "real", "lab": "UWS", "vector": "Command Injection en /network.php"},
    {"slot": "upload", "points": 120, "type": "real", "lab": "UWS", "vector": "Webshell via /upload.php"},
    {"slot": "hash", "points": 100, "type": "real", "lab": "UWS", "vector": "Hash cracking (MD5)"},
    {"slot": "ssh", "points": 150, "type": "real", "lab": "UWS", "vector": "SSH pivot -> /home/admin/flag.txt"},

    {"slot": "honeypot_secrets", "points": -50, "type": "honeypot", "lab": "IDS", "vector": "Honeypot (secrets table)"},
    {"slot": "honeypot_secrets", "points": -50, "type": "honeypot", "lab": "UWS", "vector": "Honeypot (secrets table)"},
    {"slot": "honeypot_cookie", "points": -50, "type": "honeypot", "lab": "*", "vector": "Honeypot (cookie de session)"},
]

TEAMS = ("ids", "uws")
TEAM_LABELS = {
    "ids": "Equipo IDS (Ibague Data Services)",
    "uws": "Equipo UWS (UCC Web Services)",
}

app = Flask(__name__)


def get_db():
    db = getattr(g, "_db", None)
    if db is None:
        os.makedirs(os.path.dirname(DB_PATH), exist_ok=True)
        db = g._db = sqlite3.connect(DB_PATH)
        db.row_factory = sqlite3.Row
        _init_db(db)
    return db


def _init_db(db):
    db.executescript(
        """
        CREATE TABLE IF NOT EXISTS submissions (
            id          INTEGER PRIMARY KEY AUTOINCREMENT,
            team        TEXT NOT NULL,
            alias       TEXT NOT NULL,
            flag        TEXT NOT NULL,
            points      INTEGER NOT NULL,
            type        TEXT NOT NULL,
            lab         TEXT NOT NULL,
            vector      TEXT NOT NULL,
            ts          TEXT NOT NULL,
            ip          TEXT NOT NULL
        );
        CREATE INDEX IF NOT EXISTS idx_team_flag ON submissions(team, flag);

        CREATE TABLE IF NOT EXISTS reset_attempts (
            ip             TEXT PRIMARY KEY,
            failed_count   INTEGER NOT NULL DEFAULT 0,
            banned         INTEGER NOT NULL DEFAULT 0,
            last_attempt   TEXT    NOT NULL
        );

        CREATE TABLE IF NOT EXISTS exercise (
            id          INTEGER PRIMARY KEY CHECK (id = 1),
            status      TEXT NOT NULL,
            generation  INTEGER NOT NULL,
            last_event  TEXT NOT NULL,
            updated_at  TEXT NOT NULL
        );

        CREATE TABLE IF NOT EXISTS flags (
            generation  INTEGER NOT NULL,
            lab         TEXT NOT NULL,
            slot        TEXT NOT NULL,
            flag        TEXT NOT NULL,
            points      INTEGER NOT NULL,
            type        TEXT NOT NULL,
            vector      TEXT NOT NULL,
            PRIMARY KEY (generation, lab, slot)
        );
        """
    )
    now = datetime.utcnow().isoformat(timespec="seconds")
    db.execute(
        "INSERT OR IGNORE INTO exercise (id, status, generation, last_event, updated_at)"
        " VALUES (1, 'pending', 0, 'pending', ?)",
        (now,),
    )
    db.commit()


@app.teardown_appcontext
def _close_db(exc):
    db = getattr(g, "_db", None)
    if db is not None:
        db.close()


def _client_ip():
    return request.headers.get("X-Forwarded-For", request.remote_addr or "?").split(",")[0].strip()


def _now():
    return datetime.utcnow().isoformat(timespec="seconds")


def _get_exercise(db):
    row = db.execute("SELECT * FROM exercise WHERE id=1").fetchone()
    if row is None:
        db.execute(
            "INSERT INTO exercise (id, status, generation, last_event, updated_at)"
            " VALUES (1, 'pending', 0, 'pending', ?)",
            (_now(),),
        )
        db.commit()
        row = db.execute("SELECT * FROM exercise WHERE id=1").fetchone()
    return row


def _public_exercise(row):
    return {
        "status": row["status"],
        "generation": row["generation"],
        "last_event": row["last_event"],
        "updated_at": row["updated_at"],
    }


def _new_flag(used):
    while True:
        body = "_".join(
            "".join(secrets.choice(_FLAG_ALPHABET) for _ in range(n))
            for n in (3, 6, 3)
        )
        flag = "FLAG{" + body + "}"
        if flag not in used:
            used.add(flag)
            return flag


def _register_flags(db, generation):
    used = set()
    for spec in FLAG_CATALOG:
        flag = _new_flag(used)
        db.execute(
            "INSERT INTO flags (generation, lab, slot, flag, points, type, vector)"
            " VALUES (?,?,?,?,?,?,?)",
            (
                generation,
                spec["lab"],
                spec["slot"],
                flag,
                spec["points"],
                spec["type"],
                spec["vector"],
            ),
        )


def _set_exercise(db, status, generation, last_event):
    db.execute(
        "UPDATE exercise SET status=?, generation=?, last_event=?, updated_at=? WHERE id=1",
        (status, generation, last_event, _now()),
    )


def _flags_by_value(db, generation):
    if not generation:
        return {}
    rows = db.execute(
        "SELECT * FROM flags WHERE generation=?", (generation,)
    ).fetchall()
    return {r["flag"]: r for r in rows}


def _team_scores():
    db = get_db()
    rows = db.execute(
        "SELECT team, COALESCE(SUM(points), 0) AS pts, COUNT(*) AS n FROM submissions GROUP BY team"
    ).fetchall()
    base = {t: {"team": t, "label": TEAM_LABELS[t], "pts": 0, "n": 0} for t in TEAMS}
    for r in rows:
        if r["team"] in base:
            base[r["team"]] = {"team": r["team"], "label": TEAM_LABELS[r["team"]], "pts": r["pts"], "n": r["n"]}
    return [base[t] for t in TEAMS]


def _player_scores():
    db = get_db()
    rows = db.execute(
        """
        SELECT alias, team,
               COALESCE(SUM(points), 0) AS pts,
               COUNT(*)                  AS n,
               MAX(ts)                   AS last_ts
        FROM submissions
        GROUP BY alias, team
        ORDER BY pts DESC, last_ts ASC
        """
    ).fetchall()
    return [dict(r) for r in rows]


def _recent(limit=15):
    db = get_db()
    rows = db.execute(
        "SELECT * FROM submissions ORDER BY id DESC LIMIT ?", (limit,)
    ).fetchall()
    return [dict(r) for r in rows]


def _challenge_progress():
    """Devuelve cuantas veces fue resuelta cada bandera real, por equipo."""
    db = get_db()
    ex = _get_exercise(db)
    current = {}
    if ex["generation"]:
        rows = db.execute(
            "SELECT lab, slot, flag FROM flags WHERE generation=?",
            (ex["generation"],),
        ).fetchall()
        current = {(r["lab"], r["slot"]): r["flag"] for r in rows}

    out = []
    for spec in FLAG_CATALOG:
        if spec["type"] != "real":
            continue
        flag = current.get((spec["lab"], spec["slot"]))
        solves = {}
        if flag:
            rows = db.execute(
                "SELECT team, COUNT(DISTINCT alias) AS solves FROM submissions WHERE flag=? GROUP BY team",
                (flag,),
            ).fetchall()
            solves = {r["team"]: r["solves"] for r in rows}
        out.append(
            {
                "lab": spec["lab"],
                "vector": spec["vector"],
                "points": spec["points"],
                "ids": solves.get("ids", 0),
                "uws": solves.get("uws", 0),
            }
        )
    out.sort(key=lambda x: (x["lab"], -x["points"]))
    return out


def _instructor_denied(pwd):
    """
    None si la contrasena del instructor es valida.
    Si no, un redirect al tablero (intento fallido o ban).
    """
    ip = _client_ip()
    db = get_db()
    now = datetime.utcnow()

    row = db.execute("SELECT * FROM reset_attempts WHERE ip=?", (ip,)).fetchone()

    if row and row["banned"]:
        try:
            last_attempt = datetime.fromisoformat(row["last_attempt"])
        except ValueError:
            last_attempt = now
        elapsed = now - last_attempt
        if elapsed < timedelta(seconds=RESET_BAN_SECONDS):
            remaining = timedelta(seconds=RESET_BAN_SECONDS) - elapsed
            mins = int(remaining.total_seconds() // 60) + 1
            return redirect(url_for(
                "index",
                flash=f"IP {ip} baneada del panel de instructor. Reintenta en ~{mins} min.",
                cls="danger",
            ))
        db.execute("DELETE FROM reset_attempts WHERE ip=?", (ip,))
        db.commit()
        row = None

    password_ok = bool(RESET_PASSWORD) and hmac.compare_digest(pwd, RESET_PASSWORD)
    if password_ok:
        return None

    new_count = (row["failed_count"] if row else 0) + 1
    banned = 1 if new_count >= RESET_MAX_FAILED_ATTEMPTS else 0
    db.execute(
        """
        INSERT INTO reset_attempts(ip, failed_count, banned, last_attempt)
        VALUES (?, ?, ?, ?)
        ON CONFLICT(ip) DO UPDATE SET
            failed_count=excluded.failed_count,
            banned=excluded.banned,
            last_attempt=excluded.last_attempt
        """,
        (ip, new_count, banned, now.isoformat(timespec="seconds")),
    )
    db.commit()
    if banned:
        msg = f"Password incorrecto. IP {ip} baneada por 3 horas."
        cls = "danger"
    else:
        remaining_attempts = RESET_MAX_FAILED_ATTEMPTS - new_count
        msg = (
            f"Password incorrecto (IP {ip}). "
            f"Te queda {remaining_attempts} intento antes del ban."
        )
        cls = "warn"
    return redirect(url_for("index", flash=msg, cls=cls))


@app.get("/")
def index():
    db = get_db()
    exercise = _public_exercise(_get_exercise(db))
    return render_template(
        "index.html",
        teams=_team_scores(),
        players=_player_scores(),
        recent=_recent(),
        challenges=_challenge_progress(),
        exercise=exercise,
        last_alias=request.cookies.get("alias", ""),
        last_team=request.cookies.get("team", ""),
        flash=request.args.get("flash", ""),
        flash_class=request.args.get("cls", ""),
    )


@app.post("/submit")
def submit():
    alias = (request.form.get("alias") or "").strip()[:32]
    team = (request.form.get("team") or "").strip().lower()
    flag = (request.form.get("flag") or "").strip()

    db = get_db()
    exercise = _get_exercise(db)
    if exercise["status"] != "running":
        if exercise["status"] == "pending":
            msg = "El ejercicio aún no inicia. El tablero no acepta banderas."
        else:
            msg = "El ejercicio está detenido. El tablero no acepta banderas."
        return redirect(url_for("index", flash=msg, cls="warn"))

    if not alias or team not in TEAMS or not flag:
        return redirect(url_for("index", flash="Datos incompletos. Indica alias, equipo y bandera.", cls="warn"))

    meta = _flags_by_value(db, exercise["generation"]).get(flag)
    if meta is None:
        resp = redirect(url_for("index", flash="Bandera desconocida o invalida.", cls="warn"))
        resp.set_cookie("alias", alias, max_age=86400)
        resp.set_cookie("team", team, max_age=86400)
        return resp

    if meta["type"] == "real":
        already = db.execute(
            "SELECT 1 FROM submissions WHERE team=? AND flag=? LIMIT 1", (team, flag)
        ).fetchone()
        if already:
            resp = redirect(url_for("index", flash="Bandera ya entregada por tu equipo. No suma puntos extra.", cls="info"))
            resp.set_cookie("alias", alias, max_age=86400)
            resp.set_cookie("team", team, max_age=86400)
            return resp

    db.execute(
        "INSERT INTO submissions (team, alias, flag, points, type, lab, vector, ts, ip)"
        " VALUES (?,?,?,?,?,?,?,?,?)",
        (
            team,
            alias,
            flag,
            int(meta["points"]),
            meta["type"],
            meta["lab"],
            meta["vector"],
            datetime.utcnow().isoformat(timespec="seconds"),
            _client_ip(),
        ),
    )
    db.commit()

    if meta["type"] == "honeypot":
        flash = f"Trampa! Esa bandera resta {abs(meta['points'])} puntos al {TEAM_LABELS[team]}."
        cls = "danger"
    else:
        flash = f"Bandera valida (+{meta['points']} pts para {TEAM_LABELS[team]} - {meta['vector']})."
        cls = "success"

    resp = redirect(url_for("index", flash=flash, cls=cls))
    resp.set_cookie("alias", alias, max_age=86400)
    resp.set_cookie("team", team, max_age=86400)
    return resp


@app.post("/exercise/control")
def exercise_control():
    """Iniciar, parar o reiniciar. La contrasena es SCOREBOARD_RESET_PASSWORD."""
    pwd = request.form.get("password") or ""
    action = (request.form.get("action") or "").strip().lower()
    denied = _instructor_denied(pwd)
    if denied is not None:
        return denied

    if action not in ("start", "stop", "restart"):
        return redirect(url_for("index", flash="Accion de ejercicio desconocida.", cls="warn"))

    db = get_db()
    ex = _get_exercise(db)

    if action == "start":
        if ex["status"] == "running":
            msg, cls = "El ejercicio ya está en curso.", "info"
        elif ex["status"] == "stopped" and ex["generation"] > 0:
            _set_exercise(db, "running", ex["generation"], "started")
            db.commit()
            msg, cls = "Ejercicio reanudado. Las banderas de esta generación se mantienen.", "success"
        else:
            generation = int(ex["generation"]) + 1
            _register_flags(db, generation)
            _set_exercise(db, "running", generation, "started")
            db.commit()
            msg, cls = "Ejercicio iniciado. Las banderas quedaron registradas.", "success"
        return redirect(url_for("index", flash=msg, cls=cls))

    if action == "stop":
        if ex["status"] != "running":
            return redirect(url_for(
                "index",
                flash="No hay un ejercicio en curso para detener.",
                cls="warn",
            ))
        _set_exercise(db, "stopped", ex["generation"], "stopped")
        db.commit()
        return redirect(url_for(
            "index",
            flash="Ejercicio detenido. Las banderas se conservan, pero el tablero no acepta envíos.",
            cls="success",
        ))

    generation = int(ex["generation"]) + 1
    db.execute("DELETE FROM submissions")
    _register_flags(db, generation)
    _set_exercise(db, "running", generation, "restarted")
    db.commit()
    return redirect(url_for(
        "index",
        flash="Ejercicio reiniciado. Marcador limpio y banderas nuevas.",
        cls="success",
    ))


@app.post("/exercise/flags")
def exercise_flags_view():
    """Listado de la generación actual. Solo via POST con la contraseña; no va en el HTML público."""
    pwd = request.form.get("password") or ""
    denied = _instructor_denied(pwd)
    if denied is not None:
        return denied

    db = get_db()
    ex = _get_exercise(db)
    rows = []
    if ex["generation"]:
        rows = db.execute(
            "SELECT lab, slot, flag, points, type, vector FROM flags WHERE generation=? "
            "ORDER BY lab, type, points DESC",
            (ex["generation"],),
        ).fetchall()
    resp = app.make_response(render_template(
        "instructor_flags.html",
        exercise=_public_exercise(ex),
        flags=[dict(r) for r in rows],
    ))
    resp.headers["Cache-Control"] = "no-store"
    return resp


@app.get("/api/exercise")
def api_exercise():
    db = get_db()
    return jsonify(_public_exercise(_get_exercise(db)))


@app.get("/api/exercise/flags")
def api_exercise_flags():
    if not LAB_SYNC_TOKEN:
        abort(401)
    got = request.headers.get("X-Lab-Token", "")
    if not got or not hmac.compare_digest(got, LAB_SYNC_TOKEN):
        abort(401)

    lab_id = (request.headers.get("X-Lab-Id") or "").strip().lower()
    if lab_id not in ("ids", "uws"):
        abort(400)
    want = "IDS" if lab_id == "ids" else "UWS"

    db = get_db()
    ex = _get_exercise(db)
    payload = _public_exercise(ex)
    flags = {}
    if ex["generation"]:
        rows = db.execute(
            "SELECT slot, flag FROM flags WHERE generation=? AND (lab=? OR lab='*')",
            (ex["generation"], want),
        ).fetchall()
        flags = {r["slot"]: r["flag"] for r in rows}
    payload["flags"] = flags
    return jsonify(payload)


@app.get("/api/scores")
def api_scores():
    db = get_db()
    return jsonify({
        "teams": _team_scores(),
        "players": _player_scores(),
        "recent": _recent(30),
        "exercise": _public_exercise(_get_exercise(db)),
    })


@app.get("/healthz")
def healthz():
    return "ok"


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=False)
