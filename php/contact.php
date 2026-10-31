<?php
/**
 * Registration handler for the "CREATE AN ACCOUNT" form on page2.html.
 * Validates input, hashes the password, and inserts a row into `users`
 * (see sql/portfolio.sql). Renders a simple success/error page in the
 * site's own style — no JS required, matches the form's plain POST.
 */
declare(strict_types=1);

require "connect.php"; // provides $pdo

$errors = [];
$ok = false;

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    $errors[] = "This page only accepts form submissions.";
} else {
    // Honeypot: real visitors never fill this in. Bots that do get a
    // silent "success" redirect so they don't learn the field is a trap.
    if (!empty($_POST["website_url"])) {
        header("Location: page2.html");
        exit;
    }

    $first_name = trim((string)($_POST["first_name"] ?? ""));
    $last_name  = trim((string)($_POST["last_name"] ?? ""));
    $username   = trim((string)($_POST["username"] ?? ""));
    $email      = trim((string)($_POST["email"] ?? ""));
    $password   = (string)($_POST["password"] ?? "");
    $confirm    = (string)($_POST["confirm_password"] ?? "");

    if ($first_name === "" || $last_name === "") {
        $errors[] = "Please enter your first and last name.";
    }
    if (!preg_match('/^[A-Za-z0-9_]{3,32}$/', $username)) {
        $errors[] = "Username must be 3-32 characters: letters, numbers, or underscore.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters.";
    }
    if ($password !== $confirm) {
        $errors[] = "Passwords do not match.";
    }

    if (!$errors) {
        // Check for an existing username or email before inserting.
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1");
        $stmt->execute(["username" => $username, "email" => $email]);

        if ($stmt->fetch()) {
            $errors[] = "That username or email is already registered.";
        } else {
            $display_name = trim($first_name . " " . $last_name);
            $password_hash = password_hash($password, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                "INSERT INTO users (display_name, username, email, password_hash)
                 VALUES (:display_name, :username, :email, :password_hash)"
            );
            try {
                $stmt->execute([
                    "display_name"  => $display_name,
                    "username"      => $username,
                    "email"         => $email,
                    "password_hash" => $password_hash,
                ]);
                $ok = true;
            } catch (\PDOException $e) {
                // Covers a duplicate slipping in from a race condition
                // (unique keys on username/email still protect the data).
                error_log("Registration insert failed: " . $e->getMessage());
                $errors[] = "That username or email is already registered.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $ok ? "Welcome" : "Registration" ?> | Bartolome</title>
    <link rel="stylesheet" href="../css/style.css">
</head>

<body>
    <header class="Header">
        <div class="Header-Container">
            <nav class="Navigation-Bar" aria-label="Main Navigation">
                <a href="index.html" class="NavButton"><p>Home</p></a>
                <a href="page2.html" class="NavButton"><p>About Me</p></a>
                <a href="page3.html" class="NavButton"><p>Projects</p></a>
            </nav>
        </div>
    </header>

    <main>
        <section class="Title-Card">
            <h1><?= $ok ? "THANK YOU!" : "OOPS" ?></h1>
            <h2>
                <?php if ($ok): ?>
                    Your account was created. You can now log in.
                <?php else: ?>
                    We couldn't create your account. Please go back and try again.
                <?php endif; ?>
            </h2>

            <?php if ($errors): ?>
                <div class="Auth-Alert error" style="margin-top:1.5rem; text-align:left;">
                    <ul style="margin:0; padding-left:1.1rem;">
                        <?php foreach ($errors as $error): ?>
                            <li><?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <div class="Hero-Actions">
                <a class="Btn" href="page2.html<?= $ok ? "" : "#contact" ?>">Back</a>
            </div>
        </section>
    </main>

    <footer class="Footer">
        <p>&copy; 2026 Hiam Louis Bartolome. All Rights Reserved.</p>
    </footer>
</body>
</html>