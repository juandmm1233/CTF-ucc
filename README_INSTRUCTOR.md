# CTF UCC - Laboratorio Docker Compose

Laboratorio vulnerable construido con Docker Compose para entrenar **Red Team
vs Blue Team** en grupos grandes (probado con clases de 30 estudiantes, 15 vs 15).
Disenado con fines **estrictamente educativos**.

## Edicion 15 vs 15: 2 labs + scoreboard + 8 vectores por lab

Cada lab esta pensado para correr en una **VM independiente** dentro de la
misma red. El scoreboard es un servicio aparte y debe correr en **una sola**
maquina (cualquiera de las dos VMs, o una tercera "VM del instructor").

| Carpeta         | Componente                     | Puerto web | Puerto SSH | Bandera final                |
|-----------------|--------------------------------|------------|------------|------------------------------|
| `ctf1/`         | **Ibague Data Services (IDS)** | `8080`     | `2222`     | `FLAG{UCC_Ciber_Atrapada}`   |
| `ctf2/`         | **UCC Web Services (UWS)**     | `8081`     | `2223`     | `FLAG{UCC_UWS_Pwned}`        |
| `scoreboard/`   | Tablero Flask + SQLite         | `9000`     | -          | -                            |

> **Por que un solo scoreboard?** Cada VM tiene su propia base de datos
> SQLite local. Si levantas el scoreboard en las dos VMs, los puntajes
> se duplican y no se sincronizan. Levantalo en **una sola** VM y desde
> las otras solo apunta `SCOREBOARD_URL` a esa IP.

Cada lab expone los **mismos 8 vectores**, asi cada subgrupo del Red Team
puede atacar uno distinto sin chocar y los del Blue Team pueden repartirse
los parches uno a uno.

### Vectores disponibles (por lab)

| # | Vector              | Endpoint                              | Pts | Pista en pagina                       |
|---|---------------------|---------------------------------------|-----|---------------------------------------|
| 1 | SQL Injection       | `/index.php` (login)                  | 50  | hint en card de bienvenida             |
| 2 | Cookie tampering    | `/admin.php`                          | 75  | mencionado en `/robots.txt`           |
| 3 | LFI / Path traversal| `/download.php?file=`                 | 60  | `admin_notes.md` lo confiesa          |
| 4 | Config leak via LFI | `/download.php?file=../config/app.ini`| 70  | mencionado en `admin_notes.md`        |
| 5 | Command Injection   | `/network.php`                        | 80  | nuevo item en sidebar "Network Tools" |
| 6 | RCE via Upload      | `/upload.php`                         | 120 | boton "Subir archivo" en Storage      |
| 7 | Hash cracking (MD5) | tabla `secrets` via SQLi UNION        | 100 | hashes etiquetados `legacy_md5_*`     |
| 8 | SSH pivot           | `ssh admin@<ip> -p 2222/2223`         | 150 | credenciales en `/compute.php`        |

Mas, **3 banderas trampa (honeypots)** que **restan 50 pts** si se envian al scoreboard
(prefijo `FLAG{HONEYPOT_*}` o `FLAG{FAKE_*}`). Solo el instructor sabe cuales son.

### Para los estudiantes
- Red Team: ver [`docs/RED_TEAM_PLAYBOOK.md`](docs/RED_TEAM_PLAYBOOK.md).
- Blue Team: ver [`docs/BLUE_TEAM_PLAYBOOK.md`](docs/BLUE_TEAM_PLAYBOOK.md).
- Demo de CSRF lista para subir: [`docs/csrf_demo/attacker.html`](docs/csrf_demo/attacker.html).

### Reglas sugeridas para 15v15
1. Red Team se divide en **5 trios**, cada uno se especializa en 1-2 vectores.
2. Blue Team se divide en **5 parejas/trios**, cada uno duenos de 1 vector +
   monitoreo de logs.
3. Permitido: parchear PHP, agregar `.htaccess`, cambiar passwords, fail2ban.
4. Prohibido: apagar Apache/MySQL/SSH, borrar archivos completos.
5. Cada bandera real cuenta **una sola vez por equipo**. Honeypots restan.
6. Duracion sugerida: 2 horas (90 min ataque + 30 min defensa proactiva).

---

## Estructura del proyecto

