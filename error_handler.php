<?php
/* =====================================================================
 * OneLara - Central error handling
 * Save as:  C:\xampp\htdocs\onelara\error_handler.php
 *
 * Add ONE line at the very bottom of config.php:
 *     require_once __DIR__ . '/error_handler.php';
 *
 * Then nothing else in the system can show a raw PHP fatal error to a
 * resident again. What you get:
 *
 *   - A friendly error page instead of a white screen or a stack trace
 *   - Every error written to logs/error.log with a reference code you
 *     can quote in your defense ("error OL-ERR-7F3A2B, here it is in
 *     the log")
 *   - db() to wrap any database work so constraint violations turn into
 *     readable messages instead of SQLSTATE codes
 *   - CSRF tokens for your POST forms (ISO/IEC 25010 - Security)
 *   - flash() / take_flash() so redirects can carry a message
 * ===================================================================== */

if (!defined('DB_HOST')) {
    die('error_handler.php must be required from config.php, not directly.');
}

define('OL_LOG_DIR', __DIR__ . '/logs');

if (!is_dir(OL_LOG_DIR)) {
    @mkdir(OL_LOG_DIR, 0775, true);
    @file_put_contents(OL_LOG_DIR . '/.htaccess', "Require all denied\n");
    @file_put_contents(OL_LOG_DIR . '/index.html', '');
}

ini_set('log_errors', '1');
ini_set('error_log', OL_LOG_DIR . '/php_error.log');

/** Short code shown to the user and written to the log, so a screenshot
 *  of the error page is enough to find the exact log line. */
function ol_error_ref(): string {
    return 'OL-ERR-' . strtoupper(bin2hex(random_bytes(3)));
}

function ol_log(string $ref, string $level, string $message, array $extra = []): void {
    $line = sprintf(
        "[%s] %s %s | user=%s | %s %s | %s%s\n",
        date('Y-m-d H:i:s'),
        $level,
        $ref,
        $_SESSION['user_id'] ?? 'guest',
        $_SERVER['REQUEST_METHOD'] ?? 'CLI',
        $_SERVER['REQUEST_URI']    ?? '-',
        $message,
        $extra ? ' | ' . json_encode($extra, JSON_UNESCAPED_SLASHES) : ''
    );
    @file_put_contents(OL_LOG_DIR . '/error.log', $line, FILE_APPEND | LOCK_EX);
}

/** Render a styled error page and stop. Never leaks internals unless
 *  DEBUG_MODE is on. */
