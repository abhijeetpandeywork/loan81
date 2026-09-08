<?php
/**
 * Database Connection for Loan81 Platform
 */
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

$host = 'localhost';
$charset = 'utf8mb4';

// Detect environment based on server host or directory
$server_name = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? '';
$is_prod = (strpos($server_name, 'loan81.com') !== false || is_dir('/home/u878958741'));

if ($is_prod) {
    // Production Server (195.35.44.93 / loan81.com)
    $host = '127.0.0.1';
    $db   = 'u878958741_Doc8o';
    $user = 'u878958741_ixE09';
    $pass = 'eZolsMTWSI';
} else {
    // Staging Server (147.93.23.184 / loft.digitalrubix.site)
    $host = 'localhost';
    $db   = 'u406313474_learnloft';
    $user = 'u406313474_learnloft';
    $pass = 'Learnloft1122334455';
}

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    // If running in local development without local DB, fail gracefully
    $pdo = null;
}