```
CTF ucc/
├── docker-compose.yml      All-in-one local (solo pruebas, no produccion)
├── README.md
├── ctf1/                   <-- VM 1: Ibague Data Services (IDS)
│   ├── docker-compose.yml      web_app + db_ssh standalone
│   ├── servidor_web/
│   │   ├── index.php           Login vulnerable a SQLi
│   │   ├── dashboard.php       Dashboard IDS (metricas dinamicas)
│   │   ├── compute.php         IDS Compute (credenciales SSH con toggle ojo)
│   │   ├── storage.php         IDS Storage (lista bucket + uploads)
│   │   ├── network.php         Diagnostico (Command Injection)
│   │   ├── download.php        Preview del bucket (LFI)
│   │   ├── upload.php          Subida de archivos (RCE)
│   │   ├── iam.php             IAM & Admin (lista usuarios de la BD)
│   │   ├── logs.php            IDS Observability
│   │   ├── admin.php           Panel oculto (bypass por cookie + alta de usuarios)
│   │   ├── bucket/             Archivos servidos por download.php
│   │   ├── uploads/            Carpeta donde aterrizan los archivos subidos
│   │   ├── config/app.ini      Archivo "filtrable" via LFI
│   │   ├── robots.txt          Pistas de rutas ocultas
│   │   ├── includes/{auth,db,layout}.php
│   │   └── assets/{style.css,favicon.svg}
│   └── mysql_ssh/
│       ├── Dockerfile          Ubuntu 22.04 + MariaDB + OpenSSH
│       ├── init.sql            Usuarios + tabla secrets + hashes MD5 + honeypot
│       └── entrypoint.sh       Arranque MariaDB + sshd + bandera SSH
├── ctf2/                   <-- VM 2: UCC Web Services (UWS)  [misma estructura]
│   ├── docker-compose.yml
│   ├── servidor_web/
│   └── mysql_ssh/
├── scoreboard/             <-- UNA sola VM: Scoreboard 15v15
│   ├── docker-compose.yml
│   ├── Dockerfile
│   ├── app.py              Flask + SQLite, banderas + honeypots
│   ├── requirements.txt
│   ├── templates/index.html
│   └── static/{style.css,favicon.svg}
└── docs/
    ├── RED_TEAM_PLAYBOOK.md
    ├── BLUE_TEAM_PLAYBOOK.md
    └── csrf_demo/attacker.html
```

---

## Despliegue por VM independiente (escenario 15 vs 15)

### Paso 1 - VM 1 (CTF1 / IDS)

```bash
cd ctf1
# (opcional) URL publica del scoreboard, ej: http://10.0.0.50:9000
export SCOREBOARD_URL=http://<IP-DE-LA-VM-DE-SCOREBOARD>:9000
docker compose up -d --build
```

Listo en `http://<IP-VM1>:8080` y `ssh admin@<IP-VM1> -p 2222` (`password123`).

### Paso 2 - VM 2 (CTF2 / UWS)

```bash
cd ctf2
export SCOREBOARD_URL=http://<IP-DE-LA-VM-DE-SCOREBOARD>:9000
docker compose up -d --build
```

Listo en `http://<IP-VM2>:8081` y `ssh admin@<IP-VM2> -p 2223`.

### Paso 3 - VM del scoreboard (SOLO UNA)

Puede ser la misma VM1, la VM2, o una tercera. Lo importante es que sea **una sola**:

```bash
cd scoreboard
docker compose up -d --build
```

Listo en `http://<IP-SCOREBOARD>:9000`. Esa misma URL es la que cada VM debe
exportar en `SCOREBOARD_URL` para que aparezca el banner con el link en los
dashboards.

### Detener y limpiar (en cada VM por separado)

```bash
docker compose down -v
```

---

## Despliegue todo-en-uno (solo pruebas locales del instructor)

Si quieres levantar IDS + UWS + scoreboard en una sola maquina (debug pre-clase):

```bash
docker compose up -d --build
# IDS:        http://localhost:8080
# UWS:        http://localhost:8081
# Scoreboard: http://localhost:9000
```

> Este `docker-compose.yml` raiz **no debe usarse en clase** (queda todo en
> una sola maquina y no aprovecha la red Red vs Blue). Solo sirve para
> probar que las imagenes se construyen y los endpoints responden.

---

## Flujo de resolucion del CTF (Red Team)

1. Abrir `http://localhost:8080` en el navegador.
2. **SQL injection** en el login:
   - Usuario: `' OR '1'='1' -- `
   - Contrasena: cualquier valor.
