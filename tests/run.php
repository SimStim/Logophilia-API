<?php

declare(strict_types=1);

use App\Middleware\AuthMiddleware;
use App\Middleware\RateLimitMiddleware;
use App\Models\APIModels;

require_once __DIR__ . '/../src/Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../src/Middleware/RateLimitMiddleware.php';
require_once __DIR__ . '/../src/Models/APIModels.php';

// Test suite for the Logophilia API. Run with: php tests/run.php
// Covers the input guard and download counter, list filtering and atomic hash
// creation, the rate limiter, the email CRLF guard, and the API key check. All
// state lives in a scratch directory outside the repository. No mail is ever
// sent: the email tests stop at the CRLF guard or at an unresolvable domain,
// before mail().

ini_set('display_errors', 'stderr');
// CLI has no real HTTP headers, so header() calls warn once output exists; silence those.
error_reporting(E_ALL & ~E_WARNING);

$base = sys_get_temp_dir() . '/logophilia-tests';
exec('rm -rf ' . escapeshellarg($base));
mkdir($base . '/downloads', 0777, true);
mkdir($base . '/outside', 0777, true);
define('DOWNLOADS', $base . '/downloads/');
define('UPLOADS', $base . '/uploads/');
define('RATELIMITS', $base . '/ratelimit/');

$pass = 0;
$failures = [];

function check(string $name, bool $condition): void
{
    global $pass, $failures;
    echo $condition ? "  ok  $name\n" : "  FAIL $name\n";
    if ($condition) $pass++; else $failures[] = $name;
}

function captured(callable $fn): array
{
    ob_start();
    $result = $fn();
    return [$result, ob_get_clean()];
}

// Runs $count worker processes in parallel and returns their combined output.
function spawn_workers(int $count, array $args): string
{
    $outFile = tempnam(sys_get_temp_dir(), 'loot-out-');
    $worker = escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/worker.php');
    $jobs = [];
    for ($i = 0; $i < $count; $i++) {
        $jobs[] = '(' . $worker . ' ' . implode(' ', array_map('escapeshellarg', $args))
            . ' >> ' . escapeshellarg($outFile) . ' 2>/dev/null) &';
    }
    shell_exec(implode(' ', $jobs) . ' wait');
    $raw = (string) file_get_contents($outFile);
    unlink($outFile);
    return $raw;
}

// ---------- sendFile: input guard and download counter ----------
echo "sendFile guard + counter\n";
file_put_contents(DOWNLOADS . 'Loot One.epub', 'A');
file_put_contents(DOWNLOADS . 'Loot One.epub.hash', 'stale');
touch(DOWNLOADS . 'Loot One.epub.hash', time() - 86400);
file_put_contents(DOWNLOADS . 'Loot Two.pdf', 'B');
file_put_contents(DOWNLOADS . 'Loot Two.pdf.hash', 'fresh-correct');
touch(DOWNLOADS . 'Loot Two.pdf', time() - 10);
file_put_contents(DOWNLOADS . '.hidden', 'x');
file_put_contents(DOWNLOADS . 'Loot Two.pdf.sent', '7');
file_put_contents($base . '/outside/secret.txt', 'SECRET');
symlink($base . '/outside/secret.txt', DOWNLOADS . 'Evil.epub');

[$result, $out] = captured(fn() => APIModels::sendFile('Loot One.epub'));
check('serves a listed file', $result === true && $out === 'A');
check('download counter created with 1', file_get_contents(DOWNLOADS . 'Loot One.epub.sent') === '1');

