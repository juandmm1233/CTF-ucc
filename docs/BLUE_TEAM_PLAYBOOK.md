# Blue Team Playbook - CTF UCC 15 vs 15

> Guia rapida para los 15 estudiantes que defienden los labs IDS / UWS.
> Cada vector tiene un fix corto. Apliquenlos en caliente sin tumbar el servicio.
> Despues del CTF, escriban un informe de 1 pagina con que aplicaron y por que.

## Tabla de mando

| # | Vector              | Endpoint vulnerable          | Tecnica de fix sugerida                              |
|---|---------------------|------------------------------|------------------------------------------------------|
| 1 | SQL Injection       | `/index.php`                 | Prepared statements (`mysqli_prepare`/`?`)           |
| 2 | Cookie Bypass       | `/admin.php`                 | Validar rol contra sesion, no contra cookie         |
| 3 | LFI / Path traversal| `/download.php?file=`        | `realpath()` + comparacion contra dir base          |
| 4 | Command Injection   | `/network.php`               | `escapeshellarg()` + lista blanca de hosts/IPs      |
| 5 | RCE via upload      | `/upload.php`                | Validar extension, usar nombre aleatorio, deshabilitar PHP en `uploads/` |
| 6 | Hash cracking       | `secrets` (DB)               | Eliminar hashes, no exponer la tabla via SQLi       |
| 7 | SSH pivot           | container `db_ssh`           | Cambiar password, deshabilitar `PasswordAuthentication`, fail2ban |
| 8 | CSRF                | `/admin.php`                 | Token CSRF en formulario + verificar en backend     |

## Checklist rapido por persona del Blue Team

Sugerencia: 15 personas → grupos de 2 a 3, cada grupo dueño de 1 vector (recuerden que tambien tienen que defender estas mismas vulnerabilidades en su sistema).

### Grupo A - SQLi (`/index.php`)
1. Editar `<lab>/servidor_web/index.php` (donde `<lab>` es `ctf1` o `ctf2`)
   y reemplazar el `SELECT` concatenado por:
   ```php
   $stmt = $mysqli->prepare("SELECT id, username, role FROM users WHERE username=? AND password=?");
   $stmt->bind_param("ss", $user, $pass);
   $stmt->execute();
   $result = $stmt->get_result();
   ```
2. Dejar el resto del flujo igual. Probar con `' OR '1'='1` → debe fallar.

### Grupo B - Cookie bypass (`/admin.php`)
1. Eliminar la lectura de `$_COOKIE['role']`.
2. Validar siempre con `ctf_is_admin()` (que mira `$_SESSION['ctf_role']`).
3. Devolver 403 si no es admin.

### Grupo C - LFI (`/download.php`)
1. Restringir al directorio del bucket:
   ```php
   $base = realpath(__DIR__ . '/bucket');
   $req  = realpath($base . '/' . $file);
   if ($req === false || strpos($req, $base . DIRECTORY_SEPARATOR) !== 0) {
       http_response_code(403);
       exit('Acceso denegado');
   }
   ```
2. Probar `?file=../config/app.ini` → debe rechazar.

### Grupo D - Command Injection (`/network.php`)
1. Reemplazar la concatenacion por `escapeshellarg($host)`.
2. (Opcional, mejor) lista blanca con regex: solo hostnames y IPs validos:
   ```php
   if (!preg_match('/^[a-zA-Z0-9._-]+$/', $host)) { exit('Host invalido'); }
   ```
3. Probar payload `db_ssh; cat /etc/passwd` → debe rechazarlo o escaparlo.

### Grupo E - Upload RCE (`/upload.php`)
Tres mitigaciones combinables (idealmente todas):
1. Lista blanca de extensiones (`txt`, `md`, `gz`, `pdf`, `png`...).
2. Renombrar archivos: `bin2hex(random_bytes(8)) . '.' . $ext`.
3. Crear `<lab>/servidor_web/uploads/.htaccess` con:
   ```apache
   <FilesMatch "\.(php|phtml|phar|cgi)$">
       Require all denied
   </FilesMatch>
   php_flag engine off
   ```
   Luego reiniciar el contenedor y probar subir `shell.php` → no debe ejecutarse.

### Grupo F - Hash cracking (`secrets`)
1. Conectarse a MariaDB con `ctf_admin` y borrar las filas:
   ```sql
   DELETE FROM secrets WHERE label LIKE 'legacy_md5_%';
   DELETE FROM secrets WHERE label='hash_flag_hint';
   ```
2. Asegurar que `ctf_web` no pueda leer la tabla `secrets` (`REVOKE SELECT ON ctf_login.secrets FROM 'ctf_web'@'%';`).

### Grupo G - SSH hardening (container `db_ssh`)

> **Regla del juego**: la bandera (`/home/admin/flag.txt`) **debe permanecer en su ruta original**
> y **legible por `admin`**. La defensa es *auth hardening*, NO esconder o mover el archivo.
> Si la mueven o borran, el equipo es descalificado del vector 7.

1. Entrar al contenedor: `docker exec -it ctf1_db_ssh bash` (o `ctf2_db_ssh`).
2. Cambiar password con uno fuerte: `passwd admin` (mas de 16 caracteres, mixto).
3. En `/etc/ssh/sshd_config`:
   - `PermitRootLogin no` (ya viene asi por default).
   - `MaxAuthTries 3`
   - `LoginGraceTime 10`
   - Opcional avanzado: `PasswordAuthentication no` + subir llave publica del equipo a `/home/admin/.ssh/authorized_keys`.
4. `service ssh restart` (verifica que el servicio sigue UP en el puerto 22 del contenedor).
5. Probar desde otra terminal: `ssh admin@<ip> -p 2222` con password VIEJO -> debe fallar.
   Con password NUEVO -> debe entrar.
6. (Opcional) Instalar `fail2ban` para banear IPs con multiples fallos:
   `apt-get update && apt-get install -y fail2ban && service fail2ban start`.

### Grupo H - CSRF en `/admin.php`
1. En cada formulario sensible:
   ```php
   if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
   echo '<input type="hidden" name="csrf" value="'.$_SESSION['csrf'].'">';
   ```
2. Validar al recibir el POST: si no coincide, abortar.
3. Probar el `docs/csrf_demo/attacker.html` → ya no debe crear usuarios.

## Deteccion en vivo (todo el equipo)

- **Dashboard**: dejen abierta la pagina `/logs.php` y `/iam.php`.
- **Tail de Apache**:
  ```sh
  docker logs -f ctf1_web_app  # IDS
  docker logs -f ctf2_web_app  # UWS
  ```
- **MySQL audit**: queries sospechosas con `OR '1'='1`.
- **Honeypot trap**: si el scoreboard registra que un equipo envio una bandera
  `FLAG{HONEYPOT_*}`, eso significa que un atacante toco la tabla `secrets`
  o leyo cookies sensibles. Avisen al instructor para puntos extra de Blue Team.

## Reglas del juego para Blue Team
- No pueden apagar Apache/MySQL/SSH (los servicios deben seguir respondiendo).
- No pueden borrar `index.php`, `admin.php`, etc. (solo modificarlos).
- Pueden agregar headers, .htaccess, sanitizacion, fail2ban, etc.
- Cada parche aplicado es defendible: el instructor puede pedirles que expliquen
  *por que* mitiga el vector.
