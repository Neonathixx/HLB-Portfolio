<?php
/**
 * HLB PORTFOLIO — shared authentication helpers.
 *
 * Everything that both sign-up (contact.php) and log-in (login.php) need:
 * session setup, CSRF tokens, the field rules, and brute-force throttling.
 * Keeping the rules in one file means the two forms can never drift apart.
 */
declare(strict_types=1);

// ---------------------------------------------------------------------------
// Rules, all in one place so the forms, the JS and the server agree.
// ---------------------------------------------------------------------------
const HLB_NAME_MIN      = 2;
const HLB_NAME_MAX      = 30;   // display_name is VARCHAR(60) = first + last
const HLB_USERNAME_MIN  = 3;
const HLB_USERNAME_MAX  = 32;   // matches users.username
const HLB_EMAIL_MAX     = 190;  // matches users.email
const HLB_PASSWORD_MIN  = 8;
const HLB_PASSWORD_MAX  = 72;   // bcrypt silently ignores bytes past 72

// Throttling: this many failures inside the window locks further attempts.
const HLB_MAX_ATTEMPTS   = 5;
const HLB_LOCKOUT_WINDOW = 900; // seconds (15 minutes)

// Usernames that would be confusing or impersonating.
const HLB_RESERVED_USERNAMES = [
    'admin', 'administrator', 'root', 'superuser', 'moderator', 'mod',
    'support', 'help', 'staff', 'system', 'security', 'webmaster',
    'postmaster', 'hostmaster', 'login', 'logout', 'register', 'signup',
    'account', 'profile', 'newsletter', 'null', 'undefined', 'me',
    'hlb', 'bartolome', 'aislefront',
];

// A password equal to any of these is rejected outright. A short list of the
// passwords that actually dominate breach dumps — not a substitute for the
// length and variety rules below, just a floor.
const HLB_WEAK_PASSWORDS = [
    'password', 'password1', 'password123', 'passw0rd', '12345678', '123456789',
    '1234567890', 'qwerty123', 'qwertyuiop', 'iloveyou', 'letmein', 'welcome1',
    'admin123', 'abc12345', 'football', 'baseball', 'sunshine', 'princess',
    'dragon123', 'monkey123', 'changeme', 'trustno1', 'starwars',
];

// ---------------------------------------------------------------------------
// Session
// ---------------------------------------------------------------------------

/**
 * Start a session with cookie flags that are safe by default.
 * Call this before any output on every page that reads or writes $_SESSION.
 */
function hlb_session_start(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

    session_set_cookie_params([
        'lifetime' => 0,          // until the browser closes
        'path'     => '/',
        'httponly' => true,       // JS can't read the session cookie
        'samesite' => 'Lax',      // not sent on cross-site POSTs
        'secure'   => $https,     // HTTPS-only once you're on Hostinger
    ]);

    session_start();
}

// ---------------------------------------------------------------------------
// CSRF
// ---------------------------------------------------------------------------

/** The token for this session, created on first use. */
function hlb_csrf_token(): string
{
    hlb_session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/** A ready-made hidden input for any form that POSTs. */
function hlb_csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="'
        . htmlspecialchars(hlb_csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}

/** True when the submitted token matches the session's. */
function hlb_csrf_valid(?string $submitted): bool
{
    hlb_session_start();
    return is_string($submitted)
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $submitted);
}

// ---------------------------------------------------------------------------
// Current user
// ---------------------------------------------------------------------------

/** The signed-in user's row, or null. Re-read each time so a deactivated
 *  account stops working immediately instead of at next login. */
