#!/bin/bash
# Script de inicializacion (ejecutado una sola vez por supervisord con autorestart=false)
set -e

MYSQL_DATADIR="/var/lib/mysql"
INIT_FLAG="${MYSQL_DATADIR}/.ctf2_initialized"
FLAG_PATH="/home/admin/flag.txt"

echo "[init] Preparando host keys SSH..."
ssh-keygen -A >/dev/null 2>&1 || true

if [ ! -d "${MYSQL_DATADIR}/mysql" ]; then
    echo "[init] Inicializando datadir MariaDB..."
    mariadb-install-db --user=mysql --datadir="${MYSQL_DATADIR}" >/dev/null
fi
chown -R mysql:mysql "${MYSQL_DATADIR}"

mkdir -p /run/mysqld
chown -R mysql:mysql /run/mysqld

if [ ! -f "${INIT_FLAG}" ]; then
    echo "[init] Arrancando MariaDB temporalmente para aplicar init.sql..."
    /usr/sbin/mariadbd --user=mysql --skip-networking &
    MYSQL_PID=$!

    for i in $(seq 1 60); do
        if mysqladmin ping --silent 2>/dev/null; then
            break
        fi
        sleep 1
    done

    echo "[init] Aplicando /opt/init.sql..."
    mysql < /opt/init.sql
    touch "${INIT_FLAG}"

    echo "[init] Deteniendo MariaDB temporal..."
    mysqladmin shutdown
    wait "${MYSQL_PID}" 2>/dev/null || true
fi

if [ ! -f "${FLAG_PATH}" ]; then
    echo "FLAG{UCC_UWS_Pwned}" > "${FLAG_PATH}"
    chown admin:admin "${FLAG_PATH}"
    chmod 640 "${FLAG_PATH}"
    echo "[init] Bandera depositada en ${FLAG_PATH}."
fi

echo "[init] Inicializacion completada. Supervisor arrancara mariadb y sshd."
exit 0
