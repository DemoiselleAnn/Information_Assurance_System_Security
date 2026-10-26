<?php
// CLI-only service; never included by an application request.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

const PROVISIONAL_CLEANUP_PREDICATE = "role IN ('ADMIN','SUPER_ADMIN') AND first_login_required = 1 AND temporary_credential_expires_at IS NOT NULL AND temporary_credential_expires_at <= CURRENT_TIMESTAMP";

function verifyProvisionalCleanupSchema(PDO $pdo): void {
    if ((int)$pdo->query('SELECT @@foreign_key_checks')->fetchColumn() !== 1) throw new RuntimeException('Foreign key checks must remain enabled.');
    if ((int)$pdo->query('SELECT COUNT(*) FROM information_schema.TRIGGERS WHERE TRIGGER_SCHEMA=DATABASE()')->fetchColumn()!==0) throw new RuntimeException('Unreviewed database trigger blocks cleanup.');
    $expected = [
        'deletion_requests.requested_by'=>'SET NULL', 'deletion_requests.reviewed_by'=>'SET NULL',
        'login_attempts.user_id'=>'CASCADE', 'password_recovery.user_id'=>'CASCADE',
        'security_answers.user_id'=>'CASCADE', 'security_logs.user_id'=>'SET NULL',
        'superadmin_sessions.user_id'=>'CASCADE', 'superadmin_session_state.user_id'=>'SET NULL',
        'user_permissions.user_id'=>'CASCADE', 'user_sessions.user_id'=>'CASCADE',
    ];
    $rows = $pdo->query("SELECT k.TABLE_NAME,k.COLUMN_NAME,k.REFERENCED_COLUMN_NAME,r.DELETE_RULE,r.UPDATE_RULE FROM information_schema.KEY_COLUMN_USAGE k JOIN information_schema.REFERENTIAL_CONSTRAINTS r USING(CONSTRAINT_SCHEMA,CONSTRAINT_NAME,TABLE_NAME) WHERE k.REFERENCED_TABLE_SCHEMA=DATABASE() AND k.REFERENCED_TABLE_NAME='users'")->fetchAll(PDO::FETCH_ASSOC);
    $actual=[];
    foreach ($rows as $r) {
        $key=$r['TABLE_NAME'].'.'.$r['COLUMN_NAME'];
        if ($r['REFERENCED_COLUMN_NAME']!=='id' || $r['UPDATE_RULE']!=='RESTRICT' || isset($actual[$key])) throw new RuntimeException('Unexpected user relationship.');
        $actual[$key]=$r['DELETE_RULE'];
    }
    ksort($actual); ksort($expected);
    if ($actual!==$expected) throw new RuntimeException('User foreign-key relationships differ from reviewed schema.');
    $audit=$pdo->query("SELECT IS_NULLABLE FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='security_logs' AND COLUMN_NAME='user_id'")->fetchColumn();
    if ($audit!=='YES') throw new RuntimeException('Audit user reference must permit NULL.');
    foreach (array_unique(array_merge(['users'],array_map(fn($k)=>explode('.',$k)[0],array_keys($expected)))) as $table) {
        $s=$pdo->prepare('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?');$s->execute([$table]);
        if ($s->fetchColumn()!=='InnoDB') throw new RuntimeException('Cleanup requires transactional tables.');
    }
}

/** One bounded, atomic batch. All user locks precede dependent CASCADE locks. */
function cleanupExpiredAdministrators(PDO $pdo, bool $dryRun = true, int $limit = 100, ?callable $log = null): array {
    if ($pdo->inTransaction()) throw new LogicException('Cleanup owns its transaction.');
    if ($limit<1 || $limit>1000) throw new InvalidArgumentException('Batch limit must be 1-1000.');
    verifyProvisionalCleanupSchema($pdo);
    $candidates=$pdo->query('SELECT id,role,temporary_credential_expires_at FROM users WHERE '.PROVISIONAL_CLEANUP_PREDICATE.' ORDER BY id LIMIT '.$limit)->fetchAll(PDO::FETCH_ASSOC);
    if ($dryRun) return ['dry_run'=>true,'eligible'=>$candidates,'deleted_ids'=>[]];
    if (!$log) throw new LogicException('Execution requires a private durable result logger.');
    $run=bin2hex(random_bytes(16));$deleted=[];$committed=false;$commitAttempted=false;
    $log(['event'=>'started','run'=>$run,'candidate_ids'=>array_map(fn($r)=>(int)$r['id'],$candidates)]);
    try {
        $pdo->beginTransaction();
        $lock=$pdo->prepare('SELECT id FROM users WHERE id=? FOR UPDATE');
        $eligible=$pdo->prepare('SELECT id FROM users WHERE id=? AND '.PROVISIONAL_CLEANUP_PREDICATE);
        $audit=$pdo->prepare("SELECT COUNT(*) FROM security_logs WHERE user_id=? AND COALESCE(NULLIF(username,''),NULLIF(full_name,''),NULLIF(actor_id_number,'')) IS NULL");
        $delete=$pdo->prepare('DELETE FROM users WHERE id=? AND '.PROVISIONAL_CLEANUP_PREDICATE);
        foreach ($candidates as $row) {
            $id=(int)$row['id'];$lock->execute([$id]);$lock->fetchColumn();$lock->closeCursor();
            // Fresh server timestamp AFTER the lock wait; activation may have won.
            $eligible->execute([$id]);if (!$eligible->fetchColumn()) continue;$eligible->closeCursor();
            $audit->execute([$id]);if ((int)$audit->fetchColumn()!==0) throw new RuntimeException('Audit identity snapshot missing; account retained.');
            $delete->execute([$id]);if ($delete->rowCount()===1) $deleted[]=$id;
        }
        $log(['event'=>'commit_intent','run'=>$run,'account_ids'=>$deleted]);
        $commitAttempted=true;$pdo->commit();$committed=true;
        $log(['event'=>'committed','run'=>$run,'account_ids'=>$deleted]);
        return ['dry_run'=>false,'eligible'=>$candidates,'deleted_ids'=>$deleted];
    } catch (Throwable $e) {
        $rolledBack=false;
        try {if ($pdo->inTransaction()) {$pdo->rollBack();$rolledBack=true;}} catch (Throwable $ignored) {}
        // A lost COMMIT response must never be reported as a confirmed rollback.
        $outcome=$committed?'committed_log_failure':($commitAttempted?'commit_outcome_unknown':($rolledBack?'rolled_back':'transaction_failed'));
        try {$log(['event'=>$outcome,'run'=>$run,'account_ids'=>($committed||$commitAttempted)?$deleted:[],'error_type'=>get_class($e)]);} catch (Throwable $ignored) {}
        throw $e;
    }
}
