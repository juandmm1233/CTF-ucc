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
        $msg_cls = 'uws-alert-warn';
    } else {
        $f = $_FILES['file'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $msg = 'Error al recibir el archivo (codigo ' . (int)$f['error'] . ').';
            $msg_cls = 'uws-alert-warn';
        } else {
            // VULNERABILIDAD INTENCIONAL (Upload sin validacion).
            $dest_dir = __DIR__ . '/uploads';
            if (!is_dir($dest_dir)) { @mkdir($dest_dir, 0775, true); }
            $name = basename($f['name']);
            $dest = $dest_dir . '/' . $name;
            if (@move_uploaded_file($f['tmp_name'], $dest)) {
                $saved   = 'uploads/' . $name;
                $msg     = 'Archivo subido como ' . $saved;
                $msg_cls = 'uws-alert-success';
            } else {
                $msg     = 'No se pudo guardar el archivo en el servidor.';
                $msg_cls = 'uws-alert-warn';
            }
        }
    }
}

ctf_render_header('storage', 'UWS Storage - Subir archivo');
?>

<div class="uws-card" style="margin-bottom:24px;">
    <div class="uws-card-subheader">
        <h2 class="uws-h2"><span class="material-icons">cloud_upload</span> Subir archivo al bucket</h2>
        <span class="uws-chip">uws-ctf-backups</span>
    </div>
    <p style="color:#5f6368;margin:0 0 16px 0;">
        Sube un objeto al bucket <code>uws-ctf-backups</code>. Operadores autorizados
        pueden subir respaldos comprimidos, scripts auxiliares y notas internas.
    </p>
    <form method="POST" action="upload.php" enctype="multipart/form-data" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div style="flex:1;min-width:280px;">
            <label class="uws-label" for="file">Archivo</label>
            <input class="uws-input" id="file" name="file" type="file" required />
        </div>
        <button type="submit" class="uws-button uws-button-primary"><span class="material-icons">upload</span> Subir</button>
    </form>
</div>

<?php if ($msg !== ''): ?>
    <div class="uws-alert <?php echo htmlspecialchars($msg_cls); ?>" style="margin-bottom:16px;">
        <span class="material-icons"><?php echo $msg_cls === 'uws-alert-success' ? 'check_circle' : 'warning'; ?></span>
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

<div class="uws-card">
    <div class="uws-card-subheader">
        <h2 class="uws-h2"><span class="material-icons">folder</span> Objetos cargados recientemente</h2>
    </div>
    <?php
    $files = @scandir(__DIR__ . '/uploads');
    $listed = [];
    if (is_array($files)) {
        foreach ($files as $f) {
            if ($f === '.' || $f === '..' || $f === '.keep') continue;
            $listed[] = $f;
        }
    }
    ?>
    <?php if (count($listed) === 0): ?>
        <p style="color:#5f6368;">Aun no se han subido archivos.</p>
    <?php else: ?>
        <ul class="uws-list">
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
