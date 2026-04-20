<?php
require_once __DIR__ . '/auth.php';

function ctf_render_header($active = 'dashboard', $page_title = 'Dashboard') {
    $user = ctf_current_user();
    $initial = strtoupper(substr($user, 0, 1));
    $nav = [
        'dashboard' => ['label' => 'Dashboard',       'icon' => 'dashboard',     'href' => 'dashboard.php'],
        'compute'   => ['label' => 'Compute',         'icon' => 'dns',           'href' => 'compute.php'],
        'storage'   => ['label' => 'Storage',         'icon' => 'folder',        'href' => 'storage.php'],
        'iam'       => ['label' => 'IAM & Admin',     'icon' => 'group',         'href' => 'iam.php'],
        'logs'      => ['label' => 'Observability',   'icon' => 'receipt_long',  'href' => 'logs.php'],
    ];
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Ibague Data Services - <?php echo htmlspecialchars($page_title); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Google+Sans:wght@400;500;700&family=Roboto:wght@400;500&family=Roboto+Mono&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet" />
    <link rel="stylesheet" href="assets/style.css" />
</head>
<body class="gc-app">
    <header class="gc-appbar">
        <div class="gc-appbar-inner">
            <div class="gc-appbar-brand">
                <span class="material-icons gc-appbar-logo">cloud</span>
                <span class="gc-appbar-title">Ibague Data Services</span>
                <span class="gc-appbar-subtitle">IDS</span>
                <span class="gc-chip-project">
                    <span class="material-icons gc-chip-icon">folder_special</span>
                    ids-ucc-lab
                </span>
            </div>
            <div class="gc-appbar-actions">
                <div class="gc-search">
                    <span class="material-icons">search</span>
                    <input type="text" placeholder="Buscar recursos, documentos y productos..." />
                </div>
                <span class="material-icons gc-appbar-icon" title="Terminal">terminal</span>
                <span class="material-icons gc-appbar-icon" title="Ayuda">help_outline</span>
                <span class="material-icons gc-appbar-icon" title="Notificaciones">notifications_none</span>
                <?php $__role = ctf_current_role(); ?>
                <?php if ($__role === 'admin'): ?>
                    <span class="gc-chip gc-chip-warn" title="Rol activo">admin</span>
                <?php elseif ($__role === 'observador' || $__role === 'observer'): ?>
                    <span class="gc-chip gc-chip-ok" title="Rol activo">observador</span>
                <?php else: ?>
                    <span class="gc-chip" title="Rol activo"><?php echo htmlspecialchars($__role); ?></span>
                <?php endif; ?>
                <a href="logout.php" class="gc-avatar" title="Cerrar sesion (<?php echo htmlspecialchars($user); ?>)"><?php echo htmlspecialchars($initial); ?></a>
            </div>
        </div>
    </header>

    <div class="gc-shell">
        <aside class="gc-sidebar">
            <nav>
                <ul class="gc-nav">
                    <?php foreach ($nav as $key => $item):
                        $is_active = ($key === $active) ? ' active' : ''; ?>
                        <li>
                            <a class="gc-nav-item<?php echo $is_active; ?>" href="<?php echo $item['href']; ?>">
                                <span class="material-icons"><?php echo $item['icon']; ?></span>
                                <span><?php echo htmlspecialchars($item['label']); ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="gc-nav-divider"></div>
                <ul class="gc-nav">
                    <li>
                        <a class="gc-nav-item" href="#" title="Solo visible con permisos elevados">
                            <span class="material-icons">admin_panel_settings</span>
                            <span>Admin Console</span>
                            <span class="gc-badge">LOCK</span>
                        </a>
                    </li>
                </ul>
            </nav>
        </aside>

        <main class="gc-main">
            <div class="gc-breadcrumb">
                <a href="dashboard.php">IDS</a>
                <span class="material-icons">chevron_right</span>
                <span><?php echo htmlspecialchars($page_title); ?></span>
            </div>
            <h1 class="gc-page-title"><?php echo htmlspecialchars($page_title); ?></h1>
            <?php if (ctf_is_observer()): ?>
                <div class="gc-alert" style="background:#e8f0fe;border-color:#c2d7fb;color:#174ea6;margin-bottom:16px;">
                    <span class="material-icons">visibility</span>
                    <div>
                        <strong>Modo observador activo.</strong>
                        Tienes permisos de solo lectura en la consola y en MariaDB (<code>SELECT</code>).
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
</body>
</html>
    <?php
}
