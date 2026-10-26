<?php // Shared confirmation for the existing account role-management action.
require_once __DIR__ . '/account_status.php';
$demotionDialogId = 'demote-superadmin-' . (int)$demotionAccount['id'];
?>
<button type="button" class="btn btn-small btn-danger demote-account-trigger" data-demotion-dialog="<?= $demotionDialogId ?>">Demote to Admin</button>
<dialog class="management-dialog superadmin-demotion-dialog" id="<?= $demotionDialogId ?>" aria-labelledby="<?= $demotionDialogId ?>-title">
    <form method="post" action="app.php?page=account_management" class="administrator-form" data-superadmin-demotion-form>
        <span class="demotion-security-mark" aria-hidden="true"><i class="fas fa-shield-halved"></i></span>
        <h3 id="<?= $demotionDialogId ?>-title">Demote Super Admin to Admin?</h3>
        <p class="demotion-warning"><?= htmlspecialchars($demotionAccount['username']) ?> will lose all Super Admin-only privileges and receive the normal Admin permissions. Existing Super Admin sessions for this account will be revoked.</p>
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(accountCsrfToken()) ?>">
        <input type="hidden" name="action" value="demote_super_admin">
        <input type="hidden" name="account_id" value="<?= (int)$demotionAccount['id'] ?>">
        <label for="<?= $demotionDialogId ?>-password">Current Password</label>
        <input id="<?= $demotionDialogId ?>-password" required type="password" name="current_password" autocomplete="current-password" placeholder="Enter your current password" aria-describedby="<?= $demotionDialogId ?>-password-help">
        <small id="<?= $demotionDialogId ?>-password-help" class="demotion-password-help">Enter your password to authorize this role change.</small>
        <p data-demotion-error role="alert" hidden></p>
        <div class="form-actions">
            <button type="button" class="btn btn-quiet" data-demotion-cancel>Cancel</button>
            <button type="submit" class="btn btn-danger">Demote to Admin</button>
        </div>
    </form>
</dialog>
