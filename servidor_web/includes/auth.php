<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function ctf_require_login() {
    if (empty($_SESSION['ctf_user'])) {
        header('Location: index.php');
        exit;
    }
}

function ctf_current_user() {
    return isset($_SESSION['ctf_user']) ? $_SESSION['ctf_user'] : 'guest';
}
