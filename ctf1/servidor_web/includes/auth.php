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

/**
 * Devuelve el rol actual de la sesion: 'admin', 'observador' o 'user'.
 * Se puebla durante el login exitoso en index.php.
 */
function ctf_current_role() {
    return isset($_SESSION['ctf_role']) ? $_SESSION['ctf_role'] : 'user';
}

function ctf_is_admin() {
    return ctf_current_role() === 'admin';
}

function ctf_is_observer() {
    $role = ctf_current_role();
    return $role === 'observador' || $role === 'observer';
}

/**
 * Bloquea endpoints de escritura para observadores y visitantes.
 */
function ctf_require_admin() {
    if (!ctf_is_admin()) {
        http_response_code(403);
        echo '<!DOCTYPE html><html><body style="font-family:sans-serif;padding:40px;">';
        echo '<h1>403 - Permisos insuficientes</h1>';
        echo '<p>Esta accion requiere rol <code>admin</code>. Tu rol actual es <code>';
        echo htmlspecialchars(ctf_current_role());
        echo '</code>.</p>';
        echo '<p><a href="dashboard.php">Volver al dashboard</a></p>';
        echo '</body></html>';
        exit;
    }
}
