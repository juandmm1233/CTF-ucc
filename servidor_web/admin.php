<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
ctf_require_login();

// VULNERABILIDAD INTENCIONAL: control de acceso basado en una cookie trivial.
// Un atacante puede setear la cookie 'role=admin' en el navegador y saltarse
// el control. Solo con fines educativos para el CTF.
$role_cookie = isset($_COOKIE['role']) ? $_COOKIE['role'] : '';
$is_admin = ($role_cookie === 'admin');

ctf_render_header('dashboard', 'Admin Console');
?>

<?php if (!$is_admin): ?>
    <div class="gc-alert gc-alert-error">
        <span class="material-icons">block</span>
        <div>
            <strong>403 - Acceso restringido</strong>
            <div>Esta seccion solo esta disponible para principales con <code>roles/owner</code>.
            El backend valida el rol mediante una cookie emitida por el IdP corporativo.</div>
        </div>
    </div>

    <div class="gc-card gc-card-flat" style="margin-top:16px;">
        <div class="gc-card-subheader">
            <h2 class="gc-h2"><span class="material-icons">help_center</span> Debug info</h2>
        </div>
        <pre class="gc-code">GET /admin.php
Cookie recibida: role=<?php echo htmlspecialchars($role_cookie === '' ? '(vacia)' : $role_cookie); ?>

Header esperado: Cookie: role=&lt;rol&gt;
Roles aceptados: admin</pre>
        <p class="gc-hint">Nota interna del equipo: "Como el IdP se cayo esta semana, dejamos el control de rol
        en una cookie plana. Lo arreglamos despues del CTF."</p>
    </div>
<?php else: ?>
    <div class="gc-alert gc-alert-success">
        <span class="material-icons">check_circle</span>
        <div>
            <strong>Bypass de control de acceso completado</strong>
            <div>Accediste al panel de administracion forzando la cookie <code>role=admin</code>.</div>
        </div>
    </div>

    <div class="gc-card gc-card-flat">
        <div class="gc-card-subheader">
            <h2 class="gc-h2"><span class="material-icons">flag</span> Bandera intermedia</h2>
        </div>
        <pre class="gc-code">FLAG{UCC_Cookie_Bypass_OK}</pre>
        <p class="gc-hint">Aun queda la bandera final. Usa las credenciales SSH expuestas en
        <a href="compute.php">Compute Engine</a> y revisa <code>/home/admin/</code>.</p>
    </div>

    <div class="gc-card gc-card-flat" style="margin-top:24px;">
        <div class="gc-card-subheader">
            <h2 class="gc-h2"><span class="material-icons">warning</span> Notas del administrador</h2>
        </div>
        <ul class="gc-list">
            <li>La VM <code>ctf-db-ssh</code> acepta autenticacion SSH por contrasena.</li>
            <li>El usuario <code>admin</code> fue creado con la contrasena <code>password123</code>.</li>
            <li>Recordar rotar la bandera de <code>/home/admin/flag.txt</code> al terminar el CTF.</li>
        </ul>
    </div>
<?php endif; ?>

<?php ctf_render_footer(); ?>
