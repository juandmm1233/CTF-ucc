-- Inicializacion de la base de datos del CTF2 (UWS edition - UCC Web Services)
CREATE DATABASE IF NOT EXISTS ctf_login
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE ctf_login;

CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    password VARCHAR(128) NOT NULL,
    role VARCHAR(32) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (username, password, role) VALUES
    ('root',        'R00tUwsP4ss!',        'admin'),
    ('admin',       'password123',         'admin'),
    ('computeuser', 'compute2024',         'user'),
    ('guest',       'guest',               'user');

CREATE TABLE IF NOT EXISTS secrets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(64) NOT NULL,
    value VARCHAR(255) NOT NULL
);

INSERT INTO secrets (label, value) VALUES
    ('api_access_key',  'UWSK-FAKE-KEY-DO-NOT-USE-000X'),
    ('api_secret_hint', 'stored in UWS Parameter Store'),
    ('ssh_hint',        'Usuario: admin | Password: password123'),
    ('flag_hint',       'La bandera real esta en /home/admin/flag.txt');

-- Usuario MySQL usado por web_app para el login (solo lectura)
CREATE USER IF NOT EXISTS 'ctf_web'@'%' IDENTIFIED BY 'ctf_web_pass';
GRANT SELECT ON ctf_login.* TO 'ctf_web'@'%';

-- Usuario con privilegios elevados usado por /admin.php para provisionar
-- nuevas cuentas (PHP + MariaDB) tras el bypass de cookie.
CREATE USER IF NOT EXISTS 'ctf_admin'@'%' IDENTIFIED BY 'ctf_admin_pass';
GRANT ALL PRIVILEGES ON ctf_login.* TO 'ctf_admin'@'%' WITH GRANT OPTION;
GRANT CREATE USER ON *.* TO 'ctf_admin'@'%';
GRANT RELOAD ON *.* TO 'ctf_admin'@'%';

FLUSH PRIVILEGES;
