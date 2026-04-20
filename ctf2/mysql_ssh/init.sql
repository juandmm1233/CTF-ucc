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
    ('api_access_key',   'UWSK-FAKE-KEY-DO-NOT-USE-000X'),
    ('api_secret_hint',  'stored in UWS Parameter Store'),
    ('ssh_hint',         'Usuario: admin | Password: password123'),
    ('flag_hint',        'La bandera real esta en /home/admin/flag.txt'),
    -- Hashes MD5 dejados por un dev "para pruebas". Romperlos con john/hashcat
    -- usando rockyou.txt revela la bandera de hash cracking.
    ('legacy_md5_dev',   '482c811da5d5b4bc6d497ffa98491e38'), -- "password123"
    ('legacy_md5_op',    'd8578edf8458ce06fbc5bb76a58c5ca4'), -- "qwerty"
    ('legacy_md5_qa',    'fcea920f7412b5da7be0cf42b8c93759'), -- "1234567"
    ('hash_flag_hint',   'Cuando descifres los 3 hashes MD5, somete: FLAG{UCC_UWS_Hash_Cracked}'),
    -- Honeypot: bandera trampa que NO debe enviarse al scoreboard.
    ('honeypot_alert',   'FLAG{HONEYPOT_TRAP_SOC_02}');

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
