<?php
/**
 * Estado del ejercicio publicado por el scoreboard.
 * No incluye flags. Lo usan el gate, el poll y el sincronizador.
 */

function ctf_scoreboard_url() {
    $url = getenv('SCOREBOARD_URL');
    if (!$url) {
        return '';
    }
    return rtrim($url, '/');
}

function ctf_http_get($url, $headers = array()) {
    $opts = array(
        'method' => 'GET',
        'timeout' => 2,
        'ignore_errors' => true,
    );
    if ($headers) {
        $opts['header'] = implode("\r\n", $headers) . "\r\n";
    }
    $ctx = stream_context_create(array('http' => $opts));
    $body = @file_get_contents($url, false, $ctx);
    if ($body === false) {
        return null;
    }
    $code = 0;
    if (isset($http_response_header[0]) && preg_match('/\s(\d{3})\s/', $http_response_header[0], $m)) {
        $code = (int)$m[1];
    }
    if ($code >= 400) {
        return null;
    }
    $data = json_decode($body, true);
    return is_array($data) ? $data : null;
}

function ctf_exercise_status() {
    $path = '/tmp/ctf_exercise_status.json';
    if (is_file($path) && (time() - filemtime($path) < 15)) {
        $cached = json_decode((string)@file_get_contents($path), true);
        if (is_array($cached) && isset($cached['status'])) {
            return $cached;
        }
    }

    $base = ctf_scoreboard_url();
    if ($base === '') {
        return array(
            'status' => 'unreachable',
            'generation' => 0,
            'last_event' => '',
            'updated_at' => '',
        );
    }

    $data = ctf_http_get($base . '/api/exercise');
    if (!$data || !isset($data['status'])) {
        return array(
            'status' => 'unreachable',
            'generation' => 0,
            'last_event' => '',
            'updated_at' => '',
        );
    }

    @file_put_contents($path, json_encode($data));
    return $data;
}

function ctf_exercise_screen($status, $seen) {
    $st = isset($status['status']) ? $status['status'] : 'unreachable';
    if ($st === 'pending') {
        return 'pending';
    }
    if ($st === 'stopped') {
        return 'stopped';
    }
    if ($st !== 'running') {
        return 'unreachable';
    }
    $event = isset($status['last_event']) ? $status['last_event'] : '';
    $gen = isset($status['generation']) ? (string)$status['generation'] : '0';
    if ($event === 'restarted' && (string)$seen !== $gen) {
        return 'restarted';
    }
    return 'open';
}

function ctf_local_flags() {
    $path = __DIR__ . '/flags.local.php';
    if (!is_file($path)) {
        return array();
    }
    $data = include $path;
    return is_array($data) ? $data : array();
}

function ctf_exercise_poll_script($screen) {
    $current = json_encode($screen);
    return '<script>(function(){var current=' . $current . ';'
        . 'setInterval(function(){fetch("exercise_status.php",{cache:"no-store",credentials:"same-origin"})'
        . '.then(function(r){return r.json();}).then(function(data){'
        . 'if(data&&data.screen&&data.screen!==current){location.reload();}'
        . '}).catch(function(){});},4000);})();</script>';
}

function ctf_exercise_blocking_page($screen) {
    $titles = array(
        'pending' => 'El ejercicio aún no inicia',
        'stopped' => 'El ejercicio está detenido',
        'restarted' => 'El ejercicio fue reiniciado',
        'unreachable' => 'Sin conexión con el scoreboard',
    );
    $title = isset($titles[$screen]) ? $titles[$screen] : $titles['unreachable'];
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo '<!DOCTYPE html><html lang="es"><head><meta charset="utf-8">';
    echo '<title>' . htmlspecialchars($title) . '</title>';
    echo '<style>body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;';
    echo 'background:#0b1220;color:#e8ecf6;font-family:Segoe UI,Roboto,sans-serif;}';
    echo '.card{max-width:520px;padding:36px 32px;border:1px solid #2a3553;border-radius:16px;background:#131b2c;text-align:center;}';
    echo 'h1{margin:0 0 12px;font-size:28px;}p{margin:0;color:#8a96b5;}';
    echo 'button{margin-top:20px;background:#3b82f6;color:#fff;border:0;border-radius:8px;padding:12px 18px;font-weight:700;cursor:pointer;}</style>';
    echo '</head><body><div class="card"><h1>' . htmlspecialchars($title) . '</h1>';
    if ($screen === 'restarted') {
        echo '<p>Las banderas anteriores ya no valen. Continúa cuando el grupo esté listo.</p>';
        echo '<form method="post"><input type="hidden" name="ack_exercise" value="1">';
        echo '<button type="submit">Continuar</button></form>';
    } elseif ($screen === 'unreachable') {
        echo '<p>Esta estación no pudo leer el estado del tablero.</p>';
    } else {
        echo '<p>Esta estación espera la orden del instructor en el scoreboard.</p>';
    }
    echo ctf_exercise_poll_script($screen);
    echo '</div></body></html>';
}
