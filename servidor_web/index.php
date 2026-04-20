<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';

if (!empty($_SESSION['ctf_user'])) {
    header('Location: dashboard.php');
    exit;
}

$error_msg = '';
$db_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = isset($_POST['username']) ? $_POST['username'] : '';
    $pass = isset($_POST['password']) ? $_POST['password'] : '';

    $mysqli = ctf_db_connect();

    if ($mysqli === null) {
        $db_error = 'No se pudo conectar al backend MariaDB.';
    } else {
        // VULNERABILIDAD INTENCIONAL (SQLi) - concatenacion directa de entrada del usuario.
        // NO usar este patron en produccion. Solo con fines educativos.
        $query = "SELECT id, username, role FROM users WHERE username = '" . $user . "' AND password = '" . $pass . "'";

        $result = @$mysqli->query($query);

        if ($result === false) {
            $error_msg = 'Error en la consulta: ' . $mysqli->error;
        } else {
            if ($result->num_rows > 0) {
                $row = $result->fetch_assoc();
                $_SESSION['ctf_user'] = $row['username'];
                $_SESSION['ctf_role'] = isset($row['role']) ? $row['role'] : 'user';
                $_SESSION['ctf_logged_at'] = date('c');
                $_SESSION['ctf_bypass'] = true;
                $mysqli->close();
                header('Location: dashboard.php');
                exit;
            } else {
                $error_msg = 'Credenciales invalidas.';
            }
            $result->close();
        }
        $mysqli->close();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Ibague Data Services - Iniciar sesion</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Roboto:wght@400;500&family=Roboto+Mono&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <link rel="stylesheet" href="assets/style.css" />
</head>
<body>
    <header class="gc-appbar">
        <div class="gc-appbar-inner">
            <div class="gc-appbar-brand">
                <span class="material-icons gc-appbar-logo">cloud</span>
                <span class="gc-appbar-title">Ibague Data Services</span>
                <span class="gc-appbar-subtitle">IDS</span>
            </div>
            <div class="gc-appbar-actions">
                <span class="material-icons gc-appbar-icon" title="Ayuda">help_outline</span>
                <span class="material-icons gc-appbar-icon" title="Notificaciones">notifications_none</span>
                <div class="gc-avatar" title="Invitado">G</div>
            </div>
        </div>
    </header>

    <main class="gc-container">
        <section class="gc-card">
            <div class="gc-card-header">
                <span class="material-icons gc-card-icon">lock</span>
                <div>
                    <h1 class="gc-title">Inicia sesion</h1>
                    <p class="gc-subtitle">Accede a tu consola IDS para administrar recursos.</p>
                </div>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div class="gc-alert gc-alert-error" role="alert">
                    <span class="material-icons">error_outline</span>
                    <div><?php echo htmlspecialchars($error_msg); ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($db_error)): ?>
                <div class="gc-alert gc-alert-error" role="alert">
                    <span class="material-icons">cloud_off</span>
                    <div><?php echo htmlspecialchars($db_error); ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="index.php" class="gc-form" autocomplete="off">
                <label class="gc-field">
                    <span class="gc-field-label">Usuario</span>
                    <input class="gc-input" type="text" name="username" required />
                </label>
                <label class="gc-field">
                    <span class="gc-field-label">Contrasena</span>
                    <input class="gc-input" type="password" name="password" required />
                </label>
                <div class="gc-actions">
                    <a href="#" class="gc-link">Crear cuenta</a>
                    <button class="gc-button" type="submit">Siguiente</button>
                </div>
            </form>
        </section>

        <footer class="gc-footer">
            <span>Espanol</span>
            <span class="gc-footer-sep">|</span>
            <a href="#" class="gc-footer-link">Ayuda</a>
            <a href="#" class="gc-footer-link">Privacidad</a>
            <a href="#" class="gc-footer-link">Terminos</a>
        </footer>
    </main>
</body>
</html>
