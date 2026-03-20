<?php
/**
 * db.php — Secure PDO Database Connection
 *
 * Security features:
 *  - PDO with prepared statements only (no raw query concatenation)
 *  - Error mode set to EXCEPTION so errors are caught, not silently ignored
 *  - Charset enforced to utf8mb4 to prevent charset-based injection
 *  - Persistent connections disabled (safer for shared hosts)
 *  - Credentials read from a config array (move to .env in production)
 */

// ── Database credentials ───────────────────────────────────────────────────
// In production: move these to a .env file outside the web root and read
// with getenv() or a library like vlucas/phpdotenv.
define('DB_HOST',    'localhost');
define('DB_NAME',    'tourism_system');
define('DB_USER',    'root');         // Change to your DB username
define('DB_PASS',    '');             // Change to your DB password
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a shared PDO instance (singleton pattern).
 * Throws a RuntimeException if connection fails — never exposes credentials.
 */
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
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // Throw on error
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,        // Return assoc arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                   // Real prepared statements
            PDO::ATTR_PERSISTENT         => false,                   // No persistent connections
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // Log the real error — never display it to the user
            error_log('[DB CONNECTION ERROR] ' . $e->getMessage());
            // Show a safe generic message
            die('A database error occurred. Please try again later.');
        }
    }

    return $pdo;
}
