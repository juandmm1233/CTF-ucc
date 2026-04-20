-- Inicializacion de la base de datos del CTF
CREATE DATABASE IF NOT EXISTS ctf_login
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE ctf_login;

-- Tabla de usuarios utilizada por el login vulnerable del servidor web
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(64) NOT NULL UNIQUE,
    password VARCHAR(128) NOT NULL,
    role VARCHAR(32) DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

INSERT INTO users (username, password, role) VALUES
    ('root',    'S3cur3R00tP4ss!',   'admin'),
    ('admin',   'password123',        'admin'),
    ('jdoe',    'qwerty2024',         'user'),
    ('guest',   'guest',              'user');

-- Tabla senuelo para practicar enumeracion via SQLi (UNION-based)
CREATE TABLE IF NOT EXISTS secrets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    label VARCHAR(64) NOT NULL,
    value VARCHAR(255) NOT NULL
);

INSERT INTO secrets (label, value) VALUES
    ('api_key_prod',     'AIzaSy-FAKE-KEY-DO-NOT-USE-0001'),
    ('ssh_hint',         'Usuario: admin | Password: password123'),
    ('flag_hint',        'La bandera real esta en /home/admin/flag.txt'),
    -- Hashes MD5 dejados por un dev "para pruebas". Romperlos con john/hashcat
    -- usando rockyou.txt revela la bandera de hash cracking.
    ('legacy_md5_dev',   '5f4dcc3b5aa765d61d8327deb882cf99'), -- "password"
    ('legacy_md5_op',    '21232f297a57a5a743894a0e4a801fc3'), -- "admin"
    ('legacy_md5_qa',    'e10adc3949ba59abbe56e057f20f883e'), -- "123456"
    ('hash_flag_hint',   'Cuando descifres los 3 hashes MD5, somete: FLAG{UCC_IDS_Hash_Cracked}'),
    -- Honeypot: bandera trampa que NO debe enviarse al scoreboard.
    ('honeypot_alert',   'FLAG{HONEYPOT_DO_NOT_SUBMIT_01}');

-- Usuario MySQL utilizado por el servicio web_app para el login (solo lectura)
CREATE USER IF NOT EXISTS 'ctf_web'@'%' IDENTIFIED BY 'ctf_web_pass';
GRANT SELECT ON ctf_login.* TO 'ctf_web'@'%';

-- Usuario MySQL con privilegios elevados. Se usa desde /admin.php para crear
-- nuevos usuarios de la aplicacion y sus contrapartes en MariaDB. Intencional-
-- mente expuesto al servicio web para el ejercicio Red Team / Blue Team.
CREATE USER IF NOT EXISTS 'ctf_admin'@'%' IDENTIFIED BY 'ctf_admin_pass';
GRANT ALL PRIVILEGES ON ctf_login.* TO 'ctf_admin'@'%' WITH GRANT OPTION;
GRANT CREATE USER ON *.* TO 'ctf_admin'@'%';
GRANT RELOAD ON *.* TO 'ctf_admin'@'%';

FLUSH PRIVILEGES;
