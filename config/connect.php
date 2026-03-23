<?php

$host = getenv('DB_HOST') ?: 'localhost';
$user = getenv('DB_USER') ?: 'root';
$db = getenv('DB_NAME') ?: 'sportovni_aplikace';
$port = (int)(getenv('DB_PORT') ?: 3306);

$envPass = getenv('DB_PASS');
$passwordCandidates = $envPass !== false ? [$envPass] : ['', 'root'];
$conn = null;
$lastError = 'Unknown database connection error.';

foreach ($passwordCandidates as $pass) {
    try {
        $conn = new mysqli($host, $user, $pass, $db, $port);
        break;
    } catch (mysqli_sql_exception $e) {
        $lastError = $e->getMessage();
    }
}

if ($conn === null) {
    die('Failed to connect DB: ' . $lastError);
}
?>