function hlb_current_user(PDO $pdo): ?array
{
    hlb_session_start();
    if (empty($_SESSION['user_id'])) {
        return null;
    }

    $stmt = $pdo->prepare(
        "SELECT id, display_name, username, email, role, is_active,
                created_at, last_login_at
         FROM users WHERE id = :id LIMIT 1"
    );
    $stmt->execute(['id' => (int)$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || (int)$user['is_active'] !== 1) {
        hlb_logout();
        return null;
    }
    return $user;
}

/** Send anyone who isn't signed in to the login page, remembering where
 *  they were headed so login can bounce them back. */
function hlb_require_login(PDO $pdo, string $login_url = 'login.php'): array
{
    $user = hlb_current_user($pdo);
    if ($user === null) {
        $target = $_SERVER['REQUEST_URI'] ?? '';
        $query  = $target !== '' ? '?next=' . urlencode(basename($target)) : '';
        header('Location: ' . $login_url . $query);
        exit;
    }
    return $user;
}

/** Log the current user in, replacing the session id so a stolen
 *  pre-login id is useless (session fixation). */
function hlb_login_user(array $user): void
{
    hlb_session_start();
    session_regenerate_id(true);
    $_SESSION['user_id']    = (int)$user['id'];
    $_SESSION['username']   = $user['username'];
    $_SESSION['logged_in_at'] = time();
}

/** Clear the session and expire its cookie. */
function hlb_logout(): void
{
    hlb_session_start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}

// ---------------------------------------------------------------------------
// Field rules
// ---------------------------------------------------------------------------

/** Collapse whitespace and strip control characters from a one-line field. */
function hlb_clean_line(string $value): string
{
    $value = str_replace(["\r", "\n", "\t"], ' ', $value);
    $value = preg_replace('/[\x00-\x1F\x7F]/u', '', $value) ?? '';
    return trim(preg_replace('/\s+/u', ' ', $value) ?? '');
}

/** A person's name: letters, spaces, hyphens, apostrophes, periods. Deliberately
 *  permissive about non-ASCII letters — plenty of Filipino names carry them. */
function hlb_validate_name(string $name, string $label): ?string
{
    $len = mb_strlen($name);
    if ($len === 0) {
        return "Enter your {$label}.";
    }
    if ($len < HLB_NAME_MIN || $len > HLB_NAME_MAX) {
        return ucfirst($label) . " must be between " . HLB_NAME_MIN . " and " . HLB_NAME_MAX . " characters.";
    }
    if (!preg_match("/^[\p{L}][\p{L}\p{M}' .\-]*$/u", $name)) {
        return ucfirst($label) . " can only contain letters, spaces, hyphens and apostrophes.";
    }
    return null;
}

/** Username: starts with a letter, then letters/digits/underscore. */
function hlb_validate_username(string $username): ?string
{
    if ($username === '') {
        return "Choose a username.";
    }
    if (strlen($username) < HLB_USERNAME_MIN || strlen($username) > HLB_USERNAME_MAX) {
        return "Username must be " . HLB_USERNAME_MIN . "-" . HLB_USERNAME_MAX . " characters.";
    }
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $username)) {
        return "Username must start with a letter and use only letters, numbers and underscores.";
    }
    if (in_array(strtolower($username), HLB_RESERVED_USERNAMES, true)) {
        return "That username is reserved. Please choose another.";
    }
    return null;
}

/** Email: real format, fits the column, and normalised to lowercase. */
function hlb_validate_email(string $email): ?string
{
    if ($email === '') {
        return "Enter your email address.";
    }
    if (strlen($email) > HLB_EMAIL_MAX) {
        return "That email address is too long.";
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return "Enter a valid email address, like juan@example.com.";
    }
    return null;
}

/**
 * Password rules. Returns every failure at once so the person can fix the
 * whole thing in one go instead of discovering one rule per submit.
 *
 * $context holds the username/email so a password that just repeats them
 * can be rejected — the most common way a "valid" password is still useless.
 */
function hlb_validate_password(string $password, string $confirm, array $context = []): array
{
    $errors = [];

    $length = strlen($password); // bytes: bcrypt's limit is in bytes, not chars
    if ($length < HLB_PASSWORD_MIN) {
        $errors[] = "Password must be at least " . HLB_PASSWORD_MIN . " characters.";
    }
    if ($length > HLB_PASSWORD_MAX) {
        $errors[] = "Password must be no more than " . HLB_PASSWORD_MAX . " characters.";
    }
    if ($length > 0 && $length <= HLB_PASSWORD_MAX) {
        if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password needs both an uppercase and a lowercase letter.";
        }
        if (!preg_match('/\d/', $password)) {
            $errors[] = "Password needs at least one number.";
        }
        if (preg_match('/^\s|\s$/', $password)) {
            $errors[] = "Password can't start or end with a space.";
        }
    }
    if (in_array(strtolower($password), HLB_WEAK_PASSWORDS, true)) {
        $errors[] = "That password is too common. Please pick something else.";
    }

    foreach ($context as $needle) {
        $needle = trim((string)$needle);
        if ($needle !== '' && mb_strlen($needle) >= 3
            && stripos($password, $needle) !== false) {
            $errors[] = "Password can't contain your name, username or email.";
            break;
        }
    }

    if ($confirm === '') {
        $errors[] = "Re-enter your password to confirm it.";
    } elseif (!hash_equals($password, $confirm)) {
        $errors[] = "Passwords do not match.";
    }

    return $errors;
}