3. Entras directo al **Dashboard**. Revisa el sidebar -> **Compute Engine** y pulsa el boton del ojo para revelar las credenciales SSH.
4. Pistas adicionales para escalar:
   - `http://localhost:8080/robots.txt` expone `/admin.php`.
   - En `Cloud Logging` hay una linea `DEBUG ... legacy cookie 'role' still honored`.
5. **Cookie tampering** para la bandera intermedia:
   - DevTools -> Application -> Cookies -> anadir `role=admin`.
   - Recargar `/admin.php` -> `FLAG{UCC_Cookie_Bypass_OK}`.
6. **Pivot por SSH** para la bandera final:

```bash
ssh admin@localhost -p 2222
# contrasena: password123
cat /home/admin/flag.txt
# FLAG{UCC_Ciber_Atrapada}
```

---

## Extension: provision de cuentas desde /admin.php

Despues del bypass de cookie y de obtener la bandera intermedia, el panel
`/admin.php` expone un **formulario para crear usuarios con doble impacto**:
se registran en la tabla `ctf_login.users` (login web) y se provisionan como
**usuarios reales de MariaDB** con los GRANT correspondientes.

### Roles disponibles

| Rol          | Acceso web                       | Acceso MariaDB                         |
|--------------|----------------------------------|----------------------------------------|
| `admin`      | lectura/escritura, panel admin   | `GRANT ALL PRIVILEGES ON ctf_login.*`  |
| `observador` | solo lectura, banner informativo | `GRANT SELECT ON ctf_login.*`          |

### Mecanismo interno

- Un usuario MariaDB privilegiado `ctf_admin` / `ctf_admin_pass` es creado
  en `[ctf1/mysql_ssh/init.sql](ctf1/mysql_ssh/init.sql)` con `ALL PRIVILEGES` sobre
  `ctf_login.*` y `CREATE USER` global.
- El contenedor `web_app` recibe las credenciales como variables de entorno
  (`DB_ADMIN_USER` / `DB_ADMIN_PASS`) en `docker-compose.yml`.
- `ctf1/servidor_web/admin.php` conecta con ese usuario solo cuando la cookie
  `role=admin` valida el bypass. El flujo:
  1. `INSERT ... ON DUPLICATE KEY UPDATE` en `users` (con prepared statement).
  2. `CREATE USER IF NOT EXISTS ... IDENTIFIED BY ...` + `ALTER USER` para
     forzar la contrasena enviada.
  3. `REVOKE ALL PRIVILEGES` seguido de `GRANT` segun el rol elegido.
  4. `FLUSH PRIVILEGES`.

### Uso de las nuevas credenciales

Al crear la cuenta, la UI muestra dos formas de usarla:

```bash
# 1) Login web (sin necesidad de SQLi)
http://localhost:8080/index.php

# 2) Conexion directa a MariaDB (desde el contenedor db_ssh o via tunel SSH)
mysql -h 127.0.0.1 -u <nuevo_usuario> -p'<password>' ctf_login
```

El rol persiste en la sesion PHP (`$_SESSION['ctf_role']`). El layout muestra
un chip (`admin` en ambar, `observador` en verde) en la barra superior y un
banner de "Modo observador" para quienes solo pueden leer.

### Ideas de ejercicios complementarios

- **Red Team**: forzar la creacion de un `admin` con contrasena corta para
  simular una escalada de privilegios; demostrar lateralidad entrando luego
  por MariaDB con `mysql -u <user> -p ctf_login`.
- **Blue Team**:
  - Restringir el uso de `ctf_admin` a rutas que requieran autenticacion
    fuerte (token + CSRF + MFA simulado).
  - Quitar los grants globales de `ctf_admin` (dejar solo `ctf_login.*`).
  - Reemplazar `ctf_db_connect_admin()` por una API que haga hashing
    (`PASSWORD_DEFAULT` con `password_hash`) para no guardar passwords en
    texto plano.
  - Validar el rol en la sesion (`ctf_is_admin()`) ademas de la cookie, para
    cerrar el bypass trivial.

---

## Ejercicio Blue Team - Endurecer el laboratorio

El equipo defensivo debe trabajar **dentro del laboratorio** (sin romper la
infraestructura) para cerrar los vectores de ataque. A continuacion se listan
tareas priorizadas, como realizarlas y como verificar que quedaron aplicadas.

> Importante: algunos cambios requieren reiniciar los contenedores.
> Usa `docker compose restart web_app` para solo la web o
> `docker compose up --build` para reconstruir la imagen de `db_ssh`.

