<?php
declare(strict_types=1);
$configFile = PHP_SAPI === 'cli' && getenv('BOOKING_CONFIG_FILE') ? getenv('BOOKING_CONFIG_FILE') : __DIR__ . '/../config/local.php';
if (!is_file($configFile)) { http_response_code(503); exit('Booking system setup is required. Please contact Ezy Keys on 0417 545 545.'); }
$config = require $configFile;
date_default_timezone_set($config['timezone'] ?? 'Australia/Melbourne');
if (PHP_SAPI !== 'cli') {
    ini_set('session.use_strict_mode', '1');
    session_name($config['session_name'] ?? 'ezy_booking');
    session_set_cookie_params(['lifetime'=>0, 'path'=>rtrim(str_replace('\\','/',dirname($_SERVER['SCRIPT_NAME'])), '/') . '/', 'secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly'=>true, 'samesite'=>'Lax']);
    session_start();
    header('Cache-Control: no-store');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; frame-ancestors 'none'; base-uri 'self'; form-action 'self'");
}
try {
    $db = new PDO($config['dsn'], $config['username'] ?? null, $config['password'] ?? null, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
    if ($db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') { $db->exec('PRAGMA foreign_keys=ON'); $db->exec('PRAGMA busy_timeout=5000'); }
} catch (PDOException $e) { error_log('Booking DB connection failed: '.$e->getCode()); http_response_code(503); exit('Bookings are temporarily unavailable. Please call 0417 545 545.'); }
function e(mixed $s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function q(string $sql, array $params=[]): PDOStatement { global $db; $st=$db->prepare($sql);$st->execute($params);return $st; }
function nowUtc(): string { return gmdate('Y-m-d H:i:s'); }
function money(int|string $c): string { return '$'.number_format((int)$c / 100, (int)$c % 100 ? 2 : 0); }
function csrf(): string { return $_SESSION['csrf'] ??= bin2hex(random_bytes(32)); }
function csrfField(): string { return '<input type="hidden" name="csrf" value="'.e(csrf()).'">'; }
function post(string $k, int $max=500): string { $v=$_POST[$k]??'';if(!is_string($v)||mb_strlen($v)>$max)throw new DomainException('Please check the length of your entry.');return trim($v); }
function checkCsrf(): void { if (!hash_equals(csrf(), post('csrf',100))) { http_response_code(403);throw new DomainException('This form expired. Refresh the page and try again.'); } }
function redirect(string $url): never { header('Location: '.$url, true, 303);exit; }
function flash(string $s): void { $_SESSION['flash']=$s; }
function getFlash(): string { $s=$_SESSION['flash']??'';unset($_SESSION['flash']);return $s; }
function admin(): ?array {
 if(empty($_SESSION['admin_id']))return null;
 if(time()-($_SESSION['last_seen']??0)>7200 || time()-($_SESSION['auth_at']??0)>43200) { unset($_SESSION['admin_id']);return null; }
 $_SESSION['last_seen']=time();return q('SELECT id,name,email FROM booking_admins WHERE id=? AND active=1',[$_SESSION['admin_id']])->fetch()?:null;
}
function audit(string $action,string $target,string $detail=''): void { q('INSERT INTO booking_activity (admin_id,action,target,detail,created_at) VALUES (?,?,?,?,?)',[$_SESSION['admin_id']??null,$action,$target,$detail,nowUtc()]); }
function localDate(?string $utc): string { if(!$utc)return 'Not scheduled';return (new DateTimeImmutable($utc,new DateTimeZone('UTC')))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('D j M Y, g:i a T'); }
function limitRequest(string $scope,int $limit,int $seconds): void {
 $key=hash('sha256',$scope);$since=gmdate('Y-m-d H:i:s',time()-$seconds);
 if((int)q('SELECT COUNT(*) FROM booking_attempts WHERE scope=? AND created_at>?',[$key,$since])->fetchColumn()>=$limit)throw new DomainException('Too many attempts. Please wait a few minutes and try again.');
 q('INSERT INTO booking_attempts (scope,created_at) VALUES (?,?)',[$key,nowUtc()]);
 q('DELETE FROM booking_attempts WHERE created_at<?',[gmdate('Y-m-d H:i:s',time()-86400)]);
}
function tx(callable $work): mixed {
 global $db;
 $sqlite=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
 if($sqlite)$db->exec('BEGIN IMMEDIATE');else { $db->beginTransaction();q('SELECT id FROM booking_locks WHERE id=1 FOR UPDATE'); }
 try{$r=$work();if($sqlite)$db->exec('COMMIT');else $db->commit();return $r;}catch(Throwable $e){if($sqlite)$db->exec('ROLLBACK');elseif($db->inTransaction())$db->rollBack();throw $e;}
}
function positiveInt(string $s,int $min,int $max): int { if(!ctype_digit($s)||(int)$s<$min||(int)$s>$max)throw new DomainException('Please enter a valid number.');return (int)$s; }
function priceCents(string $s): int { if(!preg_match('/^\d{1,5}(?:\.\d{1,2})?$/D',$s))throw new DomainException('Enter a valid price.');return (int)round((float)$s*100); }
