<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';
ctf_require_login();

// VULNERABILIDAD INTENCIONAL: control de acceso trivial por cookie.
$role_cookie = isset($_COOKIE['role']) ? $_COOKIE['role'] : '';
$is_admin = ($role_cookie === 'admin');

$form_error   = '';
$form_success = '';
$created_info = null;

function ctf_valid_username($u) {
    return (bool) preg_match('/^[a-zA-Z0-9_]{3,32}$/', $u);
}

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
                    @$admin_db->query("REVOKE ALL PRIVILEGES, GRANT OPTION FROM '{$safe_user}'@'%'");

                    if ($new_role === 'admin') {
                        $grant_sql = "GRANT ALL PRIVILEGES ON ctf_login.* TO '{$safe_user}'@'%'";
                    } else {
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

ctf_render_header('dashboard', 'Cuenta Administrador');
?>

<?php if (!$is_admin): ?>
    <div class="uws-alert uws-alert-error">
        <span class="material-icons">block</span>
        <div>
            <strong>403 - Acceso denegado</strong>
            <div>Esta seccion solo esta disponible para principales con <code>roles/owner</code>.
            El backend valida el rol mediante una cookie emitida por el IdP corporativo.</div>
        </div>
    </div>

    <div class="uws-card" style="margin-top:16px;">
        <div class="uws-card-subheader">
            <h2 class="uws-h2"><span class="material-icons">help_center</span> Informacion de depuracion</h2>
        </div>
        <pre class="uws-code">GET /admin.php
Cookie recibida: role=<?php echo htmlspecialchars($role_cookie === '' ? '(vacia)' : $role_cookie); ?>

Cabecera esperada: Cookie: role=&lt;rol&gt;
Roles aceptados: admin</pre>
        <p class="uws-hint">Nota del equipo: "Mientras el IdP estuvo caido dejamos la validacion de rol
        con una cookie en texto plano. Lo corregimos despues del CTF."</p>
    </div>
<?php else: ?>
    <div class="uws-alert uws-alert-success">
        <span class="material-icons">check_circle</span>
        <div>
            <strong>Bypass del control de acceso completado</strong>
            <div>Llegaste al panel de administracion forjando la cookie <code>role=admin</code>.</div>
        </div>
    </div>

    <div class="uws-card">
        <div class="uws-card-subheader">
            <h2 class="uws-h2"><span class="material-icons">flag</span> Bandera intermedia</h2>
        </div>
        <pre class="uws-code">FLAG{UCC_UWS_Cookie_Bypass}</pre>
        <p class="uws-hint">La bandera final sigue alla afuera. Usa las credenciales SSH expuestas en
        <a href="compute.php">Compute</a> y revisa <code>/home/admin/</code>.</p>
    </div>

    <div class="uws-card" style="margin-top:24px;">
        <div class="uws-card-subheader">
            <h2 class="uws-h2"><span class="material-icons">person_add</span> Provisionar usuario (BD + Web)</h2>
        </div>
        <p class="uws-muted">
            Crea una cuenta de servicio que quedara registrada <strong>simultaneamente</strong>
            en la tabla <code>ctf_login.users</code> (login del portal UWS) y como
            usuario real de MariaDB con los privilegios correspondientes a su rol.
        </p>

        <?php if ($form_error !== ''): ?>
            <div class="uws-alert uws-alert-error" style="margin-bottom:12px;">
                <span class="material-icons">error_outline</span>
                <div><?php echo htmlspecialchars($form_error); ?></div>
            </div>
        <?php endif; ?>

        <?php if ($form_success !== '' && $created_info !== null): ?>
            <div class="uws-alert uws-alert-success" style="margin-bottom:12px;">
                <span class="material-icons">check_circle</span>
                <div>
                    <strong><?php echo htmlspecialchars($form_success); ?></strong>
                    <div>Credenciales listas para usar:</div>
                </div>
            </div>
            <pre class="uws-code">Usuario:  <?php echo htmlspecialchars($created_info['username']); ?>

Password: <?php echo htmlspecialchars($created_info['password']); ?>

Rol:      <?php echo htmlspecialchars($created_info['role']); ?>


# Login web (UCC Web Services)
http://localhost:8081/index.php

# Acceso MariaDB desde el contenedor db_ssh:
$ mysql -h 127.0.0.1 -u <?php echo htmlspecialchars($created_info['username']); ?> -p'<?php echo htmlspecialchars($created_info['password']); ?>' ctf_login</pre>
        <?php endif; ?>

        <form method="POST" action="admin.php" class="uws-form" autocomplete="off" style="max-width:520px;">
            <input type="hidden" name="create_user" value="1" />
            <label class="uws-field">
                <span class="uws-field-label">Nuevo usuario</span>
                <input class="uws-input" type="text" name="new_username" required
                       pattern="[a-zA-Z0-9_]{3,32}"
                       placeholder="ej. observer01" />
            </label>
            <label class="uws-field">
                <span class="uws-field-label">Contrasena</span>
                <input class="uws-input" type="text" name="new_password" required
                       minlength="4" maxlength="64"
                       placeholder="minimo 4 caracteres" />
            </label>
            <label class="uws-field">
                <span class="uws-field-label">Rol</span>
                <select class="uws-input" name="new_role">
                    <option value="observador">observador (solo lectura)</option>
                    <option value="admin">admin (lectura/escritura)</option>
                </select>
            </label>
            <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:8px;">
                <span class="uws-hint">Los cambios impactan la BD real y el login web.</span>
                <button class="uws-button uws-button-primary" type="submit">
                    <span class="material-icons">add</span> Crear cuenta
                </button>
            </div>
        </form>
    </div>

    <div class="uws-card" style="margin-top:24px;">
        <div class="uws-card-subheader">
            <h2 class="uws-h2"><span class="material-icons">group</span> Usuarios registrados</h2>
        </div>
        <?php if ($list_error !== ''): ?>
            <div class="uws-alert uws-alert-error">
                <span class="material-icons">error_outline</span>
                <div><?php echo htmlspecialchars($list_error); ?></div>
            </div>
        <?php else: ?>
            <table class="uws-table">
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
                                    <span class="uws-chip uws-chip-warn">admin</span>
                                <?php elseif ($r === 'observador' || $r === 'observer'): ?>
                                    <span class="uws-chip uws-chip-ok">observador</span>
                                <?php else: ?>
                                    <span class="uws-chip"><?php echo htmlspecialchars($r); ?></span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>

    <div class="uws-card" style="margin-top:24px;">
        <div class="uws-card-subheader">
            <h2 class="uws-h2"><span class="material-icons">warning</span> Notas de la cuenta administrador</h2>
        </div>
        <ul class="uws-list">
            <li>La instancia Compute <code>ctf-db-ssh</code> acepta autenticacion SSH por contrasena.</li>
            <li>El usuario <code>admin</code> fue creado con la contrasena <code>password123</code>.</li>
            <li>Rota la bandera en <code>/home/admin/flag.txt</code> cuando termine el CTF.</li>
        </ul>
    </div>
<?php endif; ?>

<?php ctf_render_footer(); ?>