### 1. Cambiar contrasenas de usuarios

#### 1.1 Usuario SSH `admin` (sistema Linux, contenedor `db_ssh`)

La contrasena `password123` se fija en `[ctf1/mysql_ssh/Dockerfile](ctf1/mysql_ssh/Dockerfile)`
con la linea:

```dockerfile
RUN useradd -m -s /bin/bash admin \
    && echo 'admin:password123' | chpasswd
```

**Opcion A - Rotar al construir la imagen** (permanente):

1. Editar la linea anterior, por ejemplo:

   ```dockerfile
   && echo 'admin:N3w_Str0ng_P@ss_2026!' | chpasswd
   ```

2. Reconstruir y reiniciar:

   ```bash
   docker compose up --build -d db_ssh
   ```

**Opcion B - Rotar en caliente** (no sobrevive a rebuild, sirve para demos):

```bash
docker compose exec db_ssh bash
passwd admin
# teclear la nueva contrasena dos veces
exit
```

**Verificacion**:

```bash
ssh admin@localhost -p 2222        # debe rechazar password123
ssh admin@localhost -p 2222        # debe aceptar la nueva
```

#### 1.2 Usuarios de la aplicacion web (tabla `users` en MariaDB)

Viven en `[ctf1/mysql_ssh/init.sql](ctf1/mysql_ssh/init.sql)`. Las contrasenas estan en
**texto claro** (intencional, para el SQLi didactico).

**Opcion A - Editar init.sql y recrear la BD** (recomendado):

1. Modificar los `INSERT INTO users ...` del archivo, por ejemplo:

   ```sql
   INSERT INTO users (username, password, role) VALUES
       ('root',    'ZxC!rootP@ss#2026', 'admin'),
       ('admin',   'N3w_Str0ng_P@ss!',  'admin'),
       ('jdoe',    'JD_2026#secure',    'user'),
       ('guest',   'GuestR0tated!',     'user');
   ```

2. Recrear el volumen de la BD (borra datos persistidos) y rebuild:

   ```bash
   docker compose down -v
   docker compose up --build
   ```

**Opcion B - Actualizar en caliente** (sin rebuild):

```bash
docker compose exec db_ssh mariadb -uroot ctf_login
```

Dentro del prompt:

```sql
UPDATE users SET password = 'N3w_Str0ng_P@ss!' WHERE username = 'admin';
UPDATE users SET password = 'JD_2026#secure'   WHERE username = 'jdoe';
FLUSH PRIVILEGES;
SHOW WARNINGS;
\q
```

**Verificacion**:

- `http://localhost:8080` con usuario `admin` + contrasena vieja -> debe fallar.
- Login con la nueva contrasena -> debe pasar.
- El payload SQLi `' OR '1'='1' -- ` seguira funcionando hasta que tambien
  parchees el codigo PHP (ver seccion 2).

#### 1.3 Usuario de servicio `ctf_web` (la que usa PHP para conectarse)

Definido en `init.sql` y consumido desde `[docker-compose.yml](docker-compose.yml)`
mediante la variable `DB_PASS`.

1. En `init.sql`, cambiar:

   ```sql
   CREATE USER IF NOT EXISTS 'ctf_web'@'%' IDENTIFIED BY 'OtraPassBien#Larga';
   ```

2. En `docker-compose.yml`, actualizar la variable:

   ```yaml
   environment:
     DB_PASS: OtraPassBien#Larga
   ```

3. Recrear:

   ```bash
   docker compose down -v
   docker compose up --build
   ```

---

### 2. Parchear la inyeccion SQL

El codigo vulnerable esta en `[ctf1/servidor_web/index.php](ctf1/servidor_web/index.php)`:

```php
$query = "SELECT id, username FROM users WHERE username = '" . $user . "' AND password = '" . $pass . "'";
$result = @$mysqli->query($query);
```

**Parche recomendado** (prepared statements con `mysqli`):

```php
$stmt = $mysqli->prepare("SELECT id, username FROM users WHERE username = ? AND password = ?");
$stmt->bind_param("ss", $user, $pass);
$stmt->execute();
$result = $stmt->get_result();
```

**Verificacion**:
- `' OR '1'='1' -- ` ya no debe pasar.
- Los usuarios validos si deben autenticarse.

**Mejora extra**: almacenar contrasenas con `password_hash()` y validar con
`password_verify()` en lugar de comparacion en texto claro.

---

