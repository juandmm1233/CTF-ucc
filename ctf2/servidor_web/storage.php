<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
ctf_require_login();
ctf_render_header('storage', 'UWS Storage - Buckets');
?>

<div class="uws-toolbar">
    <button class="uws-button uws-button-primary"><span class="material-icons">add</span> Crear bucket</button>
    <button class="uws-button"><span class="material-icons">upload</span> Subir archivo</button>
    <button class="uws-button"><span class="material-icons">refresh</span> Refrescar</button>
</div>

<div class="uws-card">
    <div class="uws-card-subheader">
        <h2 class="uws-h2"><span class="material-icons">folder_open</span> Bucket: uws-ctf-backups</h2>
        <span class="uws-chip uws-chip-warn">Publico</span>
    </div>
    <table class="uws-table">
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
                <td><span class="material-icons" style="color:#687078;">description</span> welcome.txt</td>
                <td>text/plain</td>
                <td>312 B</td>
                <td>hace 2 minutos</td>
                <td><span class="uws-chip uws-chip-ok">Lectura publica</span></td>
            </tr>
            <tr>
                <td><span class="material-icons" style="color:#687078;">description</span> db_backup_2026_04_19.sql.gz</td>
                <td>application/gzip</td>
                <td>4.2 MB</td>
                <td>hace 3 horas</td>
                <td><span class="uws-chip uws-chip-warn">Lectura publica</span></td>
            </tr>
            <tr>
                <td><span class="material-icons" style="color:#687078;">description</span> robots.txt</td>
                <td>text/plain</td>
                <td>164 B</td>
                <td>hace 1 dia</td>
                <td><span class="uws-chip uws-chip-ok">Lectura publica</span></td>
            </tr>
            <tr>
                <td><span class="material-icons" style="color:#687078;">lock</span> admin_notes.md</td>
                <td>text/markdown</td>
                <td>--</td>
                <td>--</td>
                <td><span class="uws-chip uws-chip-err">Restringido</span></td>
            </tr>
        </tbody>
    </table>
</div>

<div class="uws-card" style="margin-top:24px;">
    <div class="uws-card-subheader">
        <h2 class="uws-h2"><span class="material-icons">article</span> welcome.txt</h2>
    </div>
    <pre class="uws-code"># UWS - bucket de respaldos

Este bucket contiene los respaldos operativos.
Si tu rol IAM es 'admin' puedes acceder a la consola
de administracion interna. De lo contrario habla con
el equipo de seguridad.

No olvides revisar /robots.txt para evitar exponer rutas sensibles.</pre>
</div>

<?php ctf_render_footer(); ?>
