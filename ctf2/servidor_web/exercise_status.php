<?php
require_once __DIR__ . '/includes/exercise_lib.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$status = ctf_exercise_status();
$seen = isset($_COOKIE['exercise_seen']) ? (string)$_COOKIE['exercise_seen'] : '';
$status['screen'] = ctf_exercise_screen($status, $seen);
echo json_encode($status);
