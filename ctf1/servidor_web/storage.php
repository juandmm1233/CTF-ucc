<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
ctf_require_login();
ctf_render_header('storage', 'IDS Storage');

$bucket_dir = __DIR__ . '/bucket';
$uploads_dir = __DIR__ . '/uploads';

function ids_human_size($bytes) {
    if ($bytes < 1024) return $bytes . ' B';
    if ($bytes < 1024 * 1024) return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1024 / 1024, 1) . ' MB';
}
?>

<div class="gc-toolbar">
    <a class="gc-button" href="upload.php"><span class="material-icons">upload</span> Subir archivo</a>
    <a class="gc-button gc-button-ghost" href="download.php"><span class="material-icons">cloud_download</span> Descargar / previsualizar</a>
</div>

<div class="gc-card gc-card-flat">
    <div class="gc-card-subheader">
        <h2 class="gc-h2"><span class="material-icons">folder_open</span> Bucket: ids-ucc-backups</h2>
        <span class="gc-chip gc-chip-warn">PUBLIC</span>
    </div>
    <table class="gc-table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Tamano</th>
                <th>Permisos</th>
                <th>Accion</th>
            </tr>
        </thead>
        <tbody>
        <?php
        $files = @scandir($bucket_dir) ?: [];
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            $path = $bucket_dir . '/' . $f;
            $size = is_file($path) ? ids_human_size(filesize($path)) : '--';
            $ext  = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            $type = $ext === 'md' ? 'text/markdown' : ($ext === 'txt' ? 'text/plain' : 'application/octet-stream');
            $is_admin_file = (strpos($f, 'admin') !== false);
            $perm = $is_admin_file
                ? '<span class="gc-chip gc-chip-err">restricted</span>'
                : '<span class="gc-chip gc-chip-ok">allUsers: reader</span>';
            echo '<tr>';
            echo '<td><span class="material-icons" style="color:#5f6368;">description</span> ' . htmlspecialchars($f) . '</td>';
            echo '<td>' . htmlspecialchars($type) . '</td>';
            echo '<td>' . htmlspecialchars($size) . '</td>';
            echo '<td>' . $perm . '</td>';
            echo '<td><a class="gc-link" href="download.php?file=' . rawurlencode($f) . '">Ver</a></td>';
            echo '</tr>';
        }
        ?>
        </tbody>
    </table>
</div>

<div class="gc-card gc-card-flat" style="margin-top:24px;">
    <div class="gc-card-subheader">
        <h2 class="gc-h2"><span class="material-icons">cloud_upload</span> Objetos cargados por operadores</h2>
        <a class="gc-link" href="upload.php">Subir nuevo</a>
    </div>
    <?php
    $uploaded = @scandir($uploads_dir) ?: [];
    $listed = [];
    foreach ($uploaded as $f) {
        if ($f === '.' || $f === '..' || $f === '.keep') continue;
        $listed[] = $f;
    }
    ?>
    <?php if (count($listed) === 0): ?>
        <p style="color:#5f6368;margin:0;">Aun no se han subido archivos.</p>
    <?php else: ?>
        <ul class="gc-list">
            <?php foreach ($listed as $f): ?>
                <li>
                    <a href="uploads/<?php echo rawurlencode($f); ?>">
                        <span class="material-icons">description</span>
                        <?php echo htmlspecialchars($f); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</div>

<?php ctf_render_footer(); ?>
