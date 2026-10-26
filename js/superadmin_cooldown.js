(function () {
    'use strict';
    const label = document.getElementById('takeover-cooldown');
    if (!label) return;
    const end = performance.now() + Number(label.dataset.remaining) * 1000;
    const timer = window.setInterval(function () {
        const seconds = Math.max(0, Math.ceil((end - performance.now()) / 1000));
        label.textContent = seconds ? 'Try again in\n' + String(Math.floor(seconds / 60)).padStart(2, '0') + ':' + String(seconds % 60).padStart(2, '0') : 'You can request access again.';
        if (!seconds) window.clearInterval(timer);
    }, 1000);
})();
