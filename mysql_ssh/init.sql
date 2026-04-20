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
    ('api_key_prod', 'AIzaSy-FAKE-KEY-DO-NOT-USE-0001'),
    ('ssh_hint',     'Usuario: admin | Password: password123'),
    ('flag_hint',    'La bandera real esta en /home/admin/flag.txt');

-- Usuario MySQL utilizado por el servicio web_app para conectarse
CREATE USER IF NOT EXISTS 'ctf_web'@'%' IDENTIFIED BY 'ctf_web_pass';
GRANT SELECT ON ctf_login.* TO 'ctf_web'@'%';
FLUSH PRIVILEGES;
