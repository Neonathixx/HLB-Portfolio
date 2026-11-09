<?php
/**
 * Hands main.js a CSRF token so the forms on the static .html pages can send
 * one. Same-origin only: the session cookie is SameSite=Lax, so another site
 * can't read the response, and the token is useless without the cookie.
 */
declare(strict_types=1);

require __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

echo json_encode(['token' => hlb_csrf_token()]);
