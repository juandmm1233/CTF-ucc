"""
Scoreboard simple para el CTF UCC (15 vs 15) - modelo Attack & Defend.

- Dos equipos: IDS (opera Ibague Data Services) y UWS (opera UCC Web Services).
  Cada equipo defiende SU VM y ataca la del rival.
- Banderas con puntaje variable.
- Banderas honeypot con penalizacion (no informa al jugador que existen).
- API minima: GET / (tablero), POST /submit (enviar bandera).
- Almacenamiento: SQLite en /data/scoreboard.db (montar volumen para persistir).

Pensado para clase: cada estudiante elige un equipo y un alias al primer envio,
luego desde la misma sesion sigue sumando.

NO es codigo de produccion: pensado para el aula, sin auth fuerte ni rate limiting.
"""

import hmac
import os
import sqlite3
import time
from datetime import datetime, timedelta
from flask import Flask, g, render_template, request, redirect, url_for, jsonify, abort

DB_PATH = os.environ.get("SCOREBOARD_DB", "/data/scoreboard.db")

# Password del panel de reinicio. Se carga SOLO desde env var para no dejarla
# en el codigo ni en git. Si no esta definida, el reset queda inoperable
# (cualquier intento fallara). Configurala via scoreboard/secrets.env.
RESET_PASSWORD = os.environ.get("SCOREBOARD_RESET_PASSWORD", "")

# Ban tras 2 intentos fallidos, durante 3 horas.
RESET_MAX_FAILED_ATTEMPTS = 2
RESET_BAN_SECONDS = 3 * 60 * 60

# ----------------------------------------------------------------------
# Catalogo de banderas. Si quieres agregar/quitar retos, edita aqui.
# Tipo: "real" suma puntos, "honeypot" resta puntos.
# Una bandera puede ser unica por equipo o compartida (cualquier equipo
# la puede enviar pero solo cuenta la primera vez por equipo).
# ----------------------------------------------------------------------
FLAGS = [
    # ===== Lab IDS (Ibague Data Services - puerto 8080) =====
    {"flag": "FLAG{UCC_IDS_SQLi_Bypass}",  "points":  50, "type": "real",     "lab": "IDS", "vector": "SQL Injection"},
    {"flag": "FLAG{UCC_IDS_Cookie_Bypass}", "points": 75, "type": "real",     "lab": "IDS", "vector": "Cookie tampering -> /admin.php"},
    {"flag": "FLAG{UCC_IDS_LFI_Found}",     "points": 60, "type": "real",     "lab": "IDS", "vector": "LFI / Path traversal"},
    {"flag": "FLAG{UCC_IDS_Config_Leaked}", "points": 70, "type": "real",     "lab": "IDS", "vector": "LFI -> config/app.ini"},
    {"flag": "FLAG{UCC_IDS_Cmd_Inject}",    "points": 80, "type": "real",     "lab": "IDS", "vector": "Command Injection en /network.php"},
    {"flag": "FLAG{UCC_IDS_Upload_RCE}",    "points": 120,"type": "real",     "lab": "IDS", "vector": "Webshell via /upload.php"},
    {"flag": "FLAG{UCC_IDS_Hash_Cracked}",  "points": 100,"type": "real",     "lab": "IDS", "vector": "Hash cracking (MD5)"},
    {"flag": "FLAG{UCC_Ciber_Atrapada}",    "points": 150,"type": "real",     "lab": "IDS", "vector": "SSH pivot -> /home/admin/flag.txt"},

    # ===== Lab UWS (UCC Web Services - puerto 8081) =====
    {"flag": "FLAG{UCC_UWS_SQLi_Bypass}",   "points":  50, "type": "real",    "lab": "UWS", "vector": "SQL Injection"},
    {"flag": "FLAG{UCC_UWS_Cookie_Bypass}", "points":  75, "type": "real",    "lab": "UWS", "vector": "Cookie tampering -> /admin.php"},
    {"flag": "FLAG{UCC_UWS_LFI_Found}",     "points":  60, "type": "real",    "lab": "UWS", "vector": "LFI / Path traversal"},
    {"flag": "FLAG{UCC_UWS_Config_Leaked}", "points":  70, "type": "real",    "lab": "UWS", "vector": "LFI -> config/app.ini"},
    {"flag": "FLAG{UCC_UWS_Cmd_Inject}",    "points":  80, "type": "real",    "lab": "UWS", "vector": "Command Injection en /network.php"},
    {"flag": "FLAG{UCC_UWS_Upload_RCE}",    "points": 120, "type": "real",    "lab": "UWS", "vector": "Webshell via /upload.php"},
    {"flag": "FLAG{UCC_UWS_Hash_Cracked}",  "points": 100, "type": "real",    "lab": "UWS", "vector": "Hash cracking (MD5)"},
    {"flag": "FLAG{UCC_UWS_Pwned}",         "points": 150, "type": "real",    "lab": "UWS", "vector": "SSH pivot -> /home/admin/flag.txt"},

    # ===== Honeypots / trampas (penalizan) =====
    {"flag": "FLAG{HONEYPOT_DO_NOT_SUBMIT_01}", "points": -50, "type": "honeypot", "lab": "*", "vector": "Honeypot (secrets table)"},
    {"flag": "FLAG{HONEYPOT_TRAP_SOC_02}",      "points": -50, "type": "honeypot", "lab": "*", "vector": "Honeypot (secrets table)"},
    {"flag": "FLAG{FAKE_IN_COOKIE}",            "points": -50, "type": "honeypot", "lab": "*", "vector": "Honeypot (cookie de session)"},
]

