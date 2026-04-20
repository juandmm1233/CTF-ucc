# CTF UCC - Laboratorio Docker Compose

Laboratorio vulnerable para practicar **Inyeccion SQL**, **bypass por cookie**
y **pivoting via SSH**, construido con Docker Compose.
Disenado con fines **estrictamente educativos** para actividades Red Team vs Blue Team.

Incluye dos laboratorios hermanos (ambos con marcas ficticias creadas para el CTF):

- `./` -> **Ibague Data Services (IDS)**. Puertos `8080` / `2222`. Bandera final `FLAG{UCC_Ciber_Atrapada}`.
- `./ctf2/` -> **UCC Web Services (UWS)**. Puertos `8081` / `2223`. Bandera final `FLAG{UCC_UWS_Pwned}`.

Ambos pueden correr en paralelo. Revisa `[ctf2/README.md](ctf2/README.md)` para el segundo.

---

## Estructura del proyecto

```
CTF ucc/
├── docker-compose.yml
├── README.md
├── servidor_web/
│   ├── index.php           Login vulnerable a SQLi
│   ├── dashboard.php       Dashboard IDS (metricas dinamicas)
│   ├── compute.php         IDS Compute (credenciales SSH con toggle ojo)
│   ├── storage.php         IDS Storage (bucket con pistas)
│   ├── iam.php             IAM & Admin (lista usuarios de la BD)
│   ├── logs.php            IDS Observability
│   ├── admin.php           Panel oculto (bypass por cookie)
│   ├── logout.php
│   ├── robots.txt          Pistas de rutas ocultas
│   ├── includes/
│   │   ├── auth.php
│   │   ├── db.php
│   │   └── layout.php
│   └── assets/
│       └── style.css       Estilos consola IDS
├── mysql_ssh/
│   ├── Dockerfile          Ubuntu 22.04 + MariaDB + OpenSSH
│   ├── init.sql            Usuarios de la BD y tabla secrets
│   └── entrypoint.sh       Arranque MariaDB + sshd
└── ctf2/                   Clon con marca UWS (ver ctf2/README.md)
```

---

## Servicios

| Servicio | Imagen / Build        | Puerto host | Puerto interno | Funcion                                         |
|----------|-----------------------|-------------|----------------|-------------------------------------------------|
| web_app  | `php:8.2-apache`      | `8080`      | `80`           | Login PHP vulnerable a SQLi                     |
| db_ssh   | `./mysql_ssh` (build) | `2222`      | `22` / `3306`  | MariaDB + OpenSSH con la bandera final          |

Ambos servicios estan aislados en la red Docker `ctf_net`.

---

## Despliegue

Desde la raiz del proyecto:

```bash
docker compose up --build
```

Para detener y limpiar volumenes:

```bash
docker compose down -v
```

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
  en `[mysql_ssh/init.sql](mysql_ssh/init.sql)` con `ALL PRIVILEGES` sobre
  `ctf_login.*` y `CREATE USER` global.
- El contenedor `web_app` recibe las credenciales como variables de entorno
  (`DB_ADMIN_USER` / `DB_ADMIN_PASS`) en `docker-compose.yml`.
- `servidor_web/admin.php` conecta con ese usuario solo cuando la cookie
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

La contrasena `password123` se fija en `[mysql_ssh/Dockerfile](mysql_ssh/Dockerfile)`
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

Viven en `[mysql_ssh/init.sql](mysql_ssh/init.sql)`. Las contrasenas estan en
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

El codigo vulnerable esta en `[servidor_web/index.php](servidor_web/index.php)`:

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

Archivo: `[servidor_web/admin.php](servidor_web/admin.php)`.

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

Archivo: `[mysql_ssh/Dockerfile](mysql_ssh/Dockerfile)`.

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

`[mysql_ssh/entrypoint.sh](mysql_ssh/entrypoint.sh)` escribe la bandera al arrancar:

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
