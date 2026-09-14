<?php
declare(strict_types=1);
require __DIR__ . '/../app/security.php';
$count = 0;
function check(bool $ok, string $message): void { global $count; if (!$ok) throw new RuntimeException($message); $count++; }
$directory = sys_get_temp_dir() . '/pulse-test-' . bin2hex(random_bytes(8));
try {
    check(security_rate_hit($directory, 'a', 2, 60, 100) === 0, 'First request');
    check(security_rate_hit($directory, 'a', 2, 60, 101) === 0, 'Second request');
    check(security_rate_hit($directory, 'a', 2, 60, 102) === 58, 'Blocks excess requests');
    check(security_rate_hit($directory, 'b', 2, 60, 102) === 0, 'Separate account');
    check(security_rate_hit($directory, 'a', 2, 60, 160) === 0, 'Window expires');
    check(security_video_url('https://example.org/demo.mp4') === 'https://example.org/demo.mp4', 'HTTPS video');
    check(security_video_url('') === null, 'Optional video');
    foreach (['javascript:alert(1)', 'data:text/html,test', 'file:///etc/passwd', 'https://user:pass@example.org/video'] as $value) {
        $blocked = false;
        try { security_video_url($value); } catch (RuntimeException $e) { $blocked = true; }
        check($blocked, 'Unsafe video URL rejected');
    }
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE professionals(id INTEGER, tenant_id INTEGER, user_id INTEGER); CREATE TABLE students(id INTEGER, tenant_id INTEGER, user_id INTEGER, professional_id INTEGER); CREATE TABLE progress_photos(id INTEGER, student_id INTEGER, image_path TEXT);');
    $pdo->exec("INSERT INTO professionals VALUES(1,1,10),(2,1,20),(3,2,30); INSERT INTO students VALUES(1,1,11,1),(2,1,21,2),(3,2,31,3); INSERT INTO progress_photos VALUES(1,1,'uploads/progress/test.jpg');");
    $cases = [['student',11,1,true], ['student',21,1,false], ['professional',10,1,true], ['professional',20,1,false], ['student',31,2,false], ['admin',1,1,false], ['student',11,2,false]];
    foreach ($cases as [$role,$id,$tenant,$expected]) {
        check(security_photo_allowed($pdo, ['role'=>$role,'id'=>$id,'tenant_id'=>$tenant], 'uploads/progress/test.jpg') === $expected, 'Photo ownership isolation');
    }
    check(!security_photo_allowed($pdo, ['role'=>'student','id'=>11,'tenant_id'=>1], "' OR 1=1 --"), 'Parameterized lookup');
    echo "$count verificações aprovadas.\n";
} finally {
    foreach (glob($directory . '/*.json') ?: [] as $file) unlink($file);
    if (is_dir($directory)) rmdir($directory);
}