FLAGS_BY_VALUE = {f["flag"]: f for f in FLAGS}

TEAMS = ("ids", "uws")
TEAM_LABELS = {
    "ids": "Equipo IDS (Ibague Data Services)",
    "uws": "Equipo UWS (UCC Web Services)",
}

# ----------------------------------------------------------------------

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
        """
    )
    db.commit()


@app.teardown_appcontext
def _close_db(exc):
    db = getattr(g, "_db", None)
    if db is not None:
        db.close()


def _client_ip():
    return request.headers.get("X-Forwarded-For", request.remote_addr or "?").split(",")[0].strip()


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
    real = [f for f in FLAGS if f["type"] == "real"]
    out = []
    for f in real:
        rows = db.execute(
            "SELECT team, COUNT(DISTINCT alias) AS solves FROM submissions WHERE flag=? GROUP BY team",
            (f["flag"],),
        ).fetchall()
        solves = {r["team"]: r["solves"] for r in rows}
        out.append(
            {
                "lab": f["lab"],
                "vector": f["vector"],
                "points": f["points"],
                "ids": solves.get("ids", 0),
                "uws": solves.get("uws", 0),
            }
        )
    out.sort(key=lambda x: (x["lab"], -x["points"]))
    return out


@app.get("/")
def index():
    return render_template(
        "index.html",
        teams=_team_scores(),
        players=_player_scores(),
        recent=_recent(),
        challenges=_challenge_progress(),
        last_alias=request.cookies.get("alias", ""),
        last_team=request.cookies.get("team", ""),
        flash=request.args.get("flash", ""),
        flash_class=request.args.get("cls", ""),
    )


@app.post("/submit")
def submit():
    alias = (request.form.get("alias") or "").strip()[:32]
    team  = (request.form.get("team")  or "").strip().lower()
    flag  = (request.form.get("flag")  or "").strip()

    if not alias or team not in TEAMS or not flag:
        return redirect(url_for("index", flash="Datos incompletos. Indica alias, equipo y bandera.", cls="warn"))

    meta = FLAGS_BY_VALUE.get(flag)
    if meta is None:
        resp = redirect(url_for("index", flash="Bandera desconocida o invalida.", cls="warn"))
        resp.set_cookie("alias", alias, max_age=86400)
        resp.set_cookie("team", team, max_age=86400)
        return resp

    db = get_db()

    # No permitimos doble cuenta: si ya esta enviada por ese equipo, no suma de nuevo.
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


@app.post("/reset")
def reset_match():
    """
    Panel del instructor: limpia submissions y reset_attempts.

    Reglas:
    - Password viene de la env var SCOREBOARD_RESET_PASSWORD (secrets.env).
    - Max 2 intentos fallidos por IP; al 2do fallo, ban de 3h.
    - Tras el ban, ni el password correcto pasa (por IP). Si el profe queda
      encerrado, puede usar otra IP o limpiar manualmente con:
         docker exec ctf_scoreboard python -c \
           "import sqlite3;c=sqlite3.connect('/data/scoreboard.db');\
            c.execute('DELETE FROM reset_attempts');c.commit()"
    """
    ip = _client_ip()
    pwd = request.form.get("password") or ""
    db = get_db()
    now = datetime.utcnow()

    row = db.execute("SELECT * FROM reset_attempts WHERE ip=?", (ip,)).fetchone()

    # 1) Ban vigente?
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
                flash=f"IP {ip} baneada del panel de reset. Reintenta en ~{mins} min.",
                cls="danger",
            ))
        # Ban expirado -> se borra el registro y se les da otra tanda de intentos.
        db.execute("DELETE FROM reset_attempts WHERE ip=?", (ip,))
        db.commit()
        row = None

    # 2) Password correcto? (timing-safe; si RESET_PASSWORD esta vacio, falla siempre)
    password_ok = bool(RESET_PASSWORD) and hmac.compare_digest(pwd, RESET_PASSWORD)
    if not password_ok:
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

    # 3) Password correcto -> wipe submissions y historial de bans.
    db.execute("DELETE FROM submissions")
    db.execute("DELETE FROM reset_attempts")
    db.commit()
    return redirect(url_for(
        "index",
        flash="Partida reiniciada. Marcador limpio.",
        cls="success",
    ))


@app.get("/api/scores")
def api_scores():
    return jsonify({"teams": _team_scores(), "players": _player_scores(), "recent": _recent(30)})


@app.get("/healthz")
def healthz():
    return "ok"


if __name__ == "__main__":
    app.run(host="0.0.0.0", port=5000, debug=False)
