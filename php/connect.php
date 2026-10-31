<?php
/**
 * Shared database connection. Included by any PHP page that needs $pdo
 * (contact.php, and later login.php / account.php).
 *
 * Swap these for environment variables before deploying to Hostinger —
 * never commit real production credentials to a public repo.
 */
declare(strict_types=1);

$dsn      = "mysql:host=localhost;dbname=hlb_portfolio;charset=utf8mb4";
$username = "root";
$password = "";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $username, $password, $options);
} catch (\PDOException $e) {
    // Never echo $e->getMessage() to the browser on a live site — it can
    // reveal your DB host/user. Logging it server-side is safer.
    error_log("DB connection failed: " . $e->getMessage());
    http_response_code(500);
    die("We're having trouble connecting right now. Please try again later.");
}