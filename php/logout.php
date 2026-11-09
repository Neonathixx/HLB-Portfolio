<?php
/**
 * Log out and return to the login page with a confirmation.
 */
declare(strict_types=1);

require __DIR__ . '/auth.php';

hlb_logout();
header('Location: login.php?loggedout=1');
exit;
