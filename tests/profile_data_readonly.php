<?php
// Explicit read-only verification; never loads authentication/presence code.
require_once __DIR__.'/../php/db.php';
require_once __DIR__.'/../php/profile_data.php';
$pdo->exec('SET TRANSACTION READ ONLY');
$pdo->beginTransaction();
try {
 $rows=$pdo->query('SELECT id, role, birthdate, age, TIMESTAMPDIFF(YEAR,birthdate,CURDATE()) AS current_age FROM users')->fetchAll(PDO::FETCH_ASSOC);
 $counts=[];
 foreach ($rows as $row) {
  $calculated=profileAge($row['birthdate']);
  if ($calculated !== (int)$row['current_age']) throw new RuntimeException('PHP/SQL current age mismatch');
  if (profileBirthdateLabel($row['birthdate'])==='Not provided') throw new RuntimeException('Unexpected invalid live date');
  $counts[$row['role']]=($counts[$row['role']]??0)+1;
 }
 echo 'PASS: validated birthdate labels and PHP/SQL age agreement for '.count($rows).' live accounts: '.json_encode($counts)."\n";
} finally { $pdo->rollBack(); }
