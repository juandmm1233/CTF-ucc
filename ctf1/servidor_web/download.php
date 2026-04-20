<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
ctf_require_login();

$file = isset($_GET['file']) ? $_GET['file'] : '';
$raw  = '';
$err  = '';

if ($file !== '') {
    // VULNERABILIDAD INTENCIONAL (Local File Inclusion / Path Traversal).
    // No se valida la ruta ni se restringe el directorio base. El usuario
    // puede usar ../ o rutas absolutas para leer archivos arbitrarios.
    $path = __DIR__ . '/bucket/' . $file;
    if (@is_file($path)) {
        $raw = @file_get_contents($path);
    } else {
        $err = "No se encontro el archivo: " . $file;
    }
}

ctf_render_header('storage', 'IDS Storage - Descargar archivo');
?>

<div class="gc-card gc-card-flat" style="margin-bottom:24px;">
    <div class="gc-card-subheader">
        <h2 class="gc-h2"><span class="material-icons">cloud_download</span> Descargar objeto del bucket</h2>
        <span class="gc-chip">ids-ucc-backups</span>
    </div>
    <p style="color:#5f6368;margin:0 0 16px 0;">
        Selecciona un objeto del bucket para previsualizar su contenido. Tambien puedes
        escribir manualmente el nombre del archivo.
    </p>
    <form method="GET" action="download.php" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div style="flex:1;min-width:260px;">
            <label class="gc-label" for="file">Ruta dentro del bucket</label>
            <input class="gc-input" id="file" name="file" type="text" placeholder="welcome.txt" value="<?php echo htmlspecialchars($file); ?>" />
        </div>
        <button type="submit" class="gc-button"><span class="material-icons">visibility</span> Ver</button>
    </form>
    <div style="margin-top:12px;display:flex;gap:8px;flex-wrap:wrap;">
        <a class="gc-chip" href="download.php?file=welcome.txt">welcome.txt</a>
        <a class="gc-chip" href="download.php?file=robots.txt">robots.txt</a>
        <a class="gc-chip" href="download.php?file=admin_notes.md">admin_notes.md</a>
        <a class="gc-chip" href="download.php?file=changelog.txt">changelog.txt</a>
    </div>
</div>

<?php if ($err !== ''): ?>
    <div class="gc-alert" style="background:#fce8e6;border-color:#f4b4ae;color:#b3261e;">
        <span class="material-icons">error</span>
        <div><?php echo htmlspecialchars($err); ?></div>
    </div>
<?php elseif ($raw !== ''): ?>
    <div class="gc-card gc-card-flat">
        <div class="gc-card-subheader">
            <h2 class="gc-h2"><span class="material-icons">description</span> <?php echo htmlspecialchars($file); ?></h2>
        </div>
        <pre class="gc-code" style="max-height:480px;overflow:auto;"><?php echo htmlspecialchars($raw); ?></pre>
    </div>
<?php endif; ?>

<?php ctf_render_footer(); ?>
