<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/layout.php';
ctf_require_login();
ctf_render_header('iam', 'IAM & Admin');

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

<div class="gc-toolbar">
    <button class="gc-button"><span class="material-icons">person_add</span> Agregar principal</button>
    <button class="gc-button gc-button-ghost"><span class="material-icons">history</span> Auditoria</button>
</div>

<?php if (!empty($db_error)): ?>
    <div class="gc-alert gc-alert-error">
        <span class="material-icons">error_outline</span>
        <div><?php echo htmlspecialchars($db_error); ?></div>
    </div>
<?php endif; ?>

<div class="gc-card gc-card-flat">
    <div class="gc-card-subheader">
        <h2 class="gc-h2"><span class="material-icons">group</span> Principales del proyecto ids-ucc-lab</h2>
    </div>
    <table class="gc-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>Usuario</th>
                <th>Rol</th>
                <th>Creado</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $u): ?>
                <tr>
                    <td><code>#<?php echo (int)$u['id']; ?></code></td>
                    <td>
                        <div class="gc-cell-main">
                            <span class="gc-avatar-sm"><?php echo htmlspecialchars(strtoupper(substr($u['username'], 0, 1))); ?></span>
                            <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                        </div>
                    </td>
                    <td>
                        <?php if ($u['role'] === 'admin'): ?>
                            <span class="gc-chip gc-chip-warn">roles/owner</span>
                        <?php else: ?>
                            <span class="gc-chip">roles/viewer</span>
                        <?php endif; ?>
                    </td>
                    <td><?php echo htmlspecialchars($u['created_at']); ?></td>
                    <td><span class="gc-chip gc-chip-ok">ACTIVE</span></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="gc-card gc-card-flat" style="margin-top:24px;">
    <div class="gc-card-subheader">
        <h2 class="gc-h2"><span class="material-icons">policy</span> Politicas actuales</h2>
    </div>
    <ul class="gc-list">
        <li><span class="material-icons" style="color:#c5221f;">warning</span> Autenticacion por contrasena habilitada en SSH (db_ssh)</li>
        <li><span class="material-icons" style="color:#c5221f;">warning</span> Endpoint /index.php construye SQL por concatenacion</li>
        <li><span class="material-icons" style="color:#c5221f;">warning</span> Existen usuarios con contrasenas del top-10 mas comunes</li>
        <li><span class="material-icons" style="color:#137333;">check_circle</span> Logs de acceso centralizados en IDS Observability</li>
    </ul>
</div>

<?php ctf_render_footer(); ?>
