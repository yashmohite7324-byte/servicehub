<?php
// includes/db.php
// Database connection using PDO with port 3307

$host = 'localhost';
$db   = 'servicehub1';
$user = 'root';
$pass = ''; // Your MySQL password, if any
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=3306;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    die(json_encode([
        'error' => 'Database connection failed',
        'details' => $e->getMessage()
    ]));
}
