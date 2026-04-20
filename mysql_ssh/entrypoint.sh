#!/bin/bash
set -e

MYSQL_DATADIR="/var/lib/mysql"
INIT_FLAG="${MYSQL_DATADIR}/.ctf_initialized"

# --- 1) Preparar datadir de MariaDB si esta vacio ---
if [ ! -d "${MYSQL_DATADIR}/mysql" ]; then
    echo "[entrypoint] Inicializando datadir de MariaDB..."
    mysql_install_db --user=mysql --datadir="${MYSQL_DATADIR}" >/dev/null
fi

chown -R mysql:mysql "${MYSQL_DATADIR}"

# --- 2) Asegurar que /run/mysqld exista ---
mkdir -p /run/mysqld
chown -R mysql:mysql /run/mysqld

# --- 3) Arrancar MariaDB en background ---
echo "[entrypoint] Iniciando MariaDB..."
mysqld_safe --user=mysql --bind-address=0.0.0.0 &
MYSQL_PID=$!

# --- 4) Esperar a que MySQL este listo ---
echo "[entrypoint] Esperando a que MariaDB acepte conexiones..."
for i in $(seq 1 60); do
    if mysqladmin ping --silent 2>/dev/null; then
        echo "[entrypoint] MariaDB listo."
        break
    fi
    sleep 1
done

# --- 5) Ejecutar init.sql solo la primera vez ---
if [ ! -f "${INIT_FLAG}" ]; then
    echo "[entrypoint] Aplicando init.sql..."
    mysql < /opt/init.sql
    touch "${INIT_FLAG}"
    echo "[entrypoint] Base de datos del CTF inicializada."
else
    echo "[entrypoint] Base de datos ya estaba inicializada, se omite init.sql."
fi

# --- 6) Crear la bandera en /home/admin/flag.txt ---
FLAG_PATH="/home/admin/flag.txt"
if [ ! -f "${FLAG_PATH}" ]; then
    echo "FLAG{UCC_Ciber_Atrapada}" > "${FLAG_PATH}"
    chown admin:admin "${FLAG_PATH}"
    chmod 640 "${FLAG_PATH}"
    echo "[entrypoint] Bandera depositada en ${FLAG_PATH}."
fi

# --- 7) Generar host keys de SSH si no existen ---
ssh-keygen -A >/dev/null 2>&1 || true

# --- 8) Iniciar sshd en foreground para mantener el contenedor vivo ---
echo "[entrypoint] Iniciando OpenSSH en foreground..."
exec /usr/sbin/sshd -D -e
