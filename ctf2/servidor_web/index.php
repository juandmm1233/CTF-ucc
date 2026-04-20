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
        // VULNERABILIDAD INTENCIONAL (SQLi) - concatenacion directa.
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
    <title>Iniciar sesion - UCC Web Services</title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&family=Roboto+Mono&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <link rel="stylesheet" href="assets/style.css" />
</head>
<body class="uws-login-page">
    <header class="uws-topbar uws-topbar-login">
        <div class="uws-topbar-left">
            <a href="index.php" class="uws-logo">
                <span class="material-icons uws-logo-icon">cloud_queue</span>
                <span class="uws-logo-text">UWS</span>
                <span class="uws-logo-sep">|</span>
                <span class="uws-logo-sub">UCC Web Services</span>
            </a>
        </div>
        <div class="uws-topbar-right">
            <span class="uws-region">ibague-1</span>
        </div>
    </header>

    <main class="uws-login-container">
        <section class="uws-login-card">
            <h1 class="uws-login-title">Iniciar sesion</h1>
            <div class="uws-login-tabs">
                <div class="uws-login-tab active">
                    <span class="material-icons">key</span>
                    <div>
                        <strong>Administrador</strong>
                        <small>Propietario de la cuenta con acceso sin restricciones a los recursos UWS.</small>
                    </div>
                </div>
                <div class="uws-login-tab">
                    <span class="material-icons">person</span>
                    <div>
                        <strong>Usuario estandar</strong>
                        <small>Usuario dentro de una cuenta que realiza tareas operativas diarias.</small>
                    </div>
                </div>
            </div>

            <?php if (!empty($error_msg)): ?>
                <div class="uws-alert uws-alert-error" role="alert">
                    <span class="material-icons">error</span>
                    <div><?php echo htmlspecialchars($error_msg); ?></div>
                </div>
            <?php endif; ?>
            <?php if (!empty($db_error)): ?>
                <div class="uws-alert uws-alert-error" role="alert">
                    <span class="material-icons">cloud_off</span>
                    <div><?php echo htmlspecialchars($db_error); ?></div>
                </div>
            <?php endif; ?>

            <form method="POST" action="index.php" class="uws-form" autocomplete="off">
                <label class="uws-field">
                    <span class="uws-field-label">Correo electronico o nombre de usuario</span>
                    <input class="uws-input" type="text" name="username" required />
                </label>
                <label class="uws-field">
                    <span class="uws-field-label">Contrasena</span>
                    <input class="uws-input" type="password" name="password" required />
                </label>
                <button class="uws-button uws-button-primary" type="submit">Iniciar sesion</button>
                <div class="uws-login-links">
                    <a href="#" class="uws-link">Olvidaste tu contrasena?</a>
                    <a href="#" class="uws-link">Crear una cuenta UWS nueva</a>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
