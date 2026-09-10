<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/account-protection.php';

$fixture = sys_get_temp_dir() . '/scenarisation-protection-' . bin2hex(random_bytes(8));
mkdir($fixture, 0700);
ini_set('session.save_path', $fixture);
putenv('APP_TURNSTILE_SITE_KEY=test-site-key');
putenv('APP_TURNSTILE_SECRET_KEY=test-secret-key');
putenv('APP_TURNSTILE_HOSTNAMES=scenarisation.eu,www.scenarisation.eu');
$_SERVER['REMOTE_ADDR'] = '192.0.2.10';
$checks = 0;
function check(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
}
try {
    $csrf = account_csrf_token('signup');
    $_POST = ['csrf_token' => $csrf];
    check(account_csrf_valid('signup'), 'CSRF accepts current session');
    check(!account_csrf_valid('resend_verification'), 'CSRF bound to form purpose');
    $_POST['csrf_token'] = ['invalid'];
    check(!account_csrf_valid('signup'), 'array CSRF rejected');
    $_POST['csrf_token'] = $csrf;
    $_SESSION['account_csrf']['signup']['expires'] = time() - 1;
    check(!account_csrf_valid('signup'), 'expired CSRF rejected');
    check(account_csrf_token('signup') !== $csrf, 'expired form gets fresh token');
    $success = ['success' => true, 'hostname' => 'scenarisation.eu', 'action' => 'signup'];
    check(account_turnstile_valid('token', 'signup', fn($p) => $success), 'valid provider response accepted');
    foreach ([['success'=>false], ['success'=>'true'], ['hostname'=>'attacker.test'], ['action'=>'resend_verification']] as $override) {
        check(!account_turnstile_valid('token', 'signup', fn($p) => array_replace($success, $override)), 'failed, replayed, cross-host or cross-action token rejected');
    }
    check(!account_turnstile_valid('', 'signup', fn() => $success), 'missing token rejected');
    check(!account_turnstile_valid(str_repeat('x', 2049), 'signup', fn() => $success), 'oversized token rejected');
    check(!account_turnstile_valid('token', 'signup', fn() => null), 'provider unavailable fails closed');
    check(!account_turnstile_valid('token', 'signup', function() { throw new RuntimeException(); }), 'network exception fails closed');
    putenv('APP_TURNSTILE_SECRET_KEY=');
    check(!account_turnstile_ready(), 'missing keys fail closed');
    putenv('APP_TURNSTILE_SECRET_KEY=test-secret-key');

    $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $now = 1800000000;
    for ($i=0; $i<5; $i++) check(account_rate_allowed($db, 'one@florimont.ch', $now), 'email quota permits expected requests');
    check(!account_rate_allowed($db, ' ONE@FLORIMONT.CH ', $now), 'normalized email quota enforced');
    $_SERVER['REMOTE_ADDR'] = '192.0.2.11';
    check(!account_rate_allowed($db, 'one@florimont.ch', $now), 'email quota survives IP change');
    check(account_rate_allowed($db, 'two@florimont.ch', $now), 'another school user can register');
    check(account_rate_allowed($db, 'one@florimont.ch', $now+3600), 'expired quota resets');
    $rows=$db->query('SELECT * FROM account_rate_limits')->fetchAll(PDO::FETCH_ASSOC);
    check(!str_contains(json_encode($rows), 'florimont') && !str_contains(json_encode($rows), '192.0.2'), 'no raw email or IP stored');
    $db->exec('DELETE FROM account_rate_limits');
    for ($i=0; $i<60; $i++) check(account_rate_allowed($db, "user$i@florimont.ch", $now), 'shared school IP permits burst');
    check(!account_rate_allowed($db, 'last@florimont.ch', $now), 'IP limit enforced across different emails');
    check(account_rate_allowed($db, 'last@florimont.ch', $now+900), 'IP window resets');

    // Concurrent PHP workers must never each grant their own email quota.
    $shared = $fixture . '/concurrent.sqlite';
    $concurrent = new PDO('sqlite:' . $shared);
    account_rate_allowed($concurrent, 'seed@florimont.ch', $now);
    $concurrent->exec('DELETE FROM account_rate_limits');
    $worker = $fixture . '/worker.php';
    file_put_contents($worker, '<?php require ' . var_export(realpath(__DIR__.'/../lib/account-protection.php'),true) . ';'
        . '$_SERVER["REMOTE_ADDR"]="192.0.2.20"; $db=new PDO("sqlite:".$argv[1]);'
        . 'echo account_rate_allowed($db,"parallel@florimont.ch",1800000000)?"yes":"no";');
    $workers=[];
    for($i=0;$i<8;$i++) {
        $pipes=[];
        $process=proc_open([PHP_BINARY,$worker,$shared],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
        fclose($pipes[0]);$workers[]=[$process,$pipes];
    }
    $granted=0;
    foreach($workers as [$process,$pipes]) {
        $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
        fclose($pipes[1]);fclose($pipes[2]);
        check(proc_close($process)===0, 'concurrent worker succeeds: '.$err);
        if($out==='yes')$granted++;
    }
    check($granted===5,'concurrent email requests grant exactly five attempts');
    session_write_close();
    echo "$checks account protection checks passed\n";
} finally {
    if(session_status()===PHP_SESSION_ACTIVE)session_write_close();
    $db=null;$concurrent=null;
    foreach(glob($fixture.'/*') as $path)unlink($path);
    rmdir($fixture);
}
