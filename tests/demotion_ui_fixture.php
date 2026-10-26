<?php
// Local browser regression fixture: no database connection or real role-changing request.
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(403); exit; }
$_SESSION = ['account_management_csrf' => 'ui-fixture-only'];
$demotionAccount = ['id' => 987654, 'username' => 'Disposable UI target'];
?>
<!doctype html><html lang="en"><head><meta charset="utf-8"><title>Demotion UI regression fixture</title>
<link rel="stylesheet" href="../css/admin_management.css">
<script>
let requestCount = 0;
window.fetch = function (url, options) {
    document.getElementById('request-url').textContent = String(url);
    document.getElementById('request-details').textContent = JSON.stringify({
        method:options.method, credentials:options.credentials,
        targetIncluded:!!options.body.get('account_id'),
        passwordIncluded:!!options.body.get('current_password'),
        csrfIncluded:!!options.body.get('csrf_token'),
        action:options.body.get('action')
    });
    requestCount++;
    document.getElementById('requests').textContent = String(requestCount);
    const mode = document.getElementById('response-mode').value;
    if (typeof url !== 'string') return Promise.resolve({ok:false,status:404,headers:new Headers({'content-type':'text/html'}),json:async function () { throw new Error('HTML 404'); }});
    return new Promise(function (resolve, reject) {
        options.signal.addEventListener('abort', function () { reject(new DOMException('Aborted', 'AbortError')); }, {once:true});
        if (mode === 'timeout') return;
        setTimeout(function () {
            if (mode === 'network') { reject(new TypeError('Simulated network failure')); return; }
            resolve({ok: mode === 'success', headers: new Headers({'content-type':'application/json'}),
                json: async function () { return mode === 'success'
                    ? {success:true, redirect:'demotion_ui_fixture.php?success=1'}
                    : {success:false, code:'invalid_password', error:'Incorrect password. Please try again.'}; }
            });
        }, 1000);
    });
};
window.addEventListener('error', function (event) { document.getElementById('errors').textContent = event.message; });
document.addEventListener('DOMContentLoaded', function () {
    const form = document.querySelector('[data-superadmin-demotion-form]');
    form.setAttribute('action', '../php/app.php?page=account_management');
    document.getElementById('form-action-type').textContent = typeof form.action + ': ' + String(form.action);
});
</script>
<script src="../js/action_menu.js" defer></script><script src="../js/superadmin_demotion.js" defer></script>
</head><body><main class="management-page">
<h1>Demotion UI regression fixture</h1><p>Simulated requests only. No account changes.</p>
<?php if (isset($_GET['success'])): ?><p role="status">Success: one refresh completed.</p><?php endif; ?>
<label>Simulated response<select id="response-mode"><option value="password">Incorrect password</option><option value="network">Network failure</option><option value="timeout">Timeout</option><option value="success">Success</option></select></label>
<p>Requests: <span id="requests">0</span></p><p>JavaScript errors: <span id="errors">none</span></p>
<p>Form action property: <span id="form-action-type"></span></p><p>Fetch URL: <span id="request-url">none</span></p>
<p>Request metadata: <span id="request-details">none</span></p>
<table><tbody><tr><td>Disposable UI target</td><td class="action-cell"><a href="#">View</a><span>Restricted</span>
<?php require __DIR__ . '/../php/superadmin_demotion_modal.php'; ?>
</td></tr></tbody></table>
</main></body></html>
