<?php
// Read-only production-schema verification; no authentication/presence helpers loaded.
require_once __DIR__.'/../php/db.php';
require_once __DIR__.'/../php/deletion_history.php';
$pdo->exec('SET TRANSACTION READ ONLY');$pdo->beginTransaction();
try {
 $fk=$pdo->query("SELECT DELETE_RULE FROM information_schema.REFERENTIAL_CONSTRAINTS WHERE CONSTRAINT_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND REFERENCED_TABLE_NAME='users'")->fetchAll(PDO::FETCH_COLUMN);
 if(!$fk || array_filter($fk,static fn($rule)=>$rule!=='SET NULL'))throw new RuntimeException('Unsafe audit FK');
 $origins=$pdo->query('SELECT dr.id, '.deletionOriginSql('dr').' AS origin FROM deletion_requests dr')->fetchAll(PDO::FETCH_ASSOC);
 foreach(['','PENDING','APPROVED','REJECTED'] as $status){
  $sql='SELECT COUNT(*) FROM deletion_requests dr WHERE (dr.employee_id LIKE ? OR dr.full_name LIKE ? OR dr.username LIKE ? OR dr.account_role LIKE ?)';$params=['%','%','%','%'];
  if($status!==''){$sql.=' AND dr.status = ?';$params[]=$status;}
  $stmt=$pdo->prepare($sql);$stmt->execute($params);$stmt->fetchColumn();
 }
 echo 'PASS: SET NULL audit FK, explicit-origin SQL for '.count($origins).' historical requests, and search/all status queries. No writes.' . PHP_EOL;
} finally {$pdo->rollBack();}