### 3. Corregir el bypass por cookie (`/admin.php`)

Archivo: `[ctf1/servidor_web/admin.php](ctf1/servidor_web/admin.php)`.

```php
$role_cookie = isset($_COOKIE['role']) ? $_COOKIE['role'] : '';
$is_admin = ($role_cookie === 'admin');
```

**Parche**: apoyarse en la sesion PHP, no en una cookie que el cliente controla.

```php
ctf_require_login();
$is_admin = !empty($_SESSION['ctf_user']) && $_SESSION['ctf_user'] === 'root';
```

O derivar el rol leyendo la tabla `users` en cada request:

```php
$stmt = $mysqli->prepare("SELECT role FROM users WHERE username = ?");
$stmt->bind_param("s", $_SESSION['ctf_user']);
...
```

**Verificacion**: setear `role=admin` en DevTools no debe dar acceso.

---

### 4. Endurecer SSH

Archivo: `[ctf1/mysql_ssh/Dockerfile](ctf1/mysql_ssh/Dockerfile)`.

- Deshabilitar autenticacion por contrasena y usar claves publicas:

  ```dockerfile
  && sed -i 's/^#\?PasswordAuthentication.*/PasswordAuthentication no/' /etc/ssh/sshd_config \
  && sed -i 's/^#\?PubkeyAuthentication.*/PubkeyAuthentication yes/' /etc/ssh/sshd_config
  ```

- Inyectar una `authorized_keys` del equipo Blue:

  ```dockerfile
  RUN mkdir -p /home/admin/.ssh \
      && chmod 700 /home/admin/.ssh
  COPY authorized_keys /home/admin/.ssh/authorized_keys
  RUN chmod 600 /home/admin/.ssh/authorized_keys \
      && chown -R admin:admin /home/admin/.ssh
  ```

- Limitar intentos: instalar y configurar `fail2ban` o ajustar `MaxAuthTries`.
- Mover el puerto publicado (`2222 -> 2022`) en `docker-compose.yml`.

**Verificacion**: `ssh admin@localhost -p 2222` con contrasena debe fallar.

---

### 5. Rotar la bandera

`[ctf1/mysql_ssh/entrypoint.sh](ctf1/mysql_ssh/entrypoint.sh)` escribe la bandera al arrancar:

```bash
echo "FLAG{UCC_Ciber_Atrapada}" > "${FLAG_PATH}"
```

1. Cambiar el literal por una bandera nueva (por ej. `FLAG{UCC_Blue_Win_2026}`).
2. Ajustar permisos si quieres que solo root pueda leerla:

   ```bash
   chown root:root "${FLAG_PATH}"
   chmod 600 "${FLAG_PATH}"
   ```

3. Reconstruir: `docker compose up --build -d db_ssh`.

---

### 6. Tapar fugas de informacion

- Eliminar o restringir `robots.txt` para no exponer `/admin.php`.
- Revisar que `Cloud Logging` (`logs.php`) no imprima la pista sobre la cookie.
- Quitar el boton de "Mostrar credenciales" en `compute.php` o devolver
  credenciales falsas desde una API protegida por rol real.
- Asegurar que `/includes/` no se sirva directamente (anadir un `.htaccess`
  con `Require all denied`).

---

### 7. Checklist de verificacion Blue Team

El equipo Blue puede considerar el sitio "endurecido" cuando:

- [ ] Payload `' OR '1'='1' -- ` ya no permite login.
- [ ] `admin` en la web requiere su contrasena nueva (no `password123`).
- [ ] SSH con contrasena falla; solo entra con clave publica autorizada.
- [ ] `GET /admin.php` con cookie `role=admin` devuelve 403/redirect.
- [ ] La bandera final cambio y solo es legible por root.
- [ ] `/robots.txt` ya no expone rutas sensibles.
- [ ] `Cloud Logging` no filtra pistas sobre vulnerabilidades.

Cada item pasado equivale a un punto defensivo en la rubrica del ejercicio.

---

## Notas de seguridad

- Este laboratorio contiene vulnerabilidades intencionales
  (SQLi, contrasenas debiles, acceso SSH por contrasena, control de acceso
  por cookie). Es **inseguro por diseno**.
- **No** exponer estos contenedores a Internet publico.
- **No** reutilizar las credenciales ni los patrones de codigo vulnerables
  en entornos reales.
- Al terminar el ejercicio, ejecutar `docker compose down -v` para eliminar
  contenedores, volumenes y la bandera.
