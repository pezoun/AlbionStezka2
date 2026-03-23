<?php

$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') !== false ? getenv('DB_PASS') : 'root';
$db = getenv('DB_NAME') ?: 'sportovni_aplikace';
$port = (int)(getenv('DB_PORT') ?: 3306);

$conn = new mysqli($host, $user, $pass, $db, $port);

if ($conn->connect_error) {
    die('Failed to connect DB: ' . $conn->connect_error);
}
?>