[$result, $out] = captured(fn() => APIModels::sendFile('../outside/secret.txt'));
check('blocks ../ traversal', $result === false && str_contains($out, 'FILE NOT FOUND'));
[$result, $out] = captured(fn() => APIModels::sendFile('Loot One.epub.hash'));
check('blocks .hash files', $result === false && str_contains($out, 'FILE NOT FOUND'));
[$result, $out] = captured(fn() => APIModels::sendFile('Loot Two.pdf.sent'));
check('blocks .sent files', $result === false && str_contains($out, 'FILE NOT FOUND'));
[$result, $out] = captured(fn() => APIModels::sendFile('.hidden'));
check('blocks dotfiles', $result === false && str_contains($out, 'FILE NOT FOUND'));
[$result, $out] = captured(fn() => APIModels::sendFile(''));
check('blocks empty name', $result === false && str_contains($out, 'FILE NOT FOUND'));
[$result, $out] = captured(fn() => APIModels::sendFile('Evil.epub'));
check('blocks symlink escaping the download dir', $result === false && str_contains($out, 'FILE NOT FOUND'));

[$result, $out] = captured(fn() => APIModels::sendFile('sub/../Loot One.epub'));
check('canonicalizes sub/../ to the same file', $result === true && $out === 'A');
check('counter only counts successful sends', file_get_contents(DOWNLOADS . 'Loot One.epub.sent') === '2');

// ---------- sendFile counter under concurrency ----------
echo "sendFile counter under concurrency\n";
file_put_contents(DOWNLOADS . 'Loot One.epub.sent', '41');
$raw = spawn_workers(20, ['download', 'Loot One.epub']);
check('20 concurrent downloads add exactly 20 (41 -> 61)',
    substr_count($raw, 'SENT') === 20 && file_get_contents(DOWNLOADS . 'Loot One.epub.sent') === '61');

// ---------- sendFileList: filtering and hashing ----------
echo "sendFileList filtering + hashing\n";
[$result, $out] = captured(fn() => APIModels::sendFileList());
$json = json_decode($out, true);
$sha256ByName = array_column(array: $json['freeloot'] ?? [], column_key: 'sha256', index_key: 'name');
check('lists only the two loot files', $result === true
    && array_keys($sha256ByName) === ['Loot One.epub', 'Loot Two.pdf']);
check('reports correct sha256', $sha256ByName['Loot One.epub'] === hash('sha256', 'A'));
check('stale hash regenerated', file_get_contents(DOWNLOADS . 'Loot One.epub.hash') === hash('sha256', 'A'));
check('fresh hash left untouched', file_get_contents(DOWNLOADS . 'Loot Two.pdf.hash') === 'fresh-correct');

// ---------- sendFileList: atomic hash creation under concurrency ----------
echo "sendFileList hash creation under concurrency\n";
file_put_contents(DOWNLOADS . 'Loot Three.epub', 'C');
$raw = spawn_workers(20, ['list']);
check('20 concurrent listers produce the correct hash',
    file_get_contents(DOWNLOADS . 'Loot Three.epub.hash') === hash('sha256', 'C'));
check('no temp files left behind', glob(DOWNLOADS . '.*.tmp') === []);

// ---------- RateLimitMiddleware ----------
echo "RateLimitMiddleware\n";
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REQUEST_URI'] = '/contact';
$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
$limited = fn(): array => captured(function () {
    try {
        (new RateLimitMiddleware())->handle(testing: true);
        return true;
    } catch (Throwable) {
        return false;
    }
});

$ok = true;
for ($i = 0; $i < 4; $i++) {
    [$r] = $limited();
    $ok = $ok && $r === true;
}
check('allows 4 sequential posts', $ok);
[$r, $body] = $limited();
check('blocks the 5th post with 429 JSON', $r === false && str_contains($body, 'TOO MANY REQUESTS'));
check('counter file holds "start 4"',
    (bool) preg_match('/^\d+ 4$/', (string) file_get_contents(RATELIMITS . md5(string: '203.0.113.7:/contact'))));

$_SERVER['REQUEST_URI'] = '/newsletter';
[$r] = $limited();
check('other route not limited for same ip', $r === true);
$_SERVER['REQUEST_URI'] = '/contact';
$_SERVER['REMOTE_ADDR'] = '198.51.100.9';
[$r] = $limited();
check('other ip not limited for same route', $r === true);