function ol_fail(string $ref, string $userMessage, ?string $devDetail = null, int $httpCode = 500): void {
    if (!headers_sent()) {
        http_response_code($httpCode);
        header('Content-Type: text/html; charset=utf-8');
    }
    while (ob_get_level() > 0) { ob_end_clean(); }

    $back = ($_SERVER['HTTP_REFERER'] ?? '') ?: '/home.php';
    $safe = htmlspecialchars($userMessage, ENT_QUOTES, 'UTF-8');
    $dev  = (DEBUG_MODE && $devDetail)
          ? '<pre class="dev">' . htmlspecialchars($devDetail, ENT_QUOTES, 'UTF-8') . '</pre>'
          : '';

    echo <<<HTML
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Something went wrong - OneLara</title>
<style>
  body{margin:0;min-height:100vh;display:flex;align-items:center;justify-content:center;
       font-family:system-ui,Segoe UI,sans-serif;background:#FEF2F2;padding:1.5rem;}
  .box{background:#fff;max-width:560px;width:100%;border-radius:16px;padding:2.2rem;
       box-shadow:0 10px 30px rgba(127,29,29,.12);border-top:6px solid #B91C1C;}
  h1{margin:0 0 .6rem;font-size:1.3rem;color:#991B1B;}
  p{color:#444;line-height:1.6;margin:.5rem 0;}
  code{background:#FEF2F2;padding:.2rem .5rem;border-radius:6px;color:#991B1B;font-weight:600;}
  a{display:inline-block;margin-top:1.2rem;background:#B91C1C;color:#fff;text-decoration:none;
    padding:.65rem 1.3rem;border-radius:10px;font-weight:600;}
  .dev{margin-top:1.2rem;background:#1F2937;color:#F9FAFB;padding:1rem;border-radius:10px;
       font-size:.78rem;overflow-x:auto;white-space:pre-wrap;}
</style><link rel="stylesheet" href="assets/css/mobile.css">
</head><body>
<div class="box">
  <h1>Something went wrong</h1>
  <p>{$safe}</p>
  <p>If you need to report this to the barangay office, please quote
     reference <code>{$ref}</code>.</p>
  <a href="{$back}">Go back</a>
  {$dev}
</div></body></html>
HTML;
    exit();
}

set_exception_handler(function (Throwable $e): void {
    $ref = ol_error_ref();
    ol_log($ref, 'EXCEPTION', get_class($e) . ': ' . $e->getMessage(), [
        'file' => $e->getFile(), 'line' => $e->getLine(),
    ]);
    ol_fail(
        $ref,
        'The system hit an unexpected problem while handling your request. Nothing was saved. Please try again.',
        $e->getMessage() . "\n\n" . $e->getFile() . ':' . $e->getLine() . "\n\n" . $e->getTraceAsString()
    );
});
set_error_handler(function (int $no, string $str, string $file, int $line) {
    if (!(error_reporting() & $no)) { return false; }
    throw new ErrorException($str, 0, $no, $file, $line);
});
register_shutdown_function(function (): void {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        $ref = ol_error_ref();
        ol_log($ref, 'FATAL', $err['message'], ['file' => $err['file'], 'line' => $err['line']]);
        ol_fail($ref, 'The system stopped unexpectedly. Please try again.',
                $err['message'] . "\n" . $err['file'] . ':' . $err['line']);
    }
});


function ol_db_message(PDOException $e): string {
    $sqlState = $e->errorInfo[0] ?? $e->getCode();
    $driver   = (int)($e->errorInfo[1] ?? 0);

    return match (true) {
        $driver === 1062 => 'That record already exists. Please check for a duplicate entry.',
        $driver === 1452 => 'This record is linked to something that no longer exists. Please refresh the page and try again.',
        $driver === 1451 => 'This record cannot be deleted because other records still depend on it.',
        $driver === 1406 => 'One of the values you entered is too long for its field.',
        in_array($driver, [1265, 1366], true)
                         => 'One of the values you selected is not a valid option for that field.',
        $driver === 1048 => 'A required field was left blank.',
        $sqlState === '42S02' => 'A required database table is missing. Please run the latest database patch.',
        $sqlState === '42S22' => 'A required database column is missing. Please run the latest database patch.',
        $driver === 2006 || $driver === 2013
                         => 'Lost connection to the database. Please try again.',
        default          => 'The database could not complete this request.',
    };
}

/**
 * Wrap any block of database work.
 *
 *   $result = db(function () use ($pdo, $id) {
 *       $stmt = $pdo->prepare('UPDATE ...');
 *       $stmt->execute([$id]);
 *       return true;
 *   }, $errors);
 *
 * On success you get the callback's return value. On failure you get
 * null, $errors gets a readable message appended, and the real error
 * lands in logs/error.log with a reference code.
 *
 * Pass $pdo as the third argument and an open transaction is rolled
 * back for you.
 */
function db(callable $work, array &$errors = [], ?PDO $pdo = null) {
    try {
        return $work();
    } catch (PDOException $e) {
        if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
        $ref = ol_error_ref();
        ol_log($ref, 'DB', $e->getMessage(), ['sqlstate' => $e->errorInfo[0] ?? null]);
        $errors[] = ol_db_message($e) . ' (ref ' . $ref . ')'
                  . (DEBUG_MODE ? ' — ' . $e->getMessage() : '');
        return null;
    } catch (Throwable $e) {
        if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
        $ref = ol_error_ref();
        ol_log($ref, 'ERROR', $e->getMessage(), ['file' => $e->getFile(), 'line' => $e->getLine()]);
        $errors[] = 'Something went wrong. Please try again. (ref ' . $ref . ')'
                  . (DEBUG_MODE ? ' — ' . $e->getMessage() : '');
        return null;
    }
}


function post_enum(string $key, array $allowed, ?string $default = null): ?string {
    $v = $_POST[$key] ?? null;
    return (is_string($v) && in_array($v, $allowed, true)) ? $v : $default;
}


function get_enum(string $key, array $allowed, string $default): string {
    $v = $_GET[$key] ?? null;
    return (is_string($v) && in_array($v, $allowed, true)) ? $v : $default;
}

function post_int(string $key, int $default = 0): int {
    return (int)($_POST[$key] ?? $default);
}


function post_date(string $key, ?string $min = null, ?string $max = null): ?string {
    $v = trim((string)($_POST[$key] ?? ''));
    if ($v === '') { return null; }
    $d = DateTime::createFromFormat('Y-m-d', $v);
    if (!$d || $d->format('Y-m-d') !== $v) { return null; }
    if ($min && $v < $min) { return null; }
    if ($max && $v > $max) { return null; }
    return $v;
}


function normalize_ph_mobile(?string $raw): ?string {
    $d = preg_replace('/\D/', '', (string)$raw);
    if (str_starts_with($d, '09') && strlen($d) === 11) { $d = '63' . substr($d, 1); }
    return (strlen($d) === 12 && str_starts_with($d, '63')) ? $d : null;
}


function post_money(string $key, float $max = 99999999.99): ?float {
    $v = $_POST[$key] ?? '';
    if (!is_numeric($v)) { return null; }
    $f = (float)$v;
    return ($f < 0 || $f > $max) ? null : round($f, 2);
}

function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function csrf_check(): void {
    $sent = $_POST['csrf_token'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
        $ref = ol_error_ref();
        ol_log($ref, 'CSRF', 'Rejected POST with bad or missing token');
        ol_fail($ref, 'Your session expired or the form was submitted from another page. '
                    . 'Please go back, reload, and try again.', null, 403);
    }
}

function flash(string $type, string $text): void {
    $_SESSION['flash'][] = [$type, $text];
}

function take_flash(): array {
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}


function redirect(string $to): never {
    if (!headers_sent()) { header('Location: ' . $to); }
    exit();
}
if (isset($_SESSION['user_id'])) {
    $idleLimit = 30 * 60;
    if (isset($_SESSION['last_seen']) && (time() - $_SESSION['last_seen']) > $idleLimit) {
        $_SESSION = [];
        session_destroy();
        session_start();
        flash('error', 'You were signed out after 30 minutes of inactivity.');
        redirect('/login.php');
    }
    $_SESSION['last_seen'] = time();
}
