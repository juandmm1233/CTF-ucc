<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
ctf_require_login();
ctf_render_header('dashboard', 'Console Home');
?>

<div class="uws-alert uws-alert-success" style="margin-bottom:24px;">
    <span class="material-icons">check_circle</span>
    <div>
        <strong>Bienvenido, <?php echo htmlspecialchars(ctf_current_user()); ?>.</strong>
        <div>Has iniciado sesion en UCC Web Services (UWS).</div>
    </div>
</div>

<?php $sb = getenv('SCOREBOARD_URL'); if ($sb): ?>
<div class="uws-alert" style="background:#fff3e0;border-color:#ffd699;color:#8a4a00;margin-bottom:24px;">
    <span class="material-icons" style="color:#FF9900;">emoji_events</span>
    <div>
        <strong>Scoreboard 15 vs 15</strong> activo en
        <a href="<?php echo htmlspecialchars($sb); ?>" target="_blank" rel="noopener" style="color:#FF9900;font-weight:600;">
            <?php echo htmlspecialchars($sb); ?>
        </a>.
        Envia las banderas que encuentres con tu alias y equipo. Cuidado con los honeypots.
    </div>
</div>
<?php endif; ?>

<section class="uws-grid uws-grid-4">
    <div class="uws-metric-card">
        <span class="uws-metric-label">Instancias Compute activas</span>
        <span class="uws-metric-value">2</span>
        <span class="uws-metric-delta uws-up"><span class="material-icons">arrow_upward</span> Stable</span>
    </div>
    <div class="uws-metric-card">
        <span class="uws-metric-label">Requests / min <span class="uws-live-dot" title="En vivo"></span></span>
        <span class="uws-metric-value" id="metric-rpm">284</span>
        <span class="uws-metric-delta" id="metric-rpm-delta">
            <span class="material-icons">arrow_upward</span>
            <span class="uws-delta-text">+0%</span>
        </span>
    </div>
    <div class="uws-metric-card">
        <span class="uws-metric-label">Uso de CPU <span class="uws-live-dot" title="En vivo"></span></span>
        <span class="uws-metric-value" id="metric-cpu">37%</span>
        <span class="uws-metric-delta" id="metric-cpu-delta">
            <span class="material-icons">arrow_downward</span>
            <span class="uws-delta-text">-0%</span>
        </span>
    </div>
    <div class="uws-metric-card">
        <span class="uws-metric-label">Incidentes abiertos</span>
        <span class="uws-metric-value">0</span>
        <span class="uws-metric-delta uws-ok"><span class="material-icons">verified</span> OK</span>
    </div>
</section>

<section class="uws-grid uws-grid-2" style="margin-top:24px;">
    <div class="uws-card">
        <div class="uws-card-subheader">
            <h2 class="uws-h2"><span class="material-icons">timeline</span> Actividad reciente</h2>
            <a href="logs.php" class="uws-link">Ver logs de Observability</a>
        </div>
        <ul class="uws-timeline">
            <li><span class="uws-dot uws-up"></span> <strong>web_app</strong> - inicio de sesion exitoso desde <code>172.22.0.1</code></li>
            <li><span class="uws-dot uws-ok"></span> <strong>db_ssh</strong> - MariaDB escuchando en el puerto 3306</li>
            <li><span class="uws-dot uws-ok"></span> <strong>db_ssh</strong> - sshd escuchando en el puerto 22</li>
            <li><span class="uws-dot uws-down"></span> <strong>iam</strong> - 2 usuarios IAM con contrasenas debiles</li>
        </ul>
    </div>
    <div class="uws-card">
        <div class="uws-card-subheader">
            <h2 class="uws-h2"><span class="material-icons">lightbulb</span> Recursos sugeridos</h2>
        </div>
        <ul class="uws-list">
            <li><a href="compute.php"><span class="material-icons">memory</span> Inspecciona la instancia Compute <code>ctf-db-ssh</code></a></li>
            <li><a href="storage.php"><span class="material-icons">storage</span> Revisa el bucket <code>uws-ctf-backups</code></a></li>
            <li><a href="network.php"><span class="material-icons">network_check</span> Diagnostica la red interna</a></li>
            <li><a href="iam.php"><span class="material-icons">group</span> Audita los principales IAM</a></li>
            <li><a href="logs.php"><span class="material-icons">query_stats</span> Stream de logs UWS en vivo</a></li>
        </ul>
    </div>
</section>

<script>
(function() {
    var rpm = 284;
    var cpu = 37;

    function clamp(v, min, max) { return Math.max(min, Math.min(max, v)); }
    function randomStep(range) { return Math.round((Math.random() * 2 - 1) * range); }

    function applyDelta(container, prev, curr) {
        var diff = curr - prev;
        var pct = prev === 0 ? 0 : Math.round((diff / prev) * 100);
        var iconEl = container.querySelector('.material-icons');
        var textEl = container.querySelector('.uws-delta-text');
        container.classList.remove('uws-up', 'uws-down', 'uws-ok');
        if (diff > 0) {
            container.classList.add('uws-up');
            iconEl.textContent = 'arrow_upward';
            textEl.textContent = '+' + pct + '%';
        } else if (diff < 0) {
            container.classList.add('uws-down');
            iconEl.textContent = 'arrow_downward';
            textEl.textContent = pct + '%';
        } else {
            container.classList.add('uws-ok');
            iconEl.textContent = 'trending_flat';
            textEl.textContent = '0%';
        }
    }

    function tick() {
        var rpmEl    = document.getElementById('metric-rpm');
        var cpuEl    = document.getElementById('metric-cpu');
        var rpmDelta = document.getElementById('metric-rpm-delta');
        var cpuDelta = document.getElementById('metric-cpu-delta');
        var prevRpm = rpm, prevCpu = cpu;
        rpm = clamp(rpm + randomStep(35), 120, 620);
        cpu = clamp(cpu + randomStep(6),  5,   95);
        rpmEl.textContent = rpm;
        cpuEl.textContent = cpu + '%';
        applyDelta(rpmDelta, prevRpm, rpm);
        applyDelta(cpuDelta, prevCpu, cpu);
        [rpmEl, cpuEl].forEach(function(el) {
            el.classList.remove('uws-metric-pulse');
            void el.offsetWidth;
            el.classList.add('uws-metric-pulse');
        });
    }
    setInterval(tick, 1500);
})();
</script>

<?php ctf_render_footer(); ?>
