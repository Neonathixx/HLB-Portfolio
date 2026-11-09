<?php
/**
 * Shared database connection. Included by any PHP page that needs $pdo
<<<<<<< HEAD
 * (contact.php, login.php, profile.php, newsletter.php).
 *
 * Credentials come from environment variables when those are set, so the real
 * ones can stay out of this file and out of the repo on Hostinger. The values
 * after `?:` are the local XAMPP/MAMP defaults.
 */
declare(strict_types=1);

$dsn      = getenv('DB_DSN')  ?: "mysql:host=localhost;dbname=hlb_portfolio;charset=utf8mb4";
$username = getenv('DB_USER') ?: "root";
$password = getenv('DB_PASS') ?: "";
=======
 * (contact.php, and later login.php / account.php).
 *
 * Swap these for environment variables before deploying to Hostinger —
 * never commit real production credentials to a public repo.
 */
declare(strict_types=1);

$dsn      = "mysql:host=localhost;dbname=hlb_portfolio;charset=utf8mb4";
$username = "root";
$password = "";
>>>>>>> 8f6bbb412ed461e6be0f2d46975047d64b6d4bc0

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