$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
file_put_contents(RATELIMITS . md5(string: '203.0.113.7:/contact'), (time() - 7200) . ' 4');
[$r] = $limited();
check('expired window resets the limit', $r === true);
check('expired counter restarts at 1',
    (bool) preg_match('/^\d+ 1$/', (string) file_get_contents(RATELIMITS . md5(string: '203.0.113.7:/contact'))));

$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/download';
$_SERVER['REMOTE_ADDR'] = '198.51.100.77';
captured(fn() => (new RateLimitMiddleware())->handle(testing: true));
check('GET requests are not counted', !is_file(RATELIMITS . md5(string: '198.51.100.77:/download')));

$raw = spawn_workers(20, ['limit', '/contact', '198.51.100.50']);
check('20 concurrent posts: exactly 4 admitted',
    substr_count($raw, 'PASS') === 4 && substr_count($raw, 'LIMITED') === 16);

// ---------- verifyEmail CRLF guard (via processNewsletter, no mail sent) ----------
echo "verifyEmail CRLF guard\n";
[$result, $out] = captured(fn() => APIModels::processNewsletter("x\r\nBcc: victim@evil.com", 'yes'));
check('rejects CRLF injection before DNS/SMTP', $result === false && str_contains($out, 'INVALID CHARACTERS'));
[$result, $out] = captured(fn() => APIModels::processNewsletter('reader@nonexistent-abc.example', 'yes'));
check('clean email proceeds to domain check', $result === false && str_contains($out, 'DOMAIN COULD NOT BE FOUND'));

// ---------- AuthMiddleware: expected key vs provided header slot ----------
echo "AuthMiddleware\n";
$_ENV['HTTP_X_API_KEY'] = 'valid-test-key';
$auth = fn(): array => captured(function () {
    try {
        (new AuthMiddleware())->handle(testing: true);
        return true;
    } catch (Throwable) {
        return false;
    }
});

$_SERVER['HTTP_X_API_KEY'] = 'valid-test-key';
[$result, $out] = $auth();
check('correct key authenticates', $result === true && $out === '');

$_SERVER['HTTP_X_API_KEY'] = 'wrong-test-key';
[$result, $out] = $auth();
check('wrong key gets 403', $result === false && str_contains($out, 'INVALID API KEY'));

unset($_SERVER['HTTP_X_API_KEY']);
[$result, $out] = $auth();
check('missing key gets 401', $result === false && str_contains($out, 'API KEY IS MISSING'));

$_SERVER['HTTP_X_API_KEY'] = '';
[$result, $out] = $auth();
check('empty key gets 401', $result === false && str_contains($out, 'API KEY IS MISSING'));

// A missing or empty expected key (broken .env) must fail closed, never match.
unset($_ENV['HTTP_X_API_KEY']);
$_SERVER['HTTP_X_API_KEY'] = 'wrong-test-key';
[$result, $out] = $auth();
check('missing expected key gets 403', $result === false && str_contains($out, 'INVALID API KEY'));

$_SERVER['HTTP_X_API_KEY'] = '';
[$result, $out] = $auth();
check('empty key fails closed without expected key', $result === false && str_contains($out, 'API KEY IS MISSING'));

$_ENV['HTTP_X_API_KEY'] = '';
$_SERVER['HTTP_X_API_KEY'] = 'wrong-test-key';
[$result, $out] = $auth();
check('empty expected key never matches', $result === false && str_contains($out, 'INVALID API KEY'));

// ---------- summary ----------
echo "\n$pass checks passed, " . count($failures) . " failed\n";
if ($failures !== []) {
    foreach ($failures as $failure) echo "  failed: $failure\n";
    exit(1);
}
exec('rm -rf ' . escapeshellarg($base));
