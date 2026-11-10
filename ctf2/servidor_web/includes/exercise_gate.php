<?php
/**
 * auto_prepend_file: pantalla de estado en todas las PHP del lab.
 * En CLI no hace nada, para no interferir con sync_exercise.php.
 */
if (PHP_SAPI === 'cli') {
    return;
}

$ctfScript = basename(isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '');
if ($ctfScript === 'exercise_status.php') {
    return;
}

require_once __DIR__ . '/exercise_lib.php';

$ctfStatus = ctf_exercise_status();
$ctfSeen = isset($_COOKIE['exercise_seen']) ? (string)$_COOKIE['exercise_seen'] : '';
$ctfScreen = ctf_exercise_screen($ctfStatus, $ctfSeen);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ack_exercise']) && $ctfScreen === 'restarted') {
    $ctfGen = isset($ctfStatus['generation']) ? (string)$ctfStatus['generation'] : '0';
    setcookie('exercise_seen', $ctfGen, array(
        'expires' => time() + 7 * 86400,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ));
    $target = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    header('Location: ' . $target);
    exit;
}

$ctfFlags = ctf_local_flags();
$ctfTrap = isset($ctfFlags['honeypot_cookie']) ? $ctfFlags['honeypot_cookie'] : '';
if (is_string($ctfTrap) && preg_match('/^FLAG\{[a-z0-9_]+\}$/', $ctfTrap)) {
    setcookie('flag', $ctfTrap, array(
        'expires' => time() + 86400,
        'path' => '/',
        'samesite' => 'Lax',
    ));
}

if ($ctfScreen !== 'open') {
    ctf_exercise_blocking_page($ctfScreen);
    exit;
}

ob_start(function ($html) use ($ctfScreen) {
    $script = ctf_exercise_poll_script($ctfScreen);
    $pos = strripos($html, '</body>');
    if ($pos === false) {
        return $html . $script;
    }
    return substr($html, 0, $pos) . $script . substr($html, $pos);
});
