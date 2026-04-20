<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
ctf_require_login();
ctf_render_header('storage', 'Cloud Storage');
?>

<div class="gc-toolbar">
    <button class="gc-button"><span class="material-icons">add</span> Crear bucket</button>
    <button class="gc-button gc-button-ghost"><span class="material-icons">upload</span> Subir archivo</button>
</div>

<div class="gc-card gc-card-flat">
    <div class="gc-card-subheader">
        <h2 class="gc-h2"><span class="material-icons">folder_open</span> Bucket: ctf-ucc-backups</h2>
        <span class="gc-chip gc-chip-warn">PUBLIC</span>
    </div>
    <table class="gc-table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Tipo</th>
                <th>Tamano</th>
                <th>Ultima modificacion</th>
                <th>Permisos</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><span class="material-icons" style="color:#5f6368;">description</span> welcome.txt</td>
                <td>text/plain</td>
                <td>312 B</td>
                <td>hace 2 minutos</td>
                <td><span class="gc-chip gc-chip-ok">allUsers: reader</span></td>
            </tr>
            <tr>
                <td><span class="material-icons" style="color:#5f6368;">description</span> db_backup_2026_04_19.sql.gz</td>
                <td>application/gzip</td>
                <td>4.2 MB</td>
                <td>hace 3 horas</td>
                <td><span class="gc-chip gc-chip-warn">allUsers: reader</span></td>
            </tr>
            <tr>
                <td><span class="material-icons" style="color:#5f6368;">description</span> robots.txt</td>
                <td>text/plain</td>
                <td>164 B</td>
                <td>hace 1 dia</td>
                <td><span class="gc-chip gc-chip-ok">allUsers: reader</span></td>
            </tr>
            <tr>
                <td><span class="material-icons" style="color:#5f6368;">lock</span> admin_notes.md</td>
                <td>text/markdown</td>
                <td>--</td>
                <td>--</td>
                <td><span class="gc-chip gc-chip-err">restricted</span></td>
            </tr>
        </tbody>
    </table>
</div>

<div class="gc-card gc-card-flat" style="margin-top:24px;">
    <div class="gc-card-subheader">
        <h2 class="gc-h2"><span class="material-icons">article</span> welcome.txt</h2>
    </div>
    <pre class="gc-code"># CTF Cloud - Bucket de backups

Este bucket contiene respaldos operativos.
Si tu rol IAM es 'admin', podras acceder al panel de
administracion interno. De lo contrario, habla con el
equipo de seguridad.

No olvides revisar /robots.txt para evitar exponer rutas sensibles.</pre>
</div>

<?php ctf_render_footer(); ?>
