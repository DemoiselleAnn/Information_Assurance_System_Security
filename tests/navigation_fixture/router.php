<?php
// Local test server router: expose synthetic fixture and public assets only.
$path=parse_url($_SERVER['REQUEST_URI'],PHP_URL_PATH);
if(in_array($path,['/tests/navigation_fixture/app.php','/nested-install/php/app.php'],true)) { require __DIR__.'/app.php'; return true; }
if(preg_match('~^/(?:css|js)/([a-zA-Z0-9_.-]+\.(css|js))$~',$path,$match)) {
    $file=dirname(__DIR__,2).$path;
    if(is_file($file)){header('Content-Type: '.($match[2]==='css'?'text/css':'application/javascript'));readfile($file);return true;}
}
if(preg_match('~^/GalleryImgs/[a-zA-Z0-9% _.-]+\.(png|jpg|jpeg|svg)$~',$path)) {
    $file=dirname(__DIR__,2).rawurldecode($path);
    if(is_file($file)){header('Content-Type: '.(str_ends_with($path,'.svg')?'image/svg+xml':(str_ends_with($path,'.png')?'image/png':'image/jpeg')));readfile($file);return true;}
}
http_response_code(404);echo 'Fixture route not found';return true;
