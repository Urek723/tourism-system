<?php

define('DB_HOST',    'localhost');
define('DB_NAME',    'tourism_system');
define('DB_USER',    'root');         // Change to your DB username
define('DB_PASS',    '');             // Change to your DB password
define('DB_CHARSET', 'utf8mb4');

// Set your actual Google Maps JavaScript API key here (key string only, no extra params)
define('GOOGLE_MAPS_KEY', '');

function get_db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            DB_HOST,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => false,
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('[DB CONNECTION ERROR] ' . $e->getMessage());
            die('A database error occurred. Please try again later.');
        }
    }

    return $pdo;
}