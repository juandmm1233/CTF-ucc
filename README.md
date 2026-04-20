# CTF UCC - Laboratorio Docker Compose

Laboratorio vulnerable para practicar **Inyeccion SQL** y **pivoting via SSH**, construido con Docker Compose. Disenado con fines estrictamente educativos.

## Estructura del proyecto

```
CTF ucc/
├── docker-compose.yml
├── README.md
├── servidor_web/
│   ├── index.php
│   └── assets/
│       └── style.css
└── mysql_ssh/
    ├── Dockerfile
    ├── init.sql
    └── entrypoint.sh
```

## Servicios

| Servicio | Imagen / Build        | Puerto host | Puerto interno | Funcion                                         |
|----------|-----------------------|-------------|----------------|-------------------------------------------------|
| web_app  | `php:8.2-apache`      | `8080`      | `80`           | Login PHP vulnerable a SQLi                     |
| db_ssh   | `./mysql_ssh` (build) | `2222`      | `22` / `3306`  | MariaDB + OpenSSH con la bandera final          |

Ambos servicios estan aislados en la red Docker `ctf_net`.

## Despliegue

Desde la raiz del proyecto:

```bash
docker compose up --build
```

Para detener y limpiar:

```bash
docker compose down -v
```

## Flujo de resolucion del CTF

1. Abrir `http://localhost:8080` en el navegador.
2. En el formulario de login, inyectar un payload SQLi clasico, por ejemplo:
   - Usuario: `' OR '1'='1' -- `
   - Contrasena: cualquier valor.
3. La pagina mostrara un banner de exito y revelara las credenciales:
   - Usuario SSH: `admin`
   - Contrasena: `password123`
4. Conectarse al contenedor `db_ssh` por SSH:

```bash
ssh admin@localhost -p 2222
# contrasena: password123
```

5. Leer la bandera final:

```bash
cat /home/admin/flag.txt
# FLAG{UCC_Ciber_Atrapada}
```

## Notas de seguridad

- Este laboratorio contiene vulnerabilidades intencionales (SQLi, contrasenas debiles, acceso SSH por contrasena).
- **No** exponer estos contenedores a Internet.
- **No** reutilizar las credenciales ni los patrones de codigo vulnerables en entornos reales.
