<?php
// Gate for serving JS only to script tag loads; block navigation clicks and direct access with 404
// Usage: /js/<file>.js -> internally routed here via .htaccess

// Block View Source clicks from Login/Register pages
$ref = isset($_SERVER['HTTP_REFERER']) ? strtolower($_SERVER['HTTP_REFERER']) : '';
$isViewSource = str_starts_with($ref, 'view-source:');
$isLoginOrRegister = (
    strpos($ref, '/php/login.php') !== false ||
    strpos($ref, '/php/register.php') !== false ||
    strpos($ref, 'app.php?page=login') !== false ||
    strpos($ref, 'app.php?page=register') !== false
);
if ($isViewSource && $isLoginOrRegister) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// Only allow script tag loads (Sec-Fetch-Dest must be 'script')
// Block direct URL access, XHR, fetch, etc.
$secFetchDest = isset($_SERVER['HTTP_SEC_FETCH_DEST']) ? strtolower($_SERVER['HTTP_SEC_FETCH_DEST']) : '';
if ($secFetchDest !== 'script') {
    // Not a script tag load - block with 404
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// Sanitize and serve the JS file for legitimate script tag requests only
$file = isset($_GET['file']) ? $_GET['file'] : '';
if (!preg_match('/^[a-zA-Z0-9_\-\/]+\.js$/', $file)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$path = realpath(__DIR__ . '/../js/' . $file);
$base = realpath(__DIR__ . '/../js');
if ($path === false || strpos($path, $base) !== 0 || !is_file($path)) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

// Serve JS only for script tag loads
header('Content-Type: application/javascript; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
?>


