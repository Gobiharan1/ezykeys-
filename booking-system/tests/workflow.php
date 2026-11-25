<?php
// Isolated service tests. Uses a disposable SQLite database, never the preview or hosting database.
if(PHP_SAPI!=='cli')exit;
$dir=sys_get_temp_dir().'/ezy-booking-tests-'.bin2hex(random_bytes(4));mkdir($dir,0700);
$cfg=$dir.'/config.php';file_put_contents($cfg,"<?php return ['dsn'=>'sqlite:".$dir."/test.sqlite','timezone'=>'Australia/Melbourne'];");putenv('BOOKING_CONFIG_FILE='.$cfg);
require __DIR__.'/../bin/setup.php';require __DIR__.'/../includes/service.php';
$checks=0;
function check(bool $ok,string $message):void{global $checks;if(!$ok)throw new RuntimeException($message);$checks++;echo "PASS: $message\n";}
function blocked(callable $fn,string $message):void{try{$fn();}catch(DomainException $e){check(true,$message);return;}check(false,$message);}
function requestFor(int $pid,string $suburb='Lynbrook'):array{global $db;$ref=createBooking(['package_id'=>$pid,'name'=>'Demo Learner','phone'=>'0400000000','email'=>'learner@example.test','suburb'=>$suburb,'date'=>date('Y-m-d',strtotime('+10 days')),'time'=>'Flexible','enquiry'=>'Automated isolated test']);return q('SELECT * FROM booking_requests WHERE reference=?',[$ref])->fetch();}
try{
 $b=requestFor(2);check($b['status']==='pending'&&(int)$b['total_cents']===7500,'New request is pending and saves its agreed price');
 q('UPDATE booking_packages SET total_cents=8000 WHERE id=2');check((int)bookingById((int)$b['id'])['total_cents']===7500,'Package price edits do not change existing bookings');
 blocked(fn()=>requestFor(1),'Hampton Park offer rejects another suburb');
 q('UPDATE booking_packages SET active=0 WHERE id=2');blocked(fn()=>requestFor(2),'Archived packages cannot receive new requests');q('UPDATE booking_packages SET active=1 WHERE id=2');
 $t=(new DateTimeImmutable('next monday'))->modify('+14 days')->setTime(9,0);scheduleLesson((int)$b['id'],$t->format('Y-m-d\TH:i'),'Test pickup');check(bookingById((int)$b['id'])['status']==='confirmed','Scheduling a lesson confirms its booking');
 $other=requestFor(2);blocked(fn()=>scheduleLesson((int)$other['id'],$t->modify('+30 minutes')->format('Y-m-d\TH:i'),''),'Overlapping lessons are rejected');
 scheduleLesson((int)$other['id'],$t->modify('+60 minutes')->format('Y-m-d\TH:i'),'');check(true,'Back-to-back lessons do not overlap');
 blocked(fn()=>scheduleLesson((int)$b['id'],$t->modify('+1 day')->format('Y-m-d\TH:i'),''),'Cannot schedule more lessons than purchased');
 $session=q('SELECT * FROM booking_sessions WHERE booking_id=?',[$b['id']])->fetch();blocked(fn()=>updateSession((int)$session['id'],(int)$b['id'],'completed'),'Future lessons cannot be completed');
 saveBooking((int)$b['id'],'confirmed',2500,'Test deposit');check(bookingById((int)$b['id'])['payment_status']==='deposit','Partial payments are recorded as a deposit');
 blocked(fn()=>saveBooking((int)$b['id'],'confirmed',99999,''),'Overpayments are rejected');
 blocked(fn()=>saveBooking((int)$b['id'],'completed',2500,''),'Booking cannot complete with unfinished lessons');
 saveBooking((int)$b['id'],'cancelled',2500,'Customer cancelled');check(q('SELECT status FROM booking_sessions WHERE id=?',[$session['id']])->fetchColumn()==='cancelled','Cancelling a booking releases its scheduled slots');
 $weekly=requestFor(4);scheduleLesson((int)$weekly['id'],$t->modify('+2 hours')->format('Y-m-d\TH:i'),'');blocked(fn()=>scheduleLesson((int)$weekly['id'],$t->modify('+8 days')->format('Y-m-d\TH:i'),''),'Weekly package rejects lessons in another week');
 scheduleLesson((int)$weekly['id'],$t->modify('+1 day')->format('Y-m-d\TH:i'),'');check((int)q("SELECT COUNT(*) FROM booking_sessions WHERE booking_id=? AND status='scheduled'",[$weekly['id']])->fetchColumn()===2,'Weekly package accepts two lessons in the same week');
 $historic=requestFor(2);q("INSERT INTO booking_sessions (booking_id,starts_at,ends_at,status,notes) VALUES (?,?,?,'scheduled','')",[$historic['id'],gmdate('Y-m-d H:i:s',time()-7200),gmdate('Y-m-d H:i:s',time()-3600)]);$sid=(int)$db->lastInsertId();updateSession($sid,(int)$historic['id'],'completed');check(bookingById((int)$historic['id'])['status']==='completed','Completing all included lessons completes the package');
 check(str_contains(localDate('2026-01-01 00:00:00'),'AEDT')&&str_contains(localDate('2026-07-01 00:00:00'),'AEST'),'Melbourne dates handle daylight saving correctly');
 check((int)q('SELECT COUNT(*) FROM booking_activity')->fetchColumn()>0,'Booking and admin changes are recorded in the activity log');
 echo "$checks workflow checks passed.\n";
}finally{$db=null;foreach(glob($dir.'/*') as $f)unlink($f);rmdir($dir);}
