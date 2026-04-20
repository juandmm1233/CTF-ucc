<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';
ctf_require_login();

// VULNERABILIDAD INTENCIONAL: control de acceso basado en una cookie trivial.
// Un atacante puede setear la cookie 'role=admin' en el navegador y saltarse
// el control. Solo con fines educativos para el CTF.
$role_cookie = isset($_COOKIE['role']) ? $_COOKIE['role'] : '';
$is_admin = ($role_cookie === 'admin');

$form_error   = '';
$form_success = '';
$created_info = null;

/**
 * Valida un username apto para MariaDB + PHP: 3-32 chars alfa-num + underscore.
 */
function ctf_valid_username($u) {
    return (bool) preg_match('/^[a-zA-Z0-9_]{3,32}$/', $u);
}

/**
 * Valida una contrasena: al menos 4 caracteres, sin comillas para evitar
 * que rompan el statement SQL de CREATE USER (aunque escapamos igual).
 */
function ctf_valid_password($p) {
    return is_string($p) && strlen($p) >= 4 && strlen($p) <= 64
        && strpos($p, "'") === false && strpos($p, '"') === false
        && strpos($p, '\\') === false;
}

if ($is_admin && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $new_user = isset($_POST['new_username']) ? trim($_POST['new_username']) : '';
    $new_pass = isset($_POST['new_password']) ? $_POST['new_password']      : '';
    $new_role = isset($_POST['new_role'])     ? $_POST['new_role']          : 'observador';

    $allowed_roles = ['admin', 'observador'];
    if (!in_array($new_role, $allowed_roles, true)) {
        $form_error = 'Rol invalido. Solo se permite admin u observador.';
    } elseif (!ctf_valid_username($new_user)) {
        $form_error = 'Usuario invalido. Usa 3-32 caracteres (a-z, A-Z, 0-9, _).';
    } elseif (!ctf_valid_password($new_pass)) {
        $form_error = 'Contrasena invalida. Minimo 4 caracteres, sin comillas ni \\.';
    } else {
        $admin_db = ctf_db_connect_admin();
        if ($admin_db === null) {
            $form_error = 'No se pudo conectar con privilegios elevados a MariaDB.';
        } else {
            // 1) Insertar / actualizar el usuario en la tabla de la app (login PHP).
            $stmt = $admin_db->prepare(
                "INSERT INTO users (username, password, role) VALUES (?, ?, ?)
                 ON DUPLICATE KEY UPDATE password = VALUES(password), role = VALUES(role)"
            );
            if ($stmt === false) {
                $form_error = 'Error preparando el INSERT: ' . $admin_db->error;
            } else {
                $stmt->bind_param('sss', $new_user, $new_pass, $new_role);
                if (!$stmt->execute()) {
                    $form_error = 'Error ejecutando el INSERT: ' . $stmt->error;
                }
                $stmt->close();
            }

            // 2) Crear usuario MariaDB real con GRANT segun rol.
            if ($form_error === '') {
                $safe_user = $admin_db->real_escape_string($new_user);
                $safe_pass = $admin_db->real_escape_string($new_pass);

                $create_sql = "CREATE USER IF NOT EXISTS '{$safe_user}'@'%' IDENTIFIED BY '{$safe_pass}'";
                $alter_sql  = "ALTER USER '{$safe_user}'@'%' IDENTIFIED BY '{$safe_pass}'";

                if (!$admin_db->query($create_sql)) {
                    $form_error = 'CREATE USER fallo: ' . $admin_db->error;
                } elseif (!$admin_db->query($alter_sql)) {
                    $form_error = 'ALTER USER fallo: ' . $admin_db->error;
                } else {
                    // Reset: revoca cualquier privilegio previo y asigna el apropiado.
                    @$admin_db->query("REVOKE ALL PRIVILEGES, GRANT OPTION FROM '{$safe_user}'@'%'");

                    if ($new_role === 'admin') {
                        $grant_sql = "GRANT ALL PRIVILEGES ON ctf_login.* TO '{$safe_user}'@'%'";
                    } else { // observador
                        $grant_sql = "GRANT SELECT ON ctf_login.* TO '{$safe_user}'@'%'";
                    }

                    if (!$admin_db->query($grant_sql)) {
                        $form_error = 'GRANT fallo: ' . $admin_db->error;
                    } else {
                        $admin_db->query('FLUSH PRIVILEGES');

                        $form_success = 'Usuario aprovisionado correctamente en la aplicacion y en MariaDB.';
                        $created_info = [
                            'username' => $new_user,
                            'password' => $new_pass,
                            'role'     => $new_role,
                        ];
                    }
                }
            }

            $admin_db->close();
        }
    }
}

