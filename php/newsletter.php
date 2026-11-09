<?php
/**
 * Newsletter subscribe handler for the classified box on page4.html,
 * and the confirm link target for double opt-in.
 *
 * Constraints:
 *  - honeypot + CSRF (when main.js supplied a token)
 *  - email validated and lowercased before it reaches the UNIQUE index
 *  - re-subscribing after unsubscribing reactivates the existing row rather
 *    than failing on the unique email
 *  - an already-confirmed address gets the same reply as a new one, so the
 *    box can't be used to test which addresses are on the list
 *  - the confirm token is compared with hash_equals via a lookup, never
 *    echoed back into the page
 */
declare(strict_types=1);

require __DIR__ . '/connect.php';     // $pdo
require __DIR__ . '/_partials.php';   // auth.php + page chrome

hlb_session_start();

$errors  = [];
$heading = '';
$message = '';

/** Confirm link: newsletter.php?confirm=<token> */
if (isset($_GET['confirm'])) {
    $token = (string)$_GET['confirm'];

    if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
        $errors[]  = "That confirmation link isn't valid.";
        $heading   = "Link not recognised";
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, confirmed FROM newsletter_subscribers WHERE confirm_token = :token LIMIT 1"
        );
        $stmt->execute(['token' => $token]);
        $row = $stmt->fetch();

        if (!$row) {
            $errors[] = "That confirmation link has already been used, or it expired.";
            $heading  = "Link not recognised";
        } else {
            $pdo->prepare(
                "UPDATE newsletter_subscribers
                 SET confirmed = 1, confirmed_at = CURRENT_TIMESTAMP,
                     confirm_token = NULL, unsubscribed_at = NULL
                 WHERE id = :id"
            )->execute(['id' => $row['id']]);

            $heading = "You're on the list";
            $message = "Your subscription to The Aisle is confirmed. The next issue will arrive in your inbox.";
        }
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_POST['website_url'])) {
        // Bot. Show the same page a real subscriber sees.
        $heading = "Check your inbox";
        $message = "Confirm your address using the link we sent, and you're on the list.";
    } else {
        if (isset($_POST['csrf_token']) && !hlb_csrf_valid($_POST['csrf_token'])) {
            $errors[] = "Your session expired. Please submit the form again.";
        }

        $email = strtolower(hlb_clean_line((string)($_POST['email'] ?? '')));
        $name  = hlb_clean_line((string)($_POST['display_name'] ?? ''));

        if ($error = hlb_validate_email($email)) {
            $errors[] = $error;
        }
        if ($name !== '' && ($error = hlb_validate_name($name, 'name'))) {
            $errors[] = $error;
        }

        if (!$errors) {
            try {
                // Link the subscription to an account when the email matches one.
                $stmt = $pdo->prepare("SELECT id FROM users WHERE LOWER(email) = LOWER(:email) LIMIT 1");
                $stmt->execute(['email' => $email]);
                $user_row = $stmt->fetch();
                $user_id  = $user_row ? (int)$user_row['id'] : null;

                $stmt = $pdo->prepare(
                    "SELECT id, confirmed FROM newsletter_subscribers
                     WHERE LOWER(email) = LOWER(:email) LIMIT 1"
                );
                $stmt->execute(['email' => $email]);
                $existing = $stmt->fetch();

                $token = bin2hex(random_bytes(32));

                if ($existing) {
                    // Already confirmed: leave the row alone. Previously
                    // unsubscribed, or never confirmed: issue a fresh token.
                    if ((int)$existing['confirmed'] !== 1) {
                        // Keep the name already on file when this submission
                        // didn't include one — re-subscribing shouldn't erase it.
                        $update = $pdo->prepare(
                            "UPDATE newsletter_subscribers
                             SET display_name = COALESCE(:name, display_name),
                                 user_id = :user_id,
                                 confirm_token = :token, unsubscribed_at = NULL,
                                 subscribed_at = CURRENT_TIMESTAMP
                             WHERE id = :id"
                        );
                        $update->bindValue(':name', $name !== '' ? mb_substr($name, 0, 60) : null,
                            $name !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
                        $update->bindValue(':user_id', $user_id,
                            $user_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                        $update->bindValue(':token', $token);
                        $update->bindValue(':id', $existing['id'], PDO::PARAM_INT);
                        $update->execute();
                    }
                } else {
                    $insert = $pdo->prepare(
                        "INSERT INTO newsletter_subscribers
                            (email, display_name, user_id, confirm_token, source, ip_address)
                         VALUES (:email, :name, :user_id, :token, 'page4', :ip)"
                    );
                    $insert->bindValue(':email', $email);
                    $insert->bindValue(':name', $name !== '' ? mb_substr($name, 0, 60) : null,
                        $name !== '' ? PDO::PARAM_STR : PDO::PARAM_NULL);
                    $insert->bindValue(':user_id', $user_id, $user_id === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
                    $insert->bindValue(':token', $token);
                    $insert->bindValue(':ip', hlb_client_ip_binary(), PDO::PARAM_LOB);
                    $insert->execute();
                }

                // Double opt-in: mail this link to $email to activate the row.
                // Until then `confirmed` stays 0 and the address isn't mailed.
                //   $link = "https://your-domain/php/newsletter.php?confirm={$token}";
                //   mail($email, "Confirm your subscription to The Aisle", $link);
                //
                // Prefer an SMTP library (PHPMailer) over mail() on Hostinger —
                // bare mail() is usually filtered as spam.

                unset($_SESSION['csrf_token']);
                $heading = "Check your inbox";
                $message = "Confirm your address using the link we sent, and you're on the list.";
            } catch (\PDOException $e) {
                error_log("Newsletter subscribe failed: " . $e->getMessage());
                $errors[] = "We couldn't save your subscription just now. Please try again later.";
            }
        }
    }
} else {
    $heading  = "Nothing to see here";
    $message  = "Use the subscribe box on the newsletter page.";
}

if ($errors) {
    $heading = $heading !== '' ? $heading : "That didn't work";
}

$user = hlb_current_user($pdo);
hlb_header($heading !== '' ? $heading : 'Newsletter', 'newsletter', $user);
?>

    <main>
        <div class="Auth-Wrap">
            <div class="Auth-Card">
                <h1><?= e($heading) ?></h1>

                <?php hlb_alert($errors); ?>

                <?php if ($message !== ''): ?>
                <p class="Auth-Sub"><?= e($message) ?></p>
                <?php endif; ?>

                <div class="Profile-Actions">
                    <a class="Btn" href="../page4.html">Back to the newsletter</a>
                    <?php if ($errors): ?>
                    <a class="Btn secondary" href="../page4.html#subscribe">Try again</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </main>

<?php hlb_footer(); ?>
