<?php
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
