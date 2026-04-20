# CTF UCC 2 - UWS Edition

Clon del laboratorio CTF UCC con estetica **UCC Web Services (UWS)**,
una consola ficticia de servicios en la nube. Pensado para un ejercicio
**Blue Team vs Red Team** donde cada equipo ataca/defiende su propia VM
en paralelo al laboratorio original **Ibague Data Services (IDS)**.

## Estructura

```
ctf2/
├── docker-compose.yml
├── README.md
├── servidor_web/
│   ├── index.php
│   ├── dashboard.php
│   ├── compute.php      (UWS Compute)
│   ├── storage.php      (UWS Storage)
│   ├── iam.php          (IAM)
│   ├── logs.php         (UWS Observability)
│   ├── admin.php        (Cuenta Administrador - bypass por cookie)
│   ├── logout.php
│   ├── robots.txt
│   ├── includes/
│   │   ├── auth.php
│   │   ├── db.php
│   │   └── layout.php
│   └── assets/
│       └── style.css
└── mysql_ssh/
    ├── Dockerfile           (Debian bookworm-slim)
    ├── supervisord.conf     (orquesta mariadb + sshd)
    ├── init.sh              (inicializacion one-shot)
    └── init.sql
```

## Diferencias clave respecto al lab original

| Aspecto          | ctf (IDS)                   | ctf2 (UWS)                         |
|------------------|-----------------------------|------------------------------------|
| Marca visual     | Ibague Data Services (IDS)  | UCC Web Services (UWS)             |
| Base Dockerfile  | `ubuntu:22.04`              | `debian:bookworm-slim`             |
| Orquestacion     | Shell + `mariadbd` en fg    | `supervisord` (mariadb + sshd)     |
| Puerto web       | `8080`                      | `8081`                             |
| Puerto SSH       | `2222`                      | `2223`                             |
| Red Docker       | `ctf_net`                   | `ctf_net2`                         |
| Contenedores     | `ctf_web_app`, `ctf_db_ssh` | `ctf2_web_app`, `ctf2_db_ssh`      |
| Bandera final    | `FLAG{UCC_Ciber_Atrapada}`  | `FLAG{UCC_UWS_Pwned}`              |
| Bandera cookie   | `FLAG{UCC_Cookie_Bypass_OK}`| `FLAG{UCC_UWS_Cookie_Bypass}`      |
| Session cookie   | sesion PHP por defecto      | `ctf2_sess` (evita colision)       |

## Despliegue

Desde la carpeta `ctf2/`:

```bash
docker compose up --build
```

Para detener:

```bash
docker compose down -v
```

Los dos laboratorios pueden correr **a la vez en la misma maquina** sin colisiones.

## Flujo de resolucion (Red Team)

1. Abrir `http://localhost:8081` (pantalla "Iniciar sesion" de UWS).
2. Inyectar SQLi en el login, por ejemplo:
   - Usuario: `' OR '1'='1' -- `
   - Password: cualquier valor.
3. Entras directo al **Console Home** (dashboard).
4. Ir a **Compute** en el sidebar → pulsar el ojo para revelar credenciales SSH.
5. Pistas adicionales:
   - `/robots.txt` expone `/admin.php`.
   - **UWS Observability** (logs) revela que `/admin.php` honra la cookie `role`.
6. En DevTools → Application → Cookies → agregar `role=admin` → recargar `/admin.php` para obtener la bandera intermedia `FLAG{UCC_UWS_Cookie_Bypass}`.
7. Conectarse por SSH:

```bash
ssh admin@localhost -p 2223
# password: password123
cat /home/admin/flag.txt   # FLAG{UCC_UWS_Pwned}
```

## Provision de cuentas desde /admin.php

Tras el bypass de cookie, el panel `/admin.php` permite crear cuentas con
**doble impacto**:

- Se registran en `ctf_login.users` (login UWS en `http://localhost:8081`).
- Se provisionan como usuarios reales de MariaDB con GRANTs segun el rol:

| Rol          | Web UWS                          | MariaDB                                |
|--------------|----------------------------------|----------------------------------------|
| `admin`      | lectura/escritura                | `GRANT ALL PRIVILEGES ON ctf_login.*`  |
| `observador` | solo lectura + banner informativo| `GRANT SELECT ON ctf_login.*`          |

Motor del provisionamiento: usuario MariaDB `ctf_admin` / `ctf_admin_pass`
(creado en `mysql_ssh/init.sql`) expuesto al contenedor `web_app` via las
env vars `DB_ADMIN_USER` / `DB_ADMIN_PASS` en `docker-compose.yml`.

Ejemplo de uso tras crear la cuenta desde la UI:

```bash
# Login web sin SQLi
http://localhost:8081/index.php

# Acceso directo a MariaDB (tunel SSH o docker exec)
mysql -h 127.0.0.1 -u <nuevo_usuario> -p'<password>' ctf_login
```

El rol queda en `$_SESSION['ctf_role']`: el layout muestra un chip en la
topbar y un banner "Modo observador" cuando el rol no es admin.

## Notas

- Laboratorio estrictamente educativo. Contiene vulnerabilidades intencionales.
- UCC Web Services (UWS) es una marca ficticia creada para el CTF de la
  Universidad Cooperativa de Colombia. No representa ningun proveedor real.
- No exponer a internet publico.
- Rotar/eliminar contenedores al terminar el ejercicio.
