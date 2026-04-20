<?php
require_once __DIR__ . '/auth.php';

function ctf_render_header($active = 'dashboard', $page_title = 'Console Home') {
    $user = ctf_current_user();
    $initial = strtoupper(substr($user, 0, 1));
    $nav = [
        'dashboard' => ['label' => 'Console Home',  'icon' => 'home',          'href' => 'dashboard.php'],
        'compute'   => ['label' => 'Compute',        'icon' => 'memory',        'href' => 'compute.php'],
        'storage'   => ['label' => 'Storage',        'icon' => 'storage',       'href' => 'storage.php'],
        'network'   => ['label' => 'Network Tools',  'icon' => 'network_check', 'href' => 'network.php'],
        'iam'       => ['label' => 'IAM',            'icon' => 'group',         'href' => 'iam.php'],
        'logs'      => ['label' => 'Observability',  'icon' => 'query_stats',   'href' => 'logs.php'],
    ];
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>UCC Web Services - <?php echo htmlspecialchars($page_title); ?></title>
    <link rel="icon" type="image/svg+xml" href="assets/favicon.svg" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&family=Roboto+Mono&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <link rel="stylesheet" href="assets/style.css" />
</head>
<body class="uws-app">
    <header class="uws-topbar">
        <div class="uws-topbar-left">
            <a href="dashboard.php" class="uws-logo" title="UCC Web Services">
                <span class="material-icons uws-logo-icon">cloud_queue</span>
                <span class="uws-logo-text">UWS</span>
                <span class="uws-logo-sep">|</span>
                <span class="uws-logo-sub">UCC Web Services</span>
            </a>
            <nav class="uws-topbar-menu">
                <a href="dashboard.php" class="uws-topbar-link">Servicios</a>
                <div class="uws-search uws-search-top">
                    <span class="material-icons">search</span>
                    <input type="text" placeholder="Buscar servicios, recursos y documentacion..." />
                </div>
            </nav>
        </div>
        <div class="uws-topbar-right">
            <span class="material-icons uws-topbar-icon" title="Terminal">terminal</span>
            <span class="material-icons uws-topbar-icon" title="Notificaciones">notifications_none</span>
            <span class="material-icons uws-topbar-icon" title="Soporte">help_outline</span>
            <span class="uws-region">ibague-1 <span class="material-icons">expand_more</span></span>
            <?php $__role = ctf_current_role(); ?>
            <?php if ($__role === 'admin'): ?>
                <span class="uws-chip uws-chip-warn" title="Rol activo">admin</span>
            <?php elseif ($__role === 'observador' || $__role === 'observer'): ?>
                <span class="uws-chip uws-chip-ok" title="Rol activo">observador</span>
            <?php else: ?>
                <span class="uws-chip" title="Rol activo"><?php echo htmlspecialchars($__role); ?></span>
            <?php endif; ?>
            <a href="logout.php" class="uws-user" title="Cerrar sesion (<?php echo htmlspecialchars($user); ?>)">
                <?php echo htmlspecialchars($user); ?>@uws-lab
                <span class="material-icons">expand_more</span>
            </a>
        </div>
    </header>

    <div class="uws-subbar">
        <div class="uws-subbar-inner">
            <span class="uws-subbar-pill">Visitados recientemente</span>
            <span class="uws-subbar-sep"></span>
            <a href="compute.php" class="uws-subbar-link">Compute</a>
            <a href="storage.php" class="uws-subbar-link">Storage</a>
            <a href="iam.php" class="uws-subbar-link">IAM</a>
            <a href="logs.php" class="uws-subbar-link">Observability</a>
        </div>
    </div>

    <div class="uws-shell">
        <aside class="uws-sidebar">
            <div class="uws-sidebar-header">
                <span class="material-icons">grid_view</span>
                <span>Servicios</span>
            </div>
            <nav>
                <ul class="uws-nav">
                    <?php foreach ($nav as $key => $item):
                        $is_active = ($key === $active) ? ' active' : ''; ?>
                        <li>
                            <a class="uws-nav-item<?php echo $is_active; ?>" href="<?php echo $item['href']; ?>">
                                <span class="material-icons"><?php echo $item['icon']; ?></span>
                                <span><?php echo htmlspecialchars($item['label']); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="uws-nav-divider"></div>
                <ul class="uws-nav">
                    <li>
                        <a class="uws-nav-item" href="#" title="Solo visible con permisos elevados">
                            <span class="material-icons">admin_panel_settings</span>
                            <span>Cuenta Administrador</span>
                            <span class="uws-badge">LOCK</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="uws-main">
            <div class="uws-breadcrumb">
                <a href="dashboard.php">UWS</a>
                <span class="material-icons">chevron_right</span>
                <span><?php echo htmlspecialchars($page_title); ?></span>
            </div>
            <h1 class="uws-page-title"><?php echo htmlspecialchars($page_title); ?></h1>
            <?php if (ctf_is_observer()): ?>
                <div class="uws-alert" style="background:#eaf3ff;border-color:#c2d7fb;color:#0a3b66;margin-bottom:16px;">
                    <span class="material-icons">visibility</span>
                    <div>
                        <strong>Modo observador activo.</strong>
                        Tienes permisos de solo lectura en la consola UWS y en MariaDB (<code>SELECT</code>).
                        Las acciones de escritura estan deshabilitadas.
                    </div>
                </div>
            <?php endif; ?>
    <?php
}

function ctf_render_footer() {
    ?>
        </main>
    </div>
    <footer class="uws-footer">
        <div>&copy; 2026 UCC Web Services - Laboratorio CTF Universidad Cooperativa de Colombia</div>
        <div class="uws-footer-links">
            <a href="#">Privacidad</a>
            <a href="#">Terminos</a>
            <a href="#">Preferencias de cookies</a>
        </div>
    </footer>
</body>
</html>
    <?php
}
