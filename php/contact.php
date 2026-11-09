<?php
/**
<<<<<<< HEAD
 * Sign-up handler + sign-up page.
 *
 * The form on page2.html posts here. On success we redirect to login.php so
 * the person lands somewhere useful. On failure we re-render the form with
 * their answers still filled in and every problem listed, instead of sending
 * them "back" to an empty form.
 *
 * Note on CSRF: page2.html is a static file, so it can't print a PHP token.
 * main.js fetches one from csrf-token.php and injects it. The token is
 * verified whenever it is present; a forged sign-up only creates an account
 * the attacker already controls, so it isn't worth blocking JS-less visitors
 * over. Rename page2.html to page2.php and print hlb_csrf_field() in the form
 * if you want it strict — the check below needs no changes.
 */
declare(strict_types=1);

require __DIR__ . '/connect.php';     // $pdo
require __DIR__ . '/_partials.php';   // auth.php + page chrome

hlb_session_start();

$errors = [];
$values = ['first_name' => '', 'last_name' => '', 'username' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Honeypot: real people leave this empty. Bots that fill it get the same
    // page a success would show, so they learn nothing.
    if (!empty($_POST['website_url'])) {
        header('Location: login.php?registered=1');
        exit;
    }

    if (isset($_POST['csrf_token']) && !hlb_csrf_valid($_POST['csrf_token'])) {
        $errors[] = "Your session expired. Please submit the form again.";
    }

    $values['first_name'] = hlb_clean_line((string)($_POST['first_name'] ?? ''));
    $values['last_name']  = hlb_clean_line((string)($_POST['last_name'] ?? ''));
    $values['username']   = trim((string)($_POST['username'] ?? ''));
    $values['email']      = strtolower(hlb_clean_line((string)($_POST['email'] ?? '')));
    $password             = (string)($_POST['password'] ?? '');
    $confirm              = (string)($_POST['confirm_password'] ?? '');

    foreach ([['first_name', 'first name'], ['last_name', 'last name']] as [$field, $label]) {
        if ($error = hlb_validate_name($values[$field], $label)) {
            $errors[] = $error;
        }
    }
    if ($error = hlb_validate_username($values['username'])) {
        $errors[] = $error;
    }
    if ($error = hlb_validate_email($values['email'])) {
        $errors[] = $error;
    }

    $errors = array_merge($errors, hlb_validate_password($password, $confirm, [
        $values['username'],
        $values['first_name'],
        $values['last_name'],
        strstr($values['email'], '@', true) ?: '',
    ]));

    $display_name = hlb_clean_line($values['first_name'] . ' ' . $values['last_name']);

    if (!$errors) {
        // Case-insensitive duplicate check: "Yam" and "yam" read as the same
        // person, even though a UNIQUE index would happily allow both.
        $stmt = $pdo->prepare(
            "SELECT username, email FROM users
             WHERE LOWER(username) = LOWER(:username) OR LOWER(email) = LOWER(:email)
             LIMIT 2"
        );
        $stmt->execute(['username' => $values['username'], 'email' => $values['email']]);

        foreach ($stmt->fetchAll() as $taken) {
            if (strcasecmp($taken['username'], $values['username']) === 0) {
                $errors[] = "That username is already taken.";
            }
            if (strcasecmp($taken['email'], $values['email']) === 0) {
                $errors[] = "An account with that email already exists. Try logging in instead.";
            }
        }
    }

    if (!$errors) {
        try {
=======
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

>>>>>>> 8f6bbb412ed461e6be0f2d46975047d64b6d4bc0
            $stmt = $pdo->prepare(
                "INSERT INTO users (display_name, username, email, password_hash)
                 VALUES (:display_name, :username, :email, :password_hash)"
            );
<<<<<<< HEAD
            $stmt->execute([
                'display_name'  => mb_substr($display_name, 0, 60),
                'username'      => $values['username'],
                'email'         => $values['email'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);

            // Rotate the token so the same submission can't be replayed.
            unset($_SESSION['csrf_token']);
            header('Location: login.php?registered=1&u=' . urlencode($values['username']));
            exit;
        } catch (\PDOException $e) {
            // Two people submitting the same username at the same moment still
            // hit the UNIQUE index — the data stays correct, so just say so.
            error_log("Registration insert failed: " . $e->getMessage());
            $errors[] = "That username or email was just registered. Please try another.";
        }
    }
}

hlb_header('Create an account', 'about', null);
?>

    <main>
        <div class="Auth-Wrap">
            <div class="Auth-Card">
                <h1>Create an account</h1>
                <p class="Auth-Sub">Register for updates from Yam, or to collaborate on game development, backend architectures, or game scoring.</p>

                <?php hlb_alert($errors); ?>

                <form class="Auth-Form" id="registerForm" action="contact.php" method="POST" novalidate>
                    <?= hlb_csrf_field() ?>
                    <div class="honeypot-field" aria-hidden="true">
                        <label for="website_url">Leave this empty</label>
                        <input type="text" name="website_url" id="website_url" tabindex="-1" autocomplete="off">
                    </div>

                    <div class="Form-Row">
                        <div class="Form-Group">
                            <label for="first_name">First name</label>
                            <input type="text" id="first_name" name="first_name" class="Form-Control"
                                value="<?= e($values['first_name']) ?>" autocomplete="given-name"
                                maxlength="<?= HLB_NAME_MAX ?>" required>
                        </div>
                        <div class="Form-Group">
                            <label for="last_name">Last name</label>
                            <input type="text" id="last_name" name="last_name" class="Form-Control"
                                value="<?= e($values['last_name']) ?>" autocomplete="family-name"
                                maxlength="<?= HLB_NAME_MAX ?>" required>
                        </div>
                    </div>

                    <div class="Form-Group">
                        <label for="username">Username</label>
                        <input type="text" id="username" name="username" class="Form-Control"
                            value="<?= e($values['username']) ?>" autocomplete="username"
                            pattern="[A-Za-z][A-Za-z0-9_]{<?= HLB_USERNAME_MIN - 1 ?>,<?= HLB_USERNAME_MAX - 1 ?>}"
                            maxlength="<?= HLB_USERNAME_MAX ?>" required>
                        <p class="Field-Hint">Starts with a letter. Letters, numbers and underscores, <?= HLB_USERNAME_MIN ?>&ndash;<?= HLB_USERNAME_MAX ?> characters.</p>
                    </div>

                    <div class="Form-Group">
                        <label for="email">Email address</label>
                        <input type="email" id="email" name="email" class="Form-Control"
                            value="<?= e($values['email']) ?>" placeholder="juan@example.com"
                            autocomplete="email" maxlength="<?= HLB_EMAIL_MAX ?>" required>
                    </div>

                    <div class="Form-Group">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" class="Form-Control"
                            autocomplete="new-password" minlength="<?= HLB_PASSWORD_MIN ?>"
                            maxlength="<?= HLB_PASSWORD_MAX ?>" required>
                        <div class="Pw-Meter" id="pwMeter" data-score="0" aria-hidden="true"><span></span></div>
                        <p class="Field-Hint" id="pwLabel">At least <?= HLB_PASSWORD_MIN ?> characters, with upper and lower case and a number.</p>
                    </div>

                    <div class="Form-Group">
                        <label for="confirm">Confirm password</label>
                        <input type="password" id="confirm" name="confirm_password" class="Form-Control"
                            autocomplete="new-password" minlength="<?= HLB_PASSWORD_MIN ?>"
                            maxlength="<?= HLB_PASSWORD_MAX ?>" required>
                        <p class="Field-Hint" id="confirmHint" aria-live="polite"></p>
                    </div>

                    <button type="submit" class="Form-Submit-Btn Auth-Submit">Create account</button>
                </form>

                <p class="Auth-Footer">Already registered? <a href="login.php">Log in</a></p>
            </div>
        </div>
    </main>

<?php hlb_footer(); ?>
=======
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
>>>>>>> 8f6bbb412ed461e6be0f2d46975047d64b6d4bc0
