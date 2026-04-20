<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
ctf_require_login();
ctf_render_header('compute', 'IDS Compute');
?>

<div class="gc-toolbar">
    <button class="gc-button"><span class="material-icons">add</span> Crear instancia</button>
    <button class="gc-button gc-button-ghost"><span class="material-icons">refresh</span> Refrescar</button>
</div>

<div class="gc-card gc-card-flat">
    <table class="gc-table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Zona</th>
                <th>Estado</th>
                <th>IP interna</th>
                <th>Puertos expuestos</th>
                <th>Rol</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div class="gc-cell-main">
                        <span class="material-icons" style="color:#1a73e8;">dns</span>
                        <strong>ctf-db-ssh</strong>
                    </div>
                </td>
                <td>ibague-1a</td>
                <td><span class="gc-chip gc-chip-ok">RUNNING</span></td>
                <td><code>db_ssh</code></td>
                <td><code>22/tcp</code>, <code>3306/tcp</code></td>
                <td>Base de datos + bastion SSH</td>
            </tr>
            <tr>
                <td>
                    <div class="gc-cell-main">
                        <span class="material-icons" style="color:#1a73e8;">dns</span>
                        <strong>ctf-web-app</strong>
                    </div>
                </td>
                <td>ibague-1a</td>
                <td><span class="gc-chip gc-chip-ok">RUNNING</span></td>
                <td><code>web_app</code></td>
                <td><code>80/tcp</code></td>
                <td>Frontend PHP</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="gc-card gc-card-flat" style="margin-top:24px;">
    <div class="gc-card-subheader">
        <h2 class="gc-h2"><span class="material-icons">key</span> Acceso SSH administrativo</h2>
        <button type="button" class="gc-button gc-button-ghost" id="gc-reveal-ssh" aria-pressed="false"
                onclick="ctfToggleSshCreds()">
            <span class="material-icons" id="gc-reveal-icon">visibility_off</span>
            <span id="gc-reveal-label">Mostrar credenciales</span>
        </button>
    </div>
    <p class="gc-muted">Credenciales gestionadas por el operador del laboratorio:</p>

    <div id="gc-ssh-masked" class="gc-code gc-masked" aria-hidden="false">
Host:     ********
Puerto:   ****
Usuario:  *****
Password: ************

$ ssh ****@********* -p ****</div>

    <pre id="gc-ssh-real" class="gc-code" aria-hidden="true" hidden>Host:     localhost
Puerto:   2222
Usuario:  admin
Password: password123

$ ssh admin@localhost -p 2222</pre>

    <p class="gc-hint">Sugerencia: tras conectarte, revisa <code>/home/admin/</code> en busca de la bandera final.</p>
</div>

<script>
function ctfToggleSshCreds() {
    var masked = document.getElementById('gc-ssh-masked');
    var real   = document.getElementById('gc-ssh-real');
    var btn    = document.getElementById('gc-reveal-ssh');
    var icon   = document.getElementById('gc-reveal-icon');
    var label  = document.getElementById('gc-reveal-label');
    var hidden = real.hasAttribute('hidden');

    if (hidden) {
        real.removeAttribute('hidden');
        real.setAttribute('aria-hidden', 'false');
        masked.setAttribute('hidden', '');
        masked.setAttribute('aria-hidden', 'true');
        icon.textContent = 'visibility';
        label.textContent = 'Ocultar credenciales';
        btn.setAttribute('aria-pressed', 'true');
    } else {
        real.setAttribute('hidden', '');
        real.setAttribute('aria-hidden', 'true');
        masked.removeAttribute('hidden');
        masked.setAttribute('aria-hidden', 'false');
        icon.textContent = 'visibility_off';
        label.textContent = 'Mostrar credenciales';
        btn.setAttribute('aria-pressed', 'false');
    }
}
</script>

<?php ctf_render_footer(); ?>
