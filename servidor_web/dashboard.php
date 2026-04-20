<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
ctf_require_login();
ctf_render_header('dashboard', 'Dashboard');
?>

<div class="gc-alert gc-alert-success" style="margin-bottom:24px;">
    <span class="material-icons">check_circle</span>
    <div>
        <strong>Bienvenido, <?php echo htmlspecialchars(ctf_current_user()); ?>.</strong>
        <div>Te autenticaste correctamente en la consola CTF.</div>
    </div>
</div>

<section class="gc-grid gc-grid-4">
    <div class="gc-metric-card">
        <span class="gc-metric-label">VMs activas</span>
        <span class="gc-metric-value">2</span>
        <span class="gc-metric-delta gc-up"><span class="material-icons">arrow_upward</span> Estable</span>
    </div>
    <div class="gc-metric-card">
        <span class="gc-metric-label">Requests / min <span class="gc-live-dot" title="En vivo"></span></span>
        <span class="gc-metric-value" id="metric-rpm">284</span>
        <span class="gc-metric-delta" id="metric-rpm-delta">
            <span class="material-icons">arrow_upward</span>
            <span class="gc-delta-text">+0%</span>
        </span>
    </div>
    <div class="gc-metric-card">
        <span class="gc-metric-label">Uso CPU <span class="gc-live-dot" title="En vivo"></span></span>
        <span class="gc-metric-value" id="metric-cpu">37%</span>
        <span class="gc-metric-delta" id="metric-cpu-delta">
            <span class="material-icons">arrow_downward</span>
            <span class="gc-delta-text">-0%</span>
        </span>
    </div>
    <div class="gc-metric-card">
        <span class="gc-metric-label">Incidentes abiertos</span>
        <span class="gc-metric-value">0</span>
        <span class="gc-metric-delta gc-ok"><span class="material-icons">verified</span> OK</span>
    </div>
</section>

<script>
(function() {
    var rpm = 284;
    var cpu = 37;

    function clamp(v, min, max) {
        return Math.max(min, Math.min(max, v));
    }

    function randomStep(range) {
        return Math.round((Math.random() * 2 - 1) * range);
    }

    function applyDelta(container, prev, curr, unit) {
        var diff = curr - prev;
        var pct = prev === 0 ? 0 : Math.round((diff / prev) * 100);
        var iconEl = container.querySelector('.material-icons');
        var textEl = container.querySelector('.gc-delta-text');
        container.classList.remove('gc-up', 'gc-down', 'gc-ok');

        if (diff > 0) {
            container.classList.add('gc-up');
            iconEl.textContent = 'arrow_upward';
            textEl.textContent = '+' + pct + '%';
        } else if (diff < 0) {
            container.classList.add('gc-down');
            iconEl.textContent = 'arrow_downward';
            textEl.textContent = pct + '%';
        } else {
            container.classList.add('gc-ok');
            iconEl.textContent = 'trending_flat';
            textEl.textContent = '0%';
        }
    }

    function tick() {
        var rpmEl      = document.getElementById('metric-rpm');
        var cpuEl      = document.getElementById('metric-cpu');
        var rpmDelta   = document.getElementById('metric-rpm-delta');
        var cpuDelta   = document.getElementById('metric-cpu-delta');

        var prevRpm = rpm;
        var prevCpu = cpu;

        rpm = clamp(rpm + randomStep(35), 120, 620);
        cpu = clamp(cpu + randomStep(6),  5,   95);

        rpmEl.textContent = rpm;
        cpuEl.textContent = cpu + '%';

        applyDelta(rpmDelta, prevRpm, rpm);
        applyDelta(cpuDelta, prevCpu, cpu);

        [rpmEl, cpuEl].forEach(function(el) {
            el.classList.remove('gc-metric-pulse');
            void el.offsetWidth;
            el.classList.add('gc-metric-pulse');
        });
    }

    setInterval(tick, 1500);
})();
</script>

<section class="gc-grid gc-grid-2" style="margin-top:24px;">
    <div class="gc-card gc-card-flat">
        <div class="gc-card-subheader">
            <h2 class="gc-h2"><span class="material-icons">insights</span> Actividad reciente</h2>
            <a href="logs.php" class="gc-link">Ver todos los logs</a>
        </div>
        <ul class="gc-timeline">
            <li><span class="gc-dot gc-up"></span> <strong>web_app</strong> - login exitoso desde <code>172.22.0.1</code></li>
            <li><span class="gc-dot gc-ok"></span> <strong>db_ssh</strong> - MariaDB en puerto 3306 listo</li>
            <li><span class="gc-dot gc-ok"></span> <strong>db_ssh</strong> - sshd escuchando en puerto 22</li>
            <li><span class="gc-dot gc-down"></span> <strong>iam</strong> - 2 usuarios con contrasenas debiles</li>
        </ul>
    </div>
    <div class="gc-card gc-card-flat">
        <div class="gc-card-subheader">
            <h2 class="gc-h2"><span class="material-icons">tips_and_updates</span> Recursos sugeridos</h2>
        </div>
        <ul class="gc-list">
            <li><a href="compute.php"><span class="material-icons">dns</span> Inspecciona la VM <code>db_ssh</code></a></li>
            <li><a href="storage.php"><span class="material-icons">folder</span> Revisa el bucket <code>ctf-ucc-backups</code></a></li>
            <li><a href="iam.php"><span class="material-icons">group</span> Audita los usuarios IAM</a></li>
            <li><a href="logs.php"><span class="material-icons">receipt_long</span> Logs en vivo del cluster</a></li>
        </ul>
    </div>
</section>

<?php ctf_render_footer(); ?>
