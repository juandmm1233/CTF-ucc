<?php
/**
 * Conexion estandar usada por web_app (solo SELECT).
 */
function ctf_db_connect() {
    $db_host = getenv('DB_HOST') ?: 'db_ssh';
    $db_port = getenv('DB_PORT') ?: '3306';
    $db_user = getenv('DB_USER') ?: 'ctf_web';
    $db_pass = getenv('DB_PASS') ?: 'ctf_web_pass';
    $db_name = getenv('DB_NAME') ?: 'ctf_login';

    $mysqli = @new mysqli($db_host, $db_user, $db_pass, $db_name, (int)$db_port);
    if ($mysqli->connect_errno) {
        return null;
    }
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}

/**
 * Conexion privilegiada usada por /admin.php para provisionar nuevos
 * usuarios (PHP + MariaDB). Tiene ALL PRIVILEGES sobre ctf_login y
 * CREATE USER global. No exponer en endpoints publicos.
 */
function ctf_db_connect_admin() {
    $db_host  = getenv('DB_HOST')       ?: 'db_ssh';
    $db_port  = getenv('DB_PORT')       ?: '3306';
    $db_admin = getenv('DB_ADMIN_USER') ?: 'ctf_admin';
    $db_apass = getenv('DB_ADMIN_PASS') ?: 'ctf_admin_pass';
    $db_name  = getenv('DB_NAME')       ?: 'ctf_login';

    $mysqli = @new mysqli($db_host, $db_admin, $db_apass, $db_name, (int)$db_port);
    if ($mysqli->connect_errno) {
        return null;
    }
    $mysqli->set_charset('utf8mb4');
    return $mysqli;
}
