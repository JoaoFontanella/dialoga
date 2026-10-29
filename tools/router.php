<?php
// Router exclusivo do servidor de desenvolvimento PHP.
$root = dirname(__DIR__);
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
if ($path === '/' || $path === '/index.php' || $path === '/index.php/api/status') {
    require $root . '/index.php';
    return true;
}

// Apenas frontend público. Credenciais, banco, Git e código privado não são servidos.
$file = realpath($root . $path);
$assets = realpath($root . '/assets') . DIRECTORY_SEPARATOR;
$publicAsset = $file !== false && strpos($file, $assets) === 0 && is_file($file)
    && !preg_match('~(?:^|/)\.~', $path)
    && in_array(strtolower(pathinfo($file, PATHINFO_EXTENSION)), array('css', 'js', 'html', 'svg', 'png', 'jpg', 'jpeg', 'webp', 'ico', 'ttf', 'woff', 'woff2', 'txt', 'md'), true);
if ($path === '/index.html' || $publicAsset) {
    return false;
}
http_response_code(404);
header('Content-Type: text/plain; charset=utf-8');
echo 'Não encontrado';
return true;
