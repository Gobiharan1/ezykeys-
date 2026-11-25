<?php
function createBooking(array $data): string {
 return tx(function()use($data){
 $p=q('SELECT * FROM booking_packages WHERE id=? AND active=1',[$data['package_id']])->fetch();
 if(!$p)throw new DomainException('This package is no longer available. Please choose another.');
 if($p['suburb_only'] && $p['suburb_only']!==$data['suburb'])throw new DomainException('The 45-minute offer is available to Hampton Park residents only.');
 $ref='EZY-'.gmdate('ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
 q('INSERT INTO booking_requests (reference,package_id,package_name,total_cents,lesson_count,duration_minutes,weekly_only,name,phone,email,suburb,preferred_date,preferred_time,enquiry,status,payment_status,paid_cents,admin_notes,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',[$ref,$p['id'],$p['name'],$p['total_cents'],$p['lesson_count'],$p['duration_minutes'],$p['weekly_only'],$data['name'],$data['phone'],$data['email'],$data['suburb'],$data['date'],$data['time'],$data['enquiry'],'pending','unpaid',0,'',nowUtc(),nowUtc()]);
 audit('Booking requested',$ref,'Website sample booking page');return $ref;
 });
}
function bookingById(int $id): array { $b=q('SELECT * FROM booking_requests WHERE id=?',[$id])->fetch();if(!$b)throw new DomainException('Booking not found.');return $b; }
function scheduleLesson(int $id,string $when,string $note): void {
 $zone=new DateTimeZone(date_default_timezone_get());
 $dt=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$when,$zone);
 if(!$dt||$dt->format('Y-m-d\TH:i')!==$when)throw new DomainException('Choose a valid lesson date and time.');
 if($dt->getTimestamp()<time())throw new DomainException('Choose a future lesson time.');
 $start=$dt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
 tx(function()use($id,$start,$note){
 $b=bookingById($id);
 if(in_array($b['status'],['cancelled','completed'],true))throw new DomainException('Reopen this booking before scheduling a lesson.');
 if($b['weekly_only']) {
 $others=q("SELECT starts_at FROM booking_sessions WHERE booking_id=? AND status<>'cancelled'",[$id])->fetchAll();
 $tz=new DateTimeZone(date_default_timezone_get());$week=(new DateTimeImmutable($start,new DateTimeZone('UTC')))->setTimezone($tz)->format('o-W');
 foreach($others as $other)if((new DateTimeImmutable($other['starts_at'],new DateTimeZone('UTC')))->setTimezone($tz)->format('o-W')!==$week)throw new DomainException('Weekly package lessons must be scheduled in the same Monday–Sunday calendar week.');
 }
 $count=(int)q("SELECT COUNT(*) FROM booking_sessions WHERE booking_id=? AND status<>'cancelled'",[$id])->fetchColumn();
 if($count>=(int)$b['lesson_count'])throw new DomainException('All included lessons are already scheduled. Cancel a session before replacing it.');
 $end=gmdate('Y-m-d H:i:s',strtotime($start.' UTC')+(int)$b['duration_minutes']*60);
 if(q("SELECT id FROM booking_sessions WHERE status='scheduled' AND starts_at<? AND ends_at>?",[$end,$start])->fetch())throw new DomainException('This time overlaps another lesson. Please choose a different time.');
 q("INSERT INTO booking_sessions (booking_id,starts_at,ends_at,status,notes) VALUES (?,?,?,'scheduled',?)",[$id,$start,$end,$note]);
 q("UPDATE booking_requests SET status='confirmed',updated_at=? WHERE id=?",[nowUtc(),$id]);audit('Lesson scheduled',$b['reference'],localDate($start));
 });
}
function updateSession(int $sid,int $bid,string $status): void {
 if(!in_array($status,['completed','cancelled'],true))throw new DomainException('Invalid lesson status.');
 tx(function()use($sid,$bid,$status){
 $b=bookingById($bid);$s=q('SELECT * FROM booking_sessions WHERE id=? AND booking_id=?',[$sid,$bid])->fetch();
 if(!$s||$s['status']!=='scheduled')throw new DomainException('This lesson has already been updated.');
 if($status==='completed'&&strtotime($s['starts_at'].' UTC')>time())throw new DomainException('A future lesson cannot be marked completed.');
 q('UPDATE booking_sessions SET status=? WHERE id=?',[$status,$sid]);
 $done=(int)q("SELECT COUNT(*) FROM booking_sessions WHERE booking_id=? AND status='completed'",[$bid])->fetchColumn();
 if($done>=(int)$b['lesson_count'])q("UPDATE booking_requests SET status='completed',updated_at=? WHERE id=?",[nowUtc(),$bid]);
 else { $scheduled=(int)q("SELECT COUNT(*) FROM booking_sessions WHERE booking_id=? AND status='scheduled'",[$bid])->fetchColumn();q('UPDATE booking_requests SET status=?,updated_at=? WHERE id=?',[$scheduled?'confirmed':'pending',nowUtc(),$bid]); }
 audit('Lesson '.$status,$b['reference'],localDate($s['starts_at']));
 });
}
function saveBooking(int $id,string $status,int $paid,string $notes): void {
 tx(function()use($id,$status,$paid,$notes){
 $b=bookingById($id);
 if(!in_array($status,['pending','confirmed','completed','cancelled'],true))throw new DomainException('Invalid booking status.');
 if($paid>(int)$b['total_cents'])throw new DomainException('Payment cannot exceed the agreed booking total.');
 $done=(int)q("SELECT COUNT(*) FROM booking_sessions WHERE booking_id=? AND status='completed'",[$id])->fetchColumn();
 if($status==='completed'&&$done<(int)$b['lesson_count'])throw new DomainException('Complete all included lessons before completing the booking.');
 if($status==='confirmed'&&!(int)q("SELECT COUNT(*) FROM booking_sessions WHERE booking_id=? AND status='scheduled'",[$id])->fetchColumn())throw new DomainException('Schedule at least one lesson before confirming this booking.');
 if($status==='pending'&&(int)q("SELECT COUNT(*) FROM booking_sessions WHERE booking_id=? AND status='scheduled'",[$id])->fetchColumn())throw new DomainException('Cancel scheduled lessons before returning this booking to pending.');
 if($status==='cancelled')q("UPDATE booking_sessions SET status='cancelled' WHERE booking_id=? AND status='scheduled'",[$id]);
 $payment=$paid==0?'unpaid':($paid===(int)$b['total_cents']?'paid':'deposit');
 q('UPDATE booking_requests SET status=?,paid_cents=?,payment_status=?,admin_notes=?,updated_at=? WHERE id=?',[$status,$paid,$payment,$notes,nowUtc(),$id]);
 audit('Booking updated',$b['reference'],'Status: '.$status.'; paid: '.money($paid));
 });
}
