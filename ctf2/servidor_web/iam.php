<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';
ctf_require_login();
ctf_render_header('iam', 'IAM - Usuarios y politicas');

$users = [];
$db_error = '';
$mysqli = ctf_db_connect();
if ($mysqli === null) {
    $db_error = 'No se pudo conectar a MariaDB.';
} else {
    $res = @$mysqli->query("SELECT id, username, role, created_at FROM users ORDER BY id");
    if ($res) {
        while ($row = $res->fetch_assoc()) {
            $users[] = $row;
        }
        $res->close();
    } else {
        $db_error = $mysqli->error;
    }
    $mysqli->close();
}
?>

<div class="uws-toolbar">
    <button class="uws-button uws-button-primary"><span class="material-icons">person_add</span> Agregar usuarios</button>
    <button class="uws-button"><span class="material-icons">history</span> Auditoria</button>
</div>

<?php if (!empty($db_error)): ?>
    <div class="uws-alert uws-alert-error">
        <span class="material-icons">error</span>
        <div><?php echo htmlspecialchars($db_error); ?></div>
    </div>
<?php endif; ?>

<div class="uws-card">
    <div class="uws-card-subheader">
        <h2 class="uws-h2"><span class="material-icons">group</span> Principales IAM de la cuenta uws-ctf-lab</h2>
    </div>
    <table class="uws-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Politica</th>
                <th>Creado</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><code>#<?php echo (int)$u['id']; ?></code></td>
                    <td>
                        <div class="uws-cell-main">
                            <span class="uws-avatar-sm"><?php echo htmlspecialchars(strtoupper(substr($u['username'], 0, 1))); ?></span>
                            <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                        </div>
                    </td>
                    <td>
                        <?php if ($u['role'] === 'admin'): ?>
                            <span class="uws-chip uws-chip-warn">AdministratorAccess</span>
                        <?php else: ?>
                            <span class="uws-chip">ReadOnlyAccess</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                    <td><span class="uws-chip uws-chip-ok">Activo</span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="uws-card" style="margin-top:24px;">
    <div class="uws-card-subheader">
        <h2 class="uws-h2"><span class="material-icons">policy</span> Hallazgos de seguridad</h2>
    </div>
    <ul class="uws-list">
        <li><span class="material-icons" style="color:#d13212;">warning</span> Autenticacion por contrasena habilitada en SSH de Compute (ctf-db-ssh)</li>
        <li><span class="material-icons" style="color:#d13212;">warning</span> /index.php construye SQL por concatenacion de cadenas</li>
        <li><span class="material-icons" style="color:#d13212;">warning</span> Usuarios con contrasenas del top-10 mas comunes</li>
        <li><span class="material-icons" style="color:#1d8102;">check_circle</span> Logging centralizado UWS habilitado</li>
    </ul>
</div>

<?php ctf_render_footer(); ?>
