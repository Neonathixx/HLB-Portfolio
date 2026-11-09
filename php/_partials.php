<?php
/**
 * Shared page chrome for the PHP pages in this folder.
 *
 * The static .html pages get their signed-in nav from main.js. These PHP
 * pages already know who the visitor is, so they render it server-side —
 * no flash of the wrong state while JS loads.
 */
declare(strict_types=1);

require_once __DIR__ . '/auth.php';

/**
 * @param string     $title  goes in <title>
 * @param string     $active which nav item is current: home|about|projects|newsletter|profile
 * @param array|null $user   from hlb_current_user(), or null when signed out
 */
function hlb_header(string $title, string $active = '', ?array $user = null): void
{
    $nav = [
        'home'       => ['../index.html', 'Home'],
        'about'      => ['../page2.html', 'About Me'],
        'projects'   => ['../page3.html', 'Projects'],
        'newsletter' => ['../page4.html', 'Newsletter'],
    ];
    ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?> | Bartolome</title>
    <link rel="stylesheet" href="../style.css">
</head>

<body>
    <header class="Header">
        <div class="Header-Container">
            <nav class="Navigation-Bar" aria-label="Main Navigation">
                <?php foreach ($nav as $key => [$href, $label]): ?>
                <a href="<?= e($href) ?>" class="NavButton<?= $active === $key ? ' active' : '' ?>">
                    <p><?= e($label) ?></p>
                </a>
                <?php endforeach; ?>

                <?php if ($user !== null): ?>
                <div class="NavAuth-Group">
                    <a href="profile.php" class="NavButton NavAccount<?= $active === 'profile' ? ' active' : '' ?>" title="My account">
                        <span class="NavAvatar" aria-hidden="true"><?= e(mb_strtoupper(mb_substr($user['display_name'] ?: $user['username'], 0, 1))) ?></span>
                        <p><?= e($user['display_name'] ?: $user['username']) ?></p>
                    </a>
                    <a href="logout.php" class="NavButton NavLogout" title="Log out">
                        <p>Log Out</p>
                    </a>
                </div>
                <?php else: ?>
                <a href="login.php" id="navLogin" class="NavButton NavButtonDistinct<?= $active === 'profile' ? ' active' : '' ?>">
                    <p>Log In</p>
                </a>
                <?php endif; ?>
            </nav>
        </div>
    </header>
<?php
}

function hlb_footer(): void
{
    ?>
    <footer class="Footer">
        <p>&copy; 2026 Hiam Louis Bartolome. All Rights Reserved.</p>
    </footer>
    <script src="../main.js"></script>
</body>

</html>
<?php
}

/** Render a list of problems in the site's alert style. */
function hlb_alert(array $messages, string $kind = 'error'): void
{
    if (!$messages) {
        return;
    }
    ?>
    <div class="Auth-Alert <?= e($kind) ?>" role="alert">
        <?php if (count($messages) === 1): ?>
            <?= e($messages[0]) ?>
        <?php else: ?>
        <ul class="Auth-Alert-List">
            <?php foreach ($messages as $message): ?>
            <li><?= e($message) ?></li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
<?php
}
