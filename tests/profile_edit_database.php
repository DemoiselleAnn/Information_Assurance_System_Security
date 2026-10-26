<?php
// Exercise real saves only inside a transaction that is always rolled back.
require_once __DIR__ . '/../php/db.php';
require_once __DIR__ . '/../php/profile_edit.php';
$pdo->beginTransaction();
try {
    foreach (['USER','ADMIN','SUPER_ADMIN'] as $role) {
        $stmt=$pdo->prepare("SELECT * FROM users WHERE role=? AND status='ACTIVE' AND first_login_required=0 LIMIT 1");
        $stmt->execute([$role]); $before=$stmt->fetch(PDO::FETCH_ASSOC);
        if (!$before) { echo 'SKIP: no active, setup-complete account for '.$role."\n"; continue; }
        $_SESSION=['user_id'=>(int)$before['id']];
        $input=array_intersect_key($before,profileEditableFields($role));
        $input=array_map(static fn($value)=>(string)$value,$input);
        $input['purok']='Purok 3';
        $input['id']=0; $input['user_id']=0; $input['role']='BLOCKED'; $input['status']='BLOCKED';
        saveOwnProfile($pdo,$before,$input);
        $read=$pdo->prepare('SELECT * FROM users WHERE id=?'); $read->execute([$before['id']]); $after=$read->fetch(PDO::FETCH_ASSOC);
        if ($after['purok']!==$input['purok'] || $after['role']!==$before['role'] || $after['status']!==$before['status']) throw new RuntimeException('Save/authorization failure');
        foreach ($before as $key=>$value) {
            if (in_array($key,['purok','age','updated_at'],true)) continue;
            if ((string)$after[$key] !== (string)$value) throw new RuntimeException('Unexpected field change: '.$key);
        }
        echo 'PASS: '.$role." real save/readback and protected fields (rolled back).\n";
    }
} finally { $pdo->rollBack(); }
