<?php
// Shared by the front controller and standalone endpoints. Included pages reuse
// the already-open session rather than starting it again or reopening stale data.
function ensureCakeDecoSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
}

ensureCakeDecoSession();
