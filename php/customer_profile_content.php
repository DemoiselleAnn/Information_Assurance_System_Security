<?php ?>
<?php if ($profileMessage): ?><div class="profile-alert success" role="status"><?= profileText($profileMessage) ?></div><?php endif; ?>
<section class="profile-content" aria-labelledby="profile-title">
    <header class="profile-content-header">
        <div>
            <p class="eyebrow">ACCOUNT OVERVIEW</p>
            <h1 class="profile-title" id="profile-title">My Profile</h1>
            <p class="profile-subtitle">View your Frostline account information and security status.</p>
        </div>
    </header>

    <section class="profile-card profile-summary" aria-label="Profile summary">
        <div class="profile-initials" aria-hidden="true"><?php echo profileText($initials); ?></div>
        <div>
            <h2><?php echo profileText($profileName); ?></h2>
            <span class="profile-role">FROSTLINE MEMBER</span>
        </div>
    </section>

    <div class="profile-grid">
        <section class="profile-card" aria-labelledby="personal-info-title">
            <header class="profile-card-header">
                <i class="fas fa-user" aria-hidden="true"></i>
                <div>
                    <h3 id="personal-info-title">Personal Information</h3>
                    <p>Your registered account details.</p>
                </div>
            </header>
            <dl class="profile-details">
                <div class="profile-detail"><dt>Full Name</dt><dd><?php echo profileText($profileName); ?></dd></div>
                <div class="profile-detail"><dt>ID No.</dt><dd><?php echo profileText($profile['id_number'] ?? 'Not provided'); ?></dd></div>
                <div class="profile-detail"><dt>Username</dt><dd><?php echo profileText($profile['username'] ?? ''); ?></dd></div>
                <div class="profile-detail"><dt>Email Address</dt><dd><?php echo profileText($profile['email'] ?? 'Not provided'); ?></dd></div>
                <div class="profile-detail"><dt>Role</dt><dd>Member</dd></div>
                <div class="profile-detail"><dt>Account Status</dt><dd><?php echo profileText(ucfirst(strtolower($profile['status']))); ?></dd></div>
                <div class="profile-detail"><dt>Date of Birth</dt><dd><?php echo profileText($birthdateFormatted); ?></dd></div>
                <div class="profile-detail"><dt>Age</dt><dd><?php echo profileText($ageFormatted); ?></dd></div>
                <div class="profile-detail"><dt>Gender</dt><dd><?php echo profileText($sexFormatted); ?></dd></div>
                <div class="profile-detail"><dt>Address</dt><dd><?php echo profileText($addressFormatted); ?></dd></div>
                <div class="profile-detail"><dt>Account Created</dt><dd><?php echo profileText($createdFormatted); ?></dd></div>
            </dl>
        </section>

        <section class="profile-card profile-security" aria-labelledby="security-title">
            <header class="profile-card-header">
                <i class="fas fa-shield-halved" aria-hidden="true"></i>
                <div>
                    <h3 id="security-title">Account Security</h3>
                    <p>Manage access to your account.</p>
                </div>
            </header>
            <div class="profile-security-body">
                <div class="profile-security-row">
                    <i class="fas fa-lock" aria-hidden="true"></i>
                    <div><strong>Password</strong><span>Protected</span></div>
                </div>
                <a class="profile-action" href="app.php?page=customer_dashboard&view=change_password">Change Password</a>
                <button type="button" class="profile-action" data-mp-toggle="editProfile">Edit Profile</button>
            </div>
        </section>
    </div>
</section>

