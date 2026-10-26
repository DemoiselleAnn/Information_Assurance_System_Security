<?php
// Compatibility entry point. All migrations now share the guarded consolidated upgrade.
// Requires the same explicit --database, backup and approval arguments as upgrade_database.php.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__.'/upgrade_database.php';
