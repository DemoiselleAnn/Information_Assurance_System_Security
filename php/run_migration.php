<?php
// Retired web migration entry point: no schema changes may be triggered from a URL.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
fwrite(STDERR, "Legacy migration retired. Use php cli/upgrade_database.php --plan --database DATABASE, then verify a backup before explicit --apply.\n");
exit(1);
