<?php
// Included only after the selected security log has passed the page's access checks.
if (!isset($viewLog) || !is_array($viewLog)) {
    http_response_code(404);
    exit;
}
?>
<h3 class="sl-card-title sl-modal-log-id"><?= htmlspecialchars('LOG-' . str_pad((string)$viewLog['id'], 4, '0', STR_PAD_LEFT)) ?></h3>
<dl class="sl-detail-grid">
                    <div>
                        <dt>ID No.</dt>
                        <dd><?= htmlspecialchars($viewLog['employee_id'] ?? '—') ?></dd>
                    </div>
                    <div>
                        <dt>Full Name</dt>
                        <dd><?= htmlspecialchars(trim((string)($viewLog['snapshot_full_name'] ?? '')) ?: (trim(implode(' ', array_filter([$viewLog['first_name'], $viewLog['middle_name'], $viewLog['last_name']]))) ?: 'Deleted Account')) ?></dd>
                    </div>
                    <div>
                        <dt>Username</dt>
                        <dd><?= htmlspecialchars($viewLog['username'] ?? '—') ?></dd>
                    </div>
                    <div>
                        <dt>Role</dt>
                        <dd>
                            <span class="sl-badge sl-badge-role">
                                <?= htmlspecialchars(getRoleLabel($viewLog['role'] ?? 'USER')) ?>
                            </span>
                        </dd>
                    </div>
                    <div>
                        <dt>Action</dt>
                        <dd><?= htmlspecialchars($viewLog['action'] ?? 'LOGIN') ?></dd>
                    </div>
                    <div class="sl-detail-wide">
                        <dt>Description</dt>
                        <dd><?= htmlspecialchars(getLogDescription($viewLog['action'] ?? 'LOGIN', $viewLog['failure_reason'] ?? null)) ?></dd>
                    </div>
                    <div>
                        <dt>Date</dt>
                        <dd><?= htmlspecialchars(formatLogDateTime($viewLog['created_at'])) ?></dd>
                    </div>
                    <div>
                        <dt>Time</dt>
                        <dd><?= htmlspecialchars(formatLogTime($viewLog['created_at'])) ?></dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd>
                            <span class="sl-badge sl-badge-success">
                                <span class="sl-dot sl-dot-success" aria-hidden="true"></span>
                                <?= htmlspecialchars(getStatusLabel($viewLog['status'] ?? 'SUCCESS')) ?>
                            </span>
                        </dd>
                    </div>
                    <?php if (!empty($viewLog['ip_address'])): ?>
                        <div>
                            <dt>IP Address</dt>
                            <dd class="sl-mono"><?= htmlspecialchars($viewLog['ip_address']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($viewLog['user_agent'])): ?>
                        <div class="sl-detail-wide">
                            <dt>User Agent</dt>
                            <dd class="sl-mono"><?= htmlspecialchars($viewLog['user_agent']) ?></dd>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($viewLog['target_username'])): ?>
                        <div>
                            <dt>Target Username</dt>
                            <dd class="sl-mono"><?= htmlspecialchars($viewLog['target_username']) ?></dd>
                        </div>
                    <?php endif; ?>
                </dl>
