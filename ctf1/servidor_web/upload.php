<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
ctf_require_login();

$msg     = '';
$msg_cls = '';
$saved   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    if (ctf_is_observer()) {
        $msg = 'El rol observador no puede subir archivos.';
        $msg_cls = 'gc-alert-warn';
    } else {
        $f = $_FILES['file'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $msg = 'Error al recibir el archivo (codigo ' . (int)$f['error'] . ').';
            $msg_cls = 'gc-alert-warn';
        } else {
            // VULNERABILIDAD INTENCIONAL (Upload sin validacion).
            // No se valida la extension, el MIME ni el contenido. Cualquier .php que
            // un usuario suba quedara accesible y sera ejecutado por Apache.
            $dest_dir = __DIR__ . '/uploads';
            if (!is_dir($dest_dir)) { @mkdir($dest_dir, 0775, true); }
            $name = basename($f['name']);
            $dest = $dest_dir . '/' . $name;
            if (@move_uploaded_file($f['tmp_name'], $dest)) {
                $saved   = 'uploads/' . $name;
                $msg     = 'Archivo subido como ' . $saved;
                $msg_cls = 'gc-alert-success';
            } else {
                $msg     = 'No se pudo guardar el archivo en el servidor.';
                $msg_cls = 'gc-alert-warn';
            }
        }
    }
}

ctf_render_header('storage', 'IDS Storage - Subir archivo');
?>

<div class="gc-card gc-card-flat" style="margin-bottom:24px;">
    <div class="gc-card-subheader">
        <h2 class="gc-h2"><span class="material-icons">cloud_upload</span> Subir archivo al bucket</h2>
        <span class="gc-chip">ids-ucc-backups</span>
    </div>
    <p style="color:#5f6368;margin:0 0 16px 0;">
        Sube un objeto al bucket <code>ids-ucc-backups</code>. Operadores autorizados
        pueden subir respaldos comprimidos, scripts auxiliares y notas internas.
    </p>
    <form method="POST" action="upload.php" enctype="multipart/form-data" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div style="flex:1;min-width:280px;">
            <label class="gc-label" for="file">Archivo</label>
            <input class="gc-input" id="file" name="file" type="file" required />
        </div>
        <button type="submit" class="gc-button"><span class="material-icons">upload</span> Subir</button>
    </form>
</div>

<?php if ($msg !== ''): ?>
    <div class="gc-alert <?php echo htmlspecialchars($msg_cls); ?>" style="margin-bottom:16px;">
        <span class="material-icons"><?php echo $msg_cls === 'gc-alert-success' ? 'check_circle' : 'warning'; ?></span>
        <div>
            <?php echo htmlspecialchars($msg); ?>
            <?php if ($saved !== ''): ?>
                <div style="margin-top:6px;">
                    URL: <a href="<?php echo htmlspecialchars($saved); ?>"><?php echo htmlspecialchars($saved); ?></a>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<div class="gc-card gc-card-flat">
    <div class="gc-card-subheader">
        <h2 class="gc-h2"><span class="material-icons">folder</span> Objetos cargados recientemente</h2>
    </div>
    <?php
    $files = @scandir(__DIR__ . '/uploads');
    $listed = [];
    if (is_array($files)) {
        foreach ($files as $f) {
            if ($f === '.' || $f === '..') continue;
            $listed[] = $f;
        }
    }
    ?>
    <?php if (count($listed) === 0): ?>
        <p style="color:#5f6368;">Aun no se han subido archivos.</p>
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
