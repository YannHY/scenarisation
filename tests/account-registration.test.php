<?php
declare(strict_types=1);
// Exercise real endpoints with disposable data and stubbed HTTPS/mail transports.
$fixture = sys_get_temp_dir().'/scenarisation-registration-'.bin2hex(random_bytes(8));
mkdir($fixture,0700);
putenv('APP_DB_DSN=sqlite:'.$fixture.'/test.sqlite');
putenv('APP_BASE_URL=https://scenarisation.eu');
putenv('APP_MAIL_FROM=test@scenarisation.eu');
putenv('APP_TURNSTILE_SITE_KEY=test-site-key');
putenv('APP_TURNSTILE_SECRET_KEY=test-secret-key');
putenv('APP_TURNSTILE_HOSTNAMES=scenarisation.eu');
$root=dirname(__DIR__);
$runner=$fixture.'/request.php';
file_put_contents($runner, <<<'WORKER'
<?php
ob_start();
ini_set('session.save_path',__DIR__);
$_SERVER['REQUEST_METHOD']='POST';
$_SERVER['HTTPS']='on';
$_SERVER['HTTP_HOST']='scenarisation.eu';
$_SERVER['HTTP_ORIGIN']='https://scenarisation.eu';
$_SERVER['REMOTE_ADDR']='192.0.2.1';
$_SERVER['SCRIPT_NAME']='/'.$argv[2];
require $argv[1].'/lib/account-protection.php';
$_POST=json_decode($argv[3],true);
if (($_POST['csrf_token']??null)==='VALID') $_POST['csrf_token']=account_csrf_token($argv[4]);
// All outgoing HTTPS calls are intercepted, even if the implementation changes URL.
class TestHTTPS {
    public $context;
    private string $data='';
    private int $offset=0;
    public function stream_open($path,$mode,$options,&$opened_path): bool {
        if($path!=='https://challenges.cloudflare.com/turnstile/v0/siteverify') throw new RuntimeException('Unexpected network call');
        file_put_contents(__DIR__.'/calls','called\n',FILE_APPEND);
        $this->data=$GLOBALS['argv'][5];return true;
    }
    public function stream_read($count): string {$s=substr($this->data,$this->offset,$count);$this->offset+=strlen($s);return $s;}
    public function stream_eof(): bool {return $this->offset>=strlen($this->data);}
    public function stream_stat(): array {return [];}
}
stream_wrapper_unregister('https');stream_wrapper_register('https',TestHTTPS::class);
require $argv[1].'/'.$argv[2];
WORKER);
$checks=0;
function check(bool $ok,string $message):void {global $checks;if(!$ok)throw new RuntimeException($message);$checks++;}
function request(string $page,array $post,string $purpose,array $response):string {
    global $runner,$root;
    $pipes=[];
    $process=proc_open([PHP_BINARY,'-d','disable_functions=curl_init','-d','sendmail_path=/usr/bin/true',
        $runner,$root,$page,json_encode($post),$purpose,json_encode($response)],
        [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    fclose($pipes[0]);$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);
    fclose($pipes[1]);fclose($pipes[2]);
    check(proc_close($process)===0 && $err==='', 'Request error: '.$err);
    return $out;
}
try {
    $post=['username'=>'Admin','email'=>'admin@scenarisation.eu','password'=>'temporary-test-password','accept_terms'=>'1','csrf_token'=>'VALID','cf-turnstile-response'=>'test-token'];
    $ok=['success'=>true,'hostname'=>'scenarisation.eu','action'=>'setup_admin'];
    check(request('setup_admin.php',$post,'setup_admin',$ok)==='', 'initial administrator creation redirects');
    $db=new PDO('sqlite:'.$fixture.'/test.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    check((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()===1,'initial administrator recorded');
    $post['username']='Teacher';$post['email']='teacher@florimont.ch';$ok['action']='signup';
    foreach (['missing_csrf','missing_token','failed_token','wrong_host','wrong_action','missing_terms'] as $case) {
        $db->exec('DELETE FROM account_rate_limits');$input=$post;$response=$ok;
        if($case==='missing_csrf')unset($input['csrf_token']);
        if($case==='missing_token')unset($input['cf-turnstile-response']);
        if($case==='failed_token')$response['success']=false;
        if($case==='wrong_host')$response['hostname']='attacker.test';
        if($case==='wrong_action')$response['action']='setup_admin';
        if($case==='missing_terms')unset($input['accept_terms']);
        check(request('signup.php',$input,'signup',$response)!=='','rejected submission shows form: '.$case);
        check((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn()===1,'no account created: '.$case);
    }
    $db->exec('DELETE FROM account_rate_limits');
    check(request('signup.php',$post,'signup',$ok)==='', 'valid registration redirects');
    $user=$db->query("SELECT * FROM users WHERE username='Teacher'")->fetch(PDO::FETCH_ASSOC);
    check($user && $user['terms_accepted_at'] && $user['email_verification_sent_at'],'valid registration records acceptance and sends verification via stub');
    $db->exec('DELETE FROM account_rate_limits');
    $db->exec("UPDATE users SET email_verification_sent_at=0 WHERE username='Teacher'");
    $oldToken=$user['email_verification_token_hash'];
    $resend=['email'=>'teacher@florimont.ch','csrf_token'=>'VALID','cf-turnstile-response'=>'test-token'];
    request('verify-email.php',$resend,'resend_verification',['success'=>false]);
    check($db->query("SELECT email_verification_token_hash FROM users WHERE username='Teacher'")->fetchColumn()===$oldToken,'failed captcha does not issue new email token');
    $ok['action']='resend_verification';
    check(str_contains(request('verify-email.php',$resend,'resend_verification',$ok),'Un nouveau lien'),'verified resend works');
    check($db->query("SELECT email_verification_token_hash FROM users WHERE username='Teacher'")->fetchColumn()!==$oldToken,'verified resend rotates token');
    check(str_contains(request('verify-email.php',$resend,'resend_verification',$ok),'Patientez une minute'),'existing resend cooldown retained');
    $confirm=str_repeat('a',64);
    $db->prepare("UPDATE users SET email_verification_token_hash=?,email_verification_expires_at=? WHERE username='Teacher'")->execute([hash('sha256',$confirm),time()+600]);
    request('verify-email.php',['token'=>$confirm],'verify_email',[]);
    check($db->query("SELECT email_verified_at FROM users WHERE username='Teacher'")->fetchColumn()===null,'confirmation requires CSRF');
    check(str_contains(request('verify-email.php',['token'=>$confirm,'csrf_token'=>'VALID'],'verify_email',[]),'Votre adresse email est vérifiée'),'email confirmation works without captcha');
    echo "$checks registration integration checks passed (HTTPS and email stubbed)\n";
} finally {
    $db=null;
    foreach(glob($fixture.'/*') as $path)unlink($path);
    rmdir($fixture);
}
