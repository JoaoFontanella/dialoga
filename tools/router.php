<?php
$root = dirname(__DIR__);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if ($path === '/' || $path === '/index.php' || strpos($path, '/index.php/') === 0) {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
    $_SERVER['SCRIPT_FILENAME'] = $root . '/index.php';
    chdir($root);
    require $root . '/index.php';
    return true;
}

$file = realpath($root . $path);
$assets = realpath($root . '/assets') . DIRECTORY_SEPARATOR;
$publicAsset = $file !== false && strpos($file, $assets) === 0 && is_file($file)
    && !preg_match('~(?:^|/)\.~', $path)
    && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), array('css', 'js', 'html', 'svg', 'png', 'jpg', 'jpeg', 'webp', 'ico', 'ttf', 'woff', 'woff2', 'txt'), true);
if ($publicAsset) {
    return false;
}
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Não encontrado';
return true;
