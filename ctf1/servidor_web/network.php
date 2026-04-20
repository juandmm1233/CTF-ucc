<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
ctf_require_login();
ctf_render_header('network', 'IDS Network Tools');

$host   = isset($_POST['host']) ? $_POST['host'] : '';
$tool   = isset($_POST['tool']) ? $_POST['tool'] : 'ping';
$output = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $host !== '') {
    if (ctf_is_observer()) {
        $output = "[denied] El rol observador no puede ejecutar diagnosticos de red.";
    } else {
        // VULNERABILIDAD INTENCIONAL (Command Injection).
        // Concatenamos directamente la entrada del usuario en una llamada a shell_exec.
        // No imitar en produccion. Solo para el laboratorio.
        switch ($tool) {
            case 'traceroute':
                $cmd = "traceroute -n -m 5 " . $host;
                break;
            case 'nslookup':
                $cmd = "nslookup " . $host;
                break;
            case 'ping':
            default:
                $cmd = "ping -c 2 " . $host;
                break;
        }
        $output = shell_exec($cmd . " 2>&1");
        if ($output === null) { $output = "(sin salida)"; }
    }
}
?>

<div class="gc-card gc-card-flat" style="margin-bottom:24px;">
    <div class="gc-card-subheader">
        <h2 class="gc-h2"><span class="material-icons">network_check</span> Diagnostico de conectividad</h2>
        <span class="gc-chip gc-chip-ok">beta</span>
    </div>
    <p style="color:#5f6368;margin:0 0 16px 0;">
        Herramienta interna para operadores de IDS. Ejecuta <code>ping</code>, <code>traceroute</code> y
        <code>nslookup</code> contra recursos internos del proyecto <code>ids-ucc-lab</code>.
    </p>
    <form method="POST" action="network.php" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
        <div style="flex:1;min-width:240px;">
            <label class="gc-label" for="host">Host / IP objetivo</label>
            <input class="gc-input" id="host" name="host" type="text" placeholder="db_ssh, 10.0.0.1, google.com" value="<?php echo htmlspecialchars($host); ?>" />
        </div>
        <div>
            <label class="gc-label" for="tool">Herramienta</label>
            <select class="gc-input" id="tool" name="tool">
                <option value="ping"        <?php if ($tool==='ping') echo 'selected'; ?>>ping</option>
                <option value="traceroute"  <?php if ($tool==='traceroute') echo 'selected'; ?>>traceroute</option>
                <option value="nslookup"    <?php if ($tool==='nslookup') echo 'selected'; ?>>nslookup</option>
            </select>
        </div>
        <button type="submit" class="gc-button"><span class="material-icons">play_arrow</span> Ejecutar</button>
    </form>
</div>

<?php if ($output !== ''): ?>
<div class="gc-card gc-card-flat">
    <div class="gc-card-subheader">
        <h2 class="gc-h2"><span class="material-icons">terminal</span> Salida</h2>
        <span class="gc-chip">$ <?php echo htmlspecialchars($tool); ?></span>
    </div>
    <pre class="gc-code" style="max-height:420px;overflow:auto;"><?php echo htmlspecialchars($output); ?></pre>
</div>
<?php endif; ?>

<div class="gc-card gc-card-flat" style="margin-top:24px;">
    <div class="gc-card-subheader">
        <h2 class="gc-h2"><span class="material-icons">info</span> Recursos internos comunes</h2>
    </div>
    <ul class="gc-list">
        <li><code>db_ssh</code> - instancia MariaDB + SSH</li>
        <li><code>web_app</code> - frontend PHP/Apache</li>
        <li><code>172.22.0.0/24</code> - red interna <code>ctf_net</code></li>
    </ul>
</div>

<?php ctf_render_footer(); ?>
