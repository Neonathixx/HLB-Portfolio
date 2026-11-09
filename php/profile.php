<?php
/**
 * Profile — the signed-in member's own account page.
 *
 * Replaces the old page5.html placeholder. Anyone not signed in is sent to
 * login.php, which bounces them back here once they are.
 */
declare(strict_types=1);

require __DIR__ . '/connect.php';     // $pdo
require __DIR__ . '/_partials.php';   // auth.php + page chrome

$user = hlb_require_login($pdo);

/** "4 November 2026" / "never", from a DB datetime. */
function hlb_date(?string $value): string
{
    if (!$value) {
        return 'Never';
    }
    try {
        return (new DateTimeImmutable($value))->format('j F Y, g:ia');
    } catch (\Exception $e) {
        return $value;
    }
}

// Is this member on the newsletter list? Three states worth telling apart:
// confirmed, signed up but not yet confirmed, and not on the list at all.
$news_state = 'none';
try {
    $stmt = $pdo->prepare(
        "SELECT confirmed FROM newsletter_subscribers
         WHERE LOWER(email) = LOWER(:email) AND unsubscribed_at IS NULL LIMIT 1"
    );
    $stmt->execute(['email' => $user['email']]);
    $row = $stmt->fetch();
    if ($row) {
        $news_state = (int)$row['confirmed'] === 1 ? 'confirmed' : 'pending';
    }
} catch (\PDOException $e) {
    // The newsletter table may not be imported yet — not worth an error page.
    error_log("Newsletter lookup failed: " . $e->getMessage());
}

$news_label = [
    'confirmed' => 'Subscribed to The Aisle',
    'pending'   => 'Awaiting email confirmation',
    'none'      => 'Not subscribed',
][$news_state];

$initial = mb_strtoupper(mb_substr($user['display_name'] ?: $user['username'], 0, 1));

hlb_header('Profile', 'profile', $user);
?>

    <main>
        <div class="Auth-Wrap">
            <div class="Auth-Card Profile-Card">
                <div class="Profile-Head">
                    <span class="Profile-Avatar" aria-hidden="true"><?= e($initial) ?></span>
                    <div>
                        <h1 class="Profile-Name"><?= e($user['display_name']) ?></h1>
                        <p class="Profile-Handle">@<?= e($user['username']) ?></p>
                    </div>
                </div>

                <dl class="Account-Info">
                    <div class="Account-Row">
                        <dt>Email</dt>
                        <dd><?= e($user['email']) ?></dd>
                    </div>
                    <div class="Account-Row">
                        <dt>Membership</dt>
                        <dd><?= $user['role'] === 'admin' ? 'Administrator' : 'Member' ?></dd>
                    </div>
                    <div class="Account-Row">
                        <dt>Joined</dt>
                        <dd><?= e(hlb_date($user['created_at'])) ?></dd>
                    </div>
                    <div class="Account-Row">
                        <dt>Last login</dt>
                        <dd><?= e(hlb_date($user['last_login_at'])) ?></dd>
                    </div>
                    <div class="Account-Row">
                        <dt>Newsletter</dt>
                        <dd><?= e($news_label) ?></dd>
                    </div>
                </dl>

                <div class="Profile-Actions">
                    <?php if ($news_state === 'none'): ?>
                    <a class="Btn" href="../page4.html#subscribe">Subscribe to The Aisle</a>
                    <?php else: ?>
                    <a class="Btn" href="../page4.html">Read the latest issue</a>
                    <?php endif; ?>
                    <a class="Btn secondary" href="logout.php">Log out</a>
                </div>
            </div>
        </div>
    </main>

<?php hlb_footer(); ?>
