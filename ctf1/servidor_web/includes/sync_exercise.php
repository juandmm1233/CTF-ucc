<?php
/**
 * Descarga el estado y las flags del scoreboard y las reparte en las
 * superficies del lab. Solo CLI (lo lanza el entrypoint del contenedor web).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/exercise_lib.php';
require_once __DIR__ . '/db.php';

$base = ctf_scoreboard_url();
if ($base === '') {
    exit(0);
}

$public = ctf_http_get($base . '/api/exercise');
if (!$public || !isset($public['status'])) {
    exit(0);
}
@file_put_contents('/tmp/ctf_exercise_status.json', json_encode($public));

$token = getenv('LAB_SYNC_TOKEN') ?: '';
$lab = getenv('LAB_ID') ?: '';
if ($token === '' || $lab === '') {
    exit(0);
}

$payload = ctf_http_get($base . '/api/exercise/flags', array(
    'X-Lab-Token: ' . $token,
    'X-Lab-Id: ' . $lab,
    'Accept: application/json',
));
if (!$payload || !isset($payload['generation'])) {
    exit(0);
}

$gen = (int)$payload['generation'];
$flags = (isset($payload['flags']) && is_array($payload['flags'])) ? $payload['flags'] : array();
$appliedPath = '/tmp/ctf_applied_generation';
$applied = is_file($appliedPath) ? (int)file_get_contents($appliedPath) : -1;
if ($applied === $gen) {
    exit(0);
}

function ctf_sync_value($flags, $key, $gen) {
    if ($gen <= 0) {
        return 'PENDIENTE';
    }
    $value = isset($flags[$key]) ? $flags[$key] : '';
    if (is_string($value) && preg_match('/^FLAG\{[a-z0-9_]+\}$/', $value)) {
        return $value;
    }
    return 'PENDIENTE';
}

function ctf_sync_template($template, $dest, $flag) {
    if (!is_file($template)) {
        return;
    }
    $raw = file_get_contents($template);
    if ($raw === false) {
        return;
    }
    file_put_contents($dest, str_replace('__FLAG__', $flag, $raw));
}

$root = dirname(__DIR__);
$cookie = ctf_sync_value($flags, 'cookie', $gen);
$trap = ctf_sync_value($flags, 'honeypot_cookie', $gen);
$local = var_export(array(
    'generation' => $gen,
    'cookie' => $cookie,
    'honeypot_cookie' => $trap,
), true);
file_put_contents(__DIR__ . '/flags.local.php', "<?php\nreturn " . $local . ";\n");

ctf_sync_template(
    $root . '/templates/admin_notes.md.tmpl',
    $root . '/bucket/admin_notes.md',
    ctf_sync_value($flags, 'lfi', $gen)
);
ctf_sync_template(
    $root . '/templates/app.ini.tmpl',
    $root . '/config/app.ini',
    ctf_sync_value($flags, 'config', $gen)
);

if (!is_dir('/var/ctf-private')) {
    @mkdir('/var/ctf-private', 0755, true);
}
file_put_contents('/var/ctf-private/cmd_flag.txt', ctf_sync_value($flags, 'cmd', $gen) . "\n");
file_put_contents('/var/ctf-private/upload_flag.txt', ctf_sync_value($flags, 'upload', $gen) . "\n");

if (!is_dir('/lab_runtime')) {
    @mkdir('/lab_runtime', 0755, true);
}
$sshPath = '/lab_runtime/flag.txt';
file_put_contents($sshPath, ctf_sync_value($flags, 'ssh', $gen) . "\n");
@chmod($sshPath, 0600);

$hash = ctf_sync_value($flags, 'hash', $gen);
$hashValue = ($hash === 'PENDIENTE')
    ? 'PENDIENTE'
    : 'Cuando descifres los 3 hashes MD5, somete: ' . $hash;

$secrets = array(
    'sqli_flag' => ctf_sync_value($flags, 'sqli', $gen),
    'hash_flag_hint' => $hashValue,
    'honeypot_alert' => ctf_sync_value($flags, 'honeypot_secrets', $gen),
);

$db = ctf_db_connect_admin();
if (!$db) {
    exit(0);
}

$ok = true;
foreach ($secrets as $label => $value) {
    $select = $db->prepare('SELECT id FROM secrets WHERE label=? LIMIT 1');
    if (!$select) {
        $ok = false;
        break;
    }
    $select->bind_param('s', $label);
    $select->execute();
    $select->store_result();
    $exists = $select->num_rows > 0;
    $select->close();

    if ($exists) {
        $stmt = $db->prepare('UPDATE secrets SET value=? WHERE label=?');
    } else {
        $stmt = $db->prepare('INSERT INTO secrets (label, value) VALUES (?, ?)');
        if ($stmt) {
            $stmt->bind_param('ss', $label, $value);
            if (!$stmt->execute()) {
                $ok = false;
            }
            $stmt->close();
            continue;
        }
    }
    if (!$stmt) {
        $ok = false;
        break;
    }
    $stmt->bind_param('ss', $value, $label);
    if (!$stmt->execute()) {
        $ok = false;
    }
    $stmt->close();
}
$db->close();

if ($ok) {
    file_put_contents($appliedPath, (string)$gen);
}