// ---------------------------------------------------------------------------
// Throttling (uses the login_attempts table)
// ---------------------------------------------------------------------------

/** The caller's IP packed for VARBINARY(16), or a zero byte if unavailable. */
function hlb_client_ip_binary(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    $packed = $ip !== '' ? @inet_pton($ip) : false;
    return $packed === false ? "\0" : $packed;
}

/** Record one login attempt, successful or not. */
function hlb_record_attempt(PDO $pdo, string $email, bool $successful): void
{
    try {
        // attempted_at is written from PHP rather than left to the column
        // default: MySQL's CURRENT_TIMESTAMP follows the database session's
        // time zone, which need not match PHP's, and a few hours of drift
        // would silently widen or disable the lockout window below.
        $stmt = $pdo->prepare(
            "INSERT INTO login_attempts (email, ip_address, successful, attempted_at)
             VALUES (:email, :ip, :successful, :now)"
        );
        $stmt->bindValue(':email', mb_substr($email, 0, HLB_EMAIL_MAX));
        $stmt->bindValue(':ip', hlb_client_ip_binary(), PDO::PARAM_LOB);
        $stmt->bindValue(':successful', $successful ? 1 : 0, PDO::PARAM_INT);
        $stmt->bindValue(':now', date('Y-m-d H:i:s'));
        $stmt->execute();
    } catch (\PDOException $e) {
        // Throttling is a safety net, not the feature. Never block a login
        // because the audit insert failed.
        error_log("login_attempts insert failed: " . $e->getMessage());
    }
}

/**
 * Seconds the caller must wait, or 0 if they may try now.
 *
 * Counts failures since the last success, matching on identifier OR IP, so
 * neither spraying one account from many IPs nor many accounts from one IP
 * slips through. The window is computed in PHP so the SQL stays portable.
 */
function hlb_lockout_seconds(PDO $pdo, string $email): int
{
    try {
        $cutoff = (new DateTimeImmutable('-' . HLB_LOCKOUT_WINDOW . ' seconds'))
            ->format('Y-m-d H:i:s');

        $stmt = $pdo->prepare(
            "SELECT successful, attempted_at
             FROM login_attempts
             WHERE attempted_at > :cutoff
               AND (email = :email OR ip_address = :ip)
             ORDER BY attempted_at DESC, id DESC
             LIMIT 50"
        );
        $stmt->bindValue(':cutoff', $cutoff);
        $stmt->bindValue(':email', $email);
        $stmt->bindValue(':ip', hlb_client_ip_binary(), PDO::PARAM_LOB);
        $stmt->execute();
        $rows = $stmt->fetchAll();
    } catch (\PDOException $e) {
        error_log("login_attempts read failed: " . $e->getMessage());
        return 0;
    }

    $failures   = 0;
    $newest_bad = null;
    foreach ($rows as $row) {
        if ((int)$row['successful'] === 1) {
            break; // a success clears the slate
        }
        $failures++;
        $newest_bad ??= $row['attempted_at'];
    }

    if ($failures < HLB_MAX_ATTEMPTS || $newest_bad === null) {
        return 0;
    }

    $unlocks_at = strtotime($newest_bad) + HLB_LOCKOUT_WINDOW;
    return max(0, $unlocks_at - time());
}

/** "15 minutes" / "40 seconds", for the lockout message. */
function hlb_humanize_seconds(int $seconds): string
{
    if ($seconds >= 60) {
        $minutes = (int)ceil($seconds / 60);
        return $minutes . ' minute' . ($minutes === 1 ? '' : 's');
    }
    return max(1, $seconds) . ' second' . ($seconds === 1 ? '' : 's');
}

// ---------------------------------------------------------------------------
// Output
// ---------------------------------------------------------------------------

/** Escape for HTML. Short name because it's used constantly in templates. */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
