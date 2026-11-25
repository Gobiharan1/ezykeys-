<?php
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require __DIR__.'/../includes/bootstrap.php';
$sqlite=$db->getAttribute(PDO::ATTR_DRIVER_NAME)==='sqlite';
$id=$sqlite?'INTEGER PRIMARY KEY AUTOINCREMENT':'INT AUTO_INCREMENT PRIMARY KEY';
$engine=$sqlite?'':' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4';
$tables=[
"booking_admins (id $id,name VARCHAR(100) NOT NULL,email VARCHAR(190) NOT NULL UNIQUE,password_hash VARCHAR(255) NOT NULL,active INTEGER NOT NULL DEFAULT 1)",
"booking_packages (id $id,name VARCHAR(120) NOT NULL,description VARCHAR(400) NOT NULL,total_cents INTEGER NOT NULL,lesson_count INTEGER NOT NULL,duration_minutes INTEGER NOT NULL,suburb_only VARCHAR(80) NOT NULL DEFAULT '',weekly_only INTEGER NOT NULL DEFAULT 0,active INTEGER NOT NULL DEFAULT 1,sort_order INTEGER NOT NULL DEFAULT 0)",
"booking_requests (id $id,reference VARCHAR(32) NOT NULL UNIQUE,package_id INTEGER NOT NULL,package_name VARCHAR(120) NOT NULL,total_cents INTEGER NOT NULL,lesson_count INTEGER NOT NULL,duration_minutes INTEGER NOT NULL,weekly_only INTEGER NOT NULL DEFAULT 0,name VARCHAR(100) NOT NULL,phone VARCHAR(30) NOT NULL,email VARCHAR(190) NOT NULL,suburb VARCHAR(80) NOT NULL,preferred_date VARCHAR(10) NOT NULL,preferred_time VARCHAR(40) NOT NULL,enquiry TEXT NOT NULL,status VARCHAR(20) NOT NULL,payment_status VARCHAR(20) NOT NULL,paid_cents INTEGER NOT NULL,admin_notes TEXT NOT NULL,created_at VARCHAR(19) NOT NULL,updated_at VARCHAR(19) NOT NULL)",
"booking_sessions (id $id,booking_id INTEGER NOT NULL,starts_at VARCHAR(19) NOT NULL,ends_at VARCHAR(19) NOT NULL,status VARCHAR(20) NOT NULL,notes VARCHAR(500) NOT NULL,FOREIGN KEY (booking_id) REFERENCES booking_requests(id))",
"booking_activity (id $id,admin_id INTEGER NULL,action VARCHAR(80) NOT NULL,target VARCHAR(100) NOT NULL,detail VARCHAR(500) NOT NULL,created_at VARCHAR(19) NOT NULL)",
"booking_attempts (id $id,scope VARCHAR(64) NOT NULL,created_at VARCHAR(19) NOT NULL)",
"booking_locks (id INTEGER PRIMARY KEY)"
];
foreach($tables as $t)$db->exec('CREATE TABLE IF NOT EXISTS '.$t.$engine);
foreach([
 ['booking_sessions','booking_sessions_overlap','status,starts_at,ends_at'],
 ['booking_sessions','booking_sessions_parent','booking_id,status'],
 ['booking_requests','booking_requests_status','status,created_at'],
 ['booking_requests','booking_requests_email','email'],
 ['booking_attempts','booking_attempts_scope','scope,created_at']
] as [$table,$index,$columns]) {
 if($sqlite)$db->exec("CREATE INDEX IF NOT EXISTS $index ON $table ($columns)");
 elseif(!q("SHOW INDEX FROM $table WHERE Key_name=?",[$index])->fetch())$db->exec("CREATE INDEX $index ON $table ($columns)");
}
if(!q('SELECT id FROM booking_locks WHERE id=1')->fetch())q('INSERT INTO booking_locks (id) VALUES (1)');
if(!(int)q('SELECT COUNT(*) FROM booking_packages')->fetchColumn()){
$packages=[
 ['45-Minute Lesson','A focused introduction for Hampton Park residents only.',4500,1,45,'Hampton Park',1],
 ['60-Minute Standard','Build safe driving habits with calm, structured coaching.',7500,1,60,'',2],
 ['Single 90-Minute Special','Normally $120. Save $30 with extended active driving time.',9000,1,90,'',3],
 ['Double Up Weekly','Two 60-minute lessons in the same calendar week. $70 per lesson; save $10 total.',14000,2,60,'',4],
 ['Triple Drive Weekly','Three 60-minute lessons in the same calendar week. $65 per lesson; save $30 total.',19500,3,60,'',5],
 ['Complete Test-Ready Package','Two preparation lessons, a test-day warm-up, and dual-control car hire. Test date and car hire arranged with Sanjeev.',35500,3,60,'',6]
];
foreach($packages as $p){$weekly=in_array($p[6],[4,5],true)?1:0;$p[]=$weekly;q('INSERT INTO booking_packages (name,description,total_cents,lesson_count,duration_minutes,suburb_only,sort_order,weekly_only) VALUES (?,?,?,?,?,?,?,?)',$p);}
}
$email=getenv('BOOKING_ADMIN_EMAIL');$password=getenv('BOOKING_ADMIN_PASSWORD');
if($email||$password){
 if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($password?:'')<12)exit("Provide a valid admin email and a password of at least 12 characters.\n");
 if(q('SELECT id FROM booking_admins WHERE email=?',[strtolower($email)])->fetch())exit("Admin exists. No password changed.\n");
 q('INSERT INTO booking_admins (name,email,password_hash) VALUES (?,?,?)',['Sanjeev',strtolower($email),password_hash($password,PASSWORD_DEFAULT)]);
}
echo "Database ready. Packages seeded. Admin created if new credentials were supplied.\n";