// Listado actualizado de usuarios de la app (si podemos leerlo).
$users = [];
$list_error = '';
$list_db = ctf_db_connect();
if ($list_db !== null) {
    $res = @$list_db->query("SELECT id, username, role, created_at FROM users ORDER BY id DESC");
    if ($res) {
        while ($row = $res->fetch_assoc()) { $users[] = $row; }
        $res->close();
    } else {
        $list_error = $list_db->error;
    }
    $list_db->close();
}

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
        <a href="compute.php">IDS Compute</a> y revisa <code>/home/admin/</code>.</p>
    </div>

    <div class="gc-card gc-card-flat" style="margin-top:24px;">
        <div class="gc-card-subheader">
            <h2 class="gc-h2"><span class="material-icons">person_add</span> Provisionar usuario (BD + Web)</h2>
        </div>
        <p class="gc-muted">
            Crea una cuenta de servicio que quedara registrada <strong>simultaneamente</strong>
            en la tabla <code>ctf_login.users</code> (login del portal) y como
            usuario real de MariaDB con los privilegios correspondientes a su rol.
        </p>

        <?php if ($form_error !== ''): ?>
            <div class="gc-alert gc-alert-error" style="margin-bottom:12px;">
                <span class="material-icons">error_outline</span>
                <div><?php echo htmlspecialchars($form_error); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($form_success !== '' && $created_info !== null): ?>
            <div class="gc-alert gc-alert-success" style="margin-bottom:12px;">
                <span class="material-icons">check_circle</span>
                <div>
                    <strong><?php echo htmlspecialchars($form_success); ?></strong>
                    <div>Credenciales listas para usar:</div>
                </div>
            </div>
            <pre class="gc-code">Usuario:  <?php echo htmlspecialchars($created_info['username']); ?>

Password: <?php echo htmlspecialchars($created_info['password']); ?>

Rol:      <?php echo htmlspecialchars($created_info['role']); ?>


# Login web (Ibague Data Services)
http://localhost:8080/index.php

# Acceso MariaDB desde el contenedor db_ssh:
$ mysql -h 127.0.0.1 -u <?php echo htmlspecialchars($created_info['username']); ?> -p'<?php echo htmlspecialchars($created_info['password']); ?>' ctf_login</pre>
        <?php endif; ?>

        <form method="POST" action="admin.php" class="gc-form" autocomplete="off" style="max-width:520px;">
            <input type="hidden" name="create_user" value="1" />
            <label class="gc-field">
                <span class="gc-field-label">Nuevo usuario</span>
                <input class="gc-input" type="text" name="new_username" required
                       pattern="[a-zA-Z0-9_]{3,32}"
                       placeholder="ej. observer01" />
            </label>
            <label class="gc-field">
                <span class="gc-field-label">Contrasena</span>
                <input class="gc-input" type="text" name="new_password" required
                       minlength="4" maxlength="64"
                       placeholder="minimo 4 caracteres" />
            </label>
            <label class="gc-field">
                <span class="gc-field-label">Rol</span>
                <select class="gc-input" name="new_role">
                    <option value="observador">observador (solo lectura)</option>
                    <option value="admin">admin (lectura/escritura)</option>
                </select>
            </label>
            <div class="gc-actions">
                <span class="gc-hint">Los cambios impactan la BD real y el login web.</span>
                <button class="gc-button" type="submit"><span class="material-icons">add</span> Crear cuenta</button>
            </div>
        </form>
    </div>

    <div class="gc-card gc-card-flat" style="margin-top:24px;">
        <div class="gc-card-subheader">
            <h2 class="gc-h2"><span class="material-icons">group</span> Usuarios registrados</h2>
        </div>
        <?php if ($list_error !== ''): ?>
            <div class="gc-alert gc-alert-error">
                <span class="material-icons">error_outline</span>
                <div><?php echo htmlspecialchars($list_error); ?></div>
            </div>
        <?php else: ?>
            <table class="gc-table">
                <thead>
                    <tr><th>ID</th><th>Usuario</th><th>Rol</th><th>Creado</th></tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><code>#<?php echo (int)$u['id']; ?></code></td>
                            <td><strong><?php echo htmlspecialchars($u['username']); ?></strong></td>
                            <td>
                                <?php $r = $u['role']; ?>
                                <?php if ($r === 'admin'): ?>
                                    <span class="gc-chip gc-chip-warn">admin</span>
                                <?php elseif ($r === 'observador' || $r === 'observer'): ?>
                                    <span class="gc-chip gc-chip-ok">observador</span>
                                <?php else: ?>
                                    <span class="gc-chip"><?php echo htmlspecialchars($r); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
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
