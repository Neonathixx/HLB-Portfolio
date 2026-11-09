<?php
/**
 * Log-in page and handler.
 *
 * Verification and constraints applied here:
 *  - sign in with either the username or the email address
 *  - CSRF token required (this page prints its own, so no exceptions)
 *  - the same message for an unknown account and a wrong password, so the
 *    form can't be used to discover which emails are registered
 *  - every attempt written to login_attempts; HLB_MAX_ATTEMPTS failures
 *    inside HLB_LOCKOUT_WINDOW locks further tries, matched on identifier
 *    OR IP so neither spraying one account nor one IP gets through
 *  - deactivated accounts (is_active = 0) are refused
 *  - the session id is replaced on success (session fixation)
 *  - the stored hash is upgraded if PHP's default algorithm has moved on
 *  - ?next= only ever returns to a local file, never an outside URL
 */
declare(strict_types=1);

require __DIR__ . '/connect.php';     // $pdo
require __DIR__ . '/_partials.php';   // auth.php + page chrome

hlb_session_start();

// Already signed in? Nothing to do here.
if (hlb_current_user($pdo) !== null) {
    header('Location: profile.php');
    exit;
}

$errors   = [];
$notices  = [];
$identifier = '';

// Only ever redirect to a plain filename in this folder.
$next = basename((string)($_GET['next'] ?? $_POST['next'] ?? ''));
if ($next === '' || !preg_match('/^[A-Za-z0-9_-]+\.php$/', $next) || $next === 'login.php') {
    $next = 'profile.php';
}

if (isset($_GET['registered'])) {
    $notices[]  = "Your account is ready. Log in to continue.";
    $identifier = hlb_clean_line((string)($_GET['u'] ?? ''));
}
if (isset($_GET['loggedout'])) {
    $notices[] = "You're logged out.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hlb_csrf_valid($_POST['csrf_token'] ?? null)) {
        $errors[] = "Your session expired. Please try again.";
    }

    $identifier = hlb_clean_line((string)($_POST['identifier'] ?? ''));
    $password   = (string)($_POST['password'] ?? '');

    if ($identifier === '' || $password === '') {
        $errors[] = "Enter your username or email and your password.";
    }

    // Throttle before touching the password, so a locked-out attacker gets
    // no timing signal about whether the account exists.
    $wait = $errors ? 0 : hlb_lockout_seconds($pdo, strtolower($identifier));
    if ($wait > 0) {
        $errors[] = "Too many failed attempts. Try again in " . hlb_humanize_seconds($wait) . ".";
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            "SELECT id, display_name, username, email, password_hash, role, is_active
             FROM users
             WHERE LOWER(username) = LOWER(:identifier) OR LOWER(email) = LOWER(:identifier)
             LIMIT 1"
        );
        $stmt->execute(['identifier' => $identifier]);
        $user = $stmt->fetch();

        // Always run a hash comparison, even with no such user, so both paths
        // take about the same time.
        $dummy = '$2y$12$usesomesillystringfooobarbazquuxWfRbDFFbeEu5J0o4OQSRZjy9m';
        $valid = password_verify($password, $user ? $user['password_hash'] : $dummy);

        if (!$user || !$valid) {
            hlb_record_attempt($pdo, strtolower($identifier), false);
            $errors[] = "Incorrect username, email or password.";

            // If that failure was the one that tripped the lockout, say so now
            // rather than letting the next attempt arrive as a surprise.
            $wait_now = hlb_lockout_seconds($pdo, strtolower($identifier));
            if ($wait_now > 0) {
                $errors[] = "That was the last attempt. Try again in " . hlb_humanize_seconds($wait_now) . ".";
            }
        } elseif ((int)$user['is_active'] !== 1) {
            hlb_record_attempt($pdo, strtolower($identifier), false);
            $errors[] = "This account is deactivated. Contact Yam if you think that's wrong.";
        } else {
            // Re-hash if PHP's default cost or algorithm has changed since
            // this password was set.
            if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
                $update = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
                $update->execute([
                    'hash' => password_hash($password, PASSWORD_DEFAULT),
                    'id'   => $user['id'],
                ]);
            }

            $pdo->prepare("UPDATE users SET last_login_at = CURRENT_TIMESTAMP WHERE id = :id")
                ->execute(['id' => $user['id']]);

            hlb_record_attempt($pdo, strtolower($identifier), true);
            hlb_login_user($user);

            header('Location: ' . $next);
            exit;
        }
    }
}

hlb_header('Log in', 'profile', null);
?>

    <main>
        <div class="Auth-Wrap">
            <div class="Auth-Card">
                <h1>Log in</h1>
                <p class="Auth-Sub">Use the username or the email address you registered with.</p>

                <?php hlb_alert($notices, 'success'); ?>
                <?php hlb_alert($errors); ?>

                <form class="Auth-Form" id="loginForm" action="login.php" method="POST" novalidate>
                    <?= hlb_csrf_field() ?>
                    <input type="hidden" name="next" value="<?= e($next) ?>">

                    <div class="Form-Group">
                        <label for="identifier">Username or email</label>
                        <input type="text" id="identifier" name="identifier" class="Form-Control"
                            value="<?= e($identifier) ?>" autocomplete="username"
                            maxlength="<?= HLB_EMAIL_MAX ?>" autofocus required>
                    </div>

                    <div class="Form-Group">
                        <label for="password">Password</label>
                        <div class="Pw-Field">
                            <input type="password" id="password" name="password" class="Form-Control"
                                autocomplete="current-password" maxlength="<?= HLB_PASSWORD_MAX ?>" required>
                            <button type="button" class="Pw-Toggle" data-pw-toggle="password"
                                aria-controls="password" aria-pressed="false">Show</button>
                        </div>
                    </div>

                    <button type="submit" class="Form-Submit-Btn Auth-Submit">Log in</button>
                </form>

                <p class="Auth-Footer">No account yet? <a href="contact.php">Create one</a></p>
            </div>
        </div>
    </main>

<?php hlb_footer(); ?>
