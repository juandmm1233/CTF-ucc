<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
ctf_require_login();
ctf_render_header('compute', 'Compute Instances');
?>

<div class="uws-toolbar">
    <button class="uws-button uws-button-primary"><span class="material-icons">add</span> Lanzar instancia</button>
    <button class="uws-button"><span class="material-icons">refresh</span> Refrescar</button>
    <button class="uws-button"><span class="material-icons">play_arrow</span> Estado instancia</button>
    <button class="uws-button"><span class="material-icons">build</span> Acciones</button>
</div>

<div class="uws-card">
    <table class="uws-table">
        <thead>
            <tr>
                <th><input type="checkbox" /></th>
                <th>Nombre</th>
                <th>Zona de disponibilidad</th>
                <th>Estado</th>
                <th>IP privada</th>
                <th>Puertos expuestos</th>
                <th>Rol</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><input type="checkbox" /></td>
                <td>
                    <div class="uws-cell-main">
                        <span class="material-icons" style="color:#ff9900;">memory</span>
                        <strong>ctf-db-ssh</strong>
                    </div>
                </td>
                <td>ibague-1a</td>
                <td><span class="uws-chip uws-chip-ok">Running</span></td>
                <td><code>db_ssh</code></td>
                <td><code>22/tcp</code>, <code>3306/tcp</code></td>
                <td>Base de datos + bastion SSH</td>
            </tr>
            <tr>
                <td><input type="checkbox" /></td>
                <td>
                    <div class="uws-cell-main">
                        <span class="material-icons" style="color:#ff9900;">memory</span>
                        <strong>ctf-web-app</strong>
                    </div>
                </td>
                <td>ibague-1a</td>
                <td><span class="uws-chip uws-chip-ok">Running</span></td>
                <td><code>web_app</code></td>
                <td><code>80/tcp</code></td>
                <td>Frontend PHP</td>
            </tr>
        </tbody>
    </table>
</div>

<div class="uws-card" style="margin-top:24px;">
    <div class="uws-card-subheader">
        <h2 class="uws-h2"><span class="material-icons">key</span> Acceso SSH administrativo</h2>
        <button type="button" class="uws-button" id="uws-reveal-ssh" aria-pressed="false"
                onclick="ctfToggleSshCreds()">
            <span class="material-icons" id="uws-reveal-icon">visibility_off</span>
            <span id="uws-reveal-label">Mostrar credenciales</span>
        </button>
    </div>
    <p class="uws-muted">Credenciales gestionadas por el operador del laboratorio:</p>

    <div id="uws-ssh-masked" class="uws-code uws-masked" aria-hidden="false">
Host:     ********
Puerto:   ****
Usuario:  *****
Password: ************

$ ssh ****@********* -p ****</div>

    <pre id="uws-ssh-real" class="uws-code" aria-hidden="true" hidden>Host:     localhost
Puerto:   2223
Usuario:  admin
Password: password123

$ ssh admin@localhost -p 2223</pre>

    <p class="uws-hint">Sugerencia: tras conectarte, revisa <code>/home/admin/</code> en busca de la bandera final.</p>
</div>

<script>
function ctfToggleSshCreds() {
    var masked = document.getElementById('uws-ssh-masked');
    var real   = document.getElementById('uws-ssh-real');
    var btn    = document.getElementById('uws-reveal-ssh');
    var icon   = document.getElementById('uws-reveal-icon');
    var label  = document.getElementById('uws-reveal-label');
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
