<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/layout.php';
ctf_require_login();
ctf_render_header('logs', 'UWS Observability - Logs');

function ctf_log_ts($offset) {
    return date('Y-m-d H:i:s', time() - $offset);
}
?>

<div class="uws-toolbar">
    <div class="uws-search uws-search-filter">
        <span class="material-icons">filter_alt</span>
        <input type="text" placeholder='fields @timestamp, @message | filter @message like /password/' />
    </div>
    <button class="uws-button uws-button-primary"><span class="material-icons">play_arrow</span> Ejecutar consulta</button>
</div>

<div class="uws-card">
    <div class="uws-log">
<span class="uws-log-info">[<?php echo ctf_log_ts(240); ?>] INFO  db_ssh          mariadbd: ready for connections on port 3306</span>
<span class="uws-log-info">[<?php echo ctf_log_ts(232); ?>] INFO  db_ssh          sshd: listening on 0.0.0.0:22</span>
<span class="uws-log-info">[<?php echo ctf_log_ts(180); ?>] INFO  web_app         Apache/2.4 started, serving /var/www/html</span>
<span class="uws-log-warn">[<?php echo ctf_log_ts(95); ?>] WARN  web_app         POST /index.php suspicious payload: ' OR '1'='1</span>
<span class="uws-log-warn">[<?php echo ctf_log_ts(93); ?>] WARN  mariadb         query returned 4 rows for user ''</span>
<span class="uws-log-info">[<?php echo ctf_log_ts(92); ?>] INFO  web_app         session opened for user=<?php echo htmlspecialchars(ctf_current_user()); ?></span>
<span class="uws-log-err">[<?php echo ctf_log_ts(70); ?>] ERROR security        password auth enabled on sshd (policy violation)</span>
<span class="uws-log-warn">[<?php echo ctf_log_ts(45); ?>] WARN  iam             user 'admin' uses top-1000 password</span>
<span class="uws-log-info">[<?php echo ctf_log_ts(20); ?>] INFO  web_app         GET /dashboard.php 200 OK</span>
<span class="uws-log-info">[<?php echo ctf_log_ts(10); ?>] INFO  web_app         GET /logs.php 200 OK</span>
<span class="uws-log-debug">[<?php echo ctf_log_ts(2); ?>] DEBUG web_app         Note: legacy cookie 'role' still honored in /admin.php</span>
    </div>
</div>

<?php ctf_render_footer(); ?>
