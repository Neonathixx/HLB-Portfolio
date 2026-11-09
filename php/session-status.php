<?php
/**
 * Tells main.js whether the visitor is signed in, so the static .html pages
 * can show the account/log-out nav. Returns only what the nav needs — never
 * the email, role or anything else a page doesn't display.
 */
declare(strict_types=1);

require __DIR__ . '/connect.php';
require __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

$user = hlb_current_user($pdo);

echo json_encode($user === null
    ? ['authenticated' => false]
    : [
        'authenticated' => true,
        'display_name'  => $user['display_name'],
        'username'      => $user['username'],
    ]
);