<dialog id="mp-edit-profile-panel" class="member-edit-dialog" aria-labelledby="member-edit-title" data-member-modal data-open="<?= $editOpen ? 'true' : 'false' ?>">
    <form method="post" action="app.php?page=customer_dashboard&amp;view=profile" id="mpEditForm" novalidate>
        <header class="member-modal-header"><div><p class="eyebrow">FROSTLINE ACCOUNT</p><h2 id="member-edit-title">Edit Profile</h2></div><button type="button" class="member-modal-close" data-member-close aria-label="Close Edit Profile">&times;</button></header>
        <input type="hidden" name="csrf_token" value="<?= profileText($profileCsrf) ?>">
        <input type="hidden" name="action" value="edit_profile">
        <div class="member-modal-body">
            <p class="member-step-status" aria-live="polite">Step 1 of 3 — Personal Information</p>
            <ol class="member-progress" aria-label="Profile editing progress"><li aria-current="step">Personal</li><li>Address</li><li>Review</li></ol>
            <div class="profile-alert error" data-member-error role="alert" <?= $profileError ? '' : 'hidden' ?>><?= profileText($profileError) ?></div>
            <?php
            $personal = ['first_name','middle_name','last_name','extension_name','email','birthdate','sex'];
            foreach ([1 => 'Personal Information', 2 => 'Address Information'] as $step => $heading): ?>
            <section data-member-step="<?= $step ?>" <?= $step === 2 ? 'hidden' : '' ?> aria-labelledby="member-step-<?= $step ?>">
                <h3 id="member-step-<?= $step ?>" tabindex="-1"><?= $heading ?></h3>
                <div class="member-edit-grid">
                <?php foreach (profileEditableFields('USER') as $field => $label):
                    if (in_array($field, $personal, true) !== ($step === 1)) continue;
                    $value = is_string($editValues[$field] ?? null) ? $editValues[$field] : '';
                    $required = in_array($field, ['first_name','last_name','email','birthdate','sex'], true);
                    $max = $field === 'email' ? 100 : (in_array($field, ['extension_name','zip_code'], true) ? 10 : 50);
                ?>
                    <label for="member-<?= $field ?>"><span><?= profileText($label) ?><?= $required ? ' *' : '' ?></span>
                    <?php if ($field === 'sex'): ?>
                        <select id="member-<?= $field ?>" name="sex" data-original="<?= profileText($profile[$field] ?? '') ?>" required aria-invalid="<?= isset($profileFieldErrors[$field]) ? 'true' : 'false' ?>" aria-describedby="member-error-<?= $field ?>">
                            <option value="">Select gender</option>
                            <?php foreach (['private'=>'Private','male'=>'Male','female'=>'Female','other'=>'Prefer not to say'] as $key=>$text): ?>
                            <option value="<?= $key ?>" <?= $value === $key ? 'selected' : '' ?>><?= $text ?></option>
                            <?php endforeach; ?>
                        </select>
                    <?php else: ?>
                        <input id="member-<?= $field ?>" type="<?= $field === 'email' ? 'email' : ($field === 'birthdate' ? 'date' : 'text') ?>" name="<?= $field ?>" data-original="<?= profileText($profile[$field] ?? '') ?>" value="<?= profileText($value) ?>" maxlength="<?= $max ?>" <?= $required ? 'required' : '' ?> <?= $field === 'birthdate' ? 'max="' . date('Y-m-d') . '"' : '' ?> aria-invalid="<?= isset($profileFieldErrors[$field]) ? 'true' : 'false' ?>" aria-describedby="member-error-<?= $field ?>">
                    <?php endif; ?>
                        <span class="member-field-error" id="member-error-<?= $field ?>" aria-live="polite"><?= profileText($profileFieldErrors[$field] ?? '') ?></span>
                    </label>
                <?php endforeach; ?>
                </div>
                <?php if ($step === 1): ?><p class="member-form-note">Age is calculated from your birthdate.</p><?php endif; ?>
            </section>
            <?php endforeach; ?>
            <section data-member-step="3" hidden aria-labelledby="member-step-3"><h3 id="member-step-3" tabindex="-1">Review &amp; Save</h3><p>Check your information before saving.</p><div data-member-review></div></section>
        </div>
        <footer class="member-edit-actions">
            <button type="button" class="profile-action member-secondary" data-member-close>Cancel</button>
            <button type="button" class="profile-action member-secondary" data-member-back hidden>Back</button>
            <button type="button" class="profile-action" data-member-next>Next</button>
            <button type="submit" class="profile-action" data-member-save hidden>Save Changes</button>
        </footer>
    </form>
</dialog>
