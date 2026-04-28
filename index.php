<?php

declare(strict_types=1);

$requested = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$localPath = __DIR__ . '/frontend' . $requested;

// Serve static files from frontend
if (preg_match('#\.(html|css|js|png|jpg|gif|svg|woff|woff2)$#', $requested)) {
    if (file_exists($localPath) && is_file($localPath)) {
        $ext = pathinfo($requested, PATHINFO_EXTENSION);
        $mimes = [
            'html' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
        ];
        header('Content-Type: ' . ($mimes[$ext] ?? 'application/octet-stream'));
        readfile($localPath);
        exit;
    }
}

// Route API calls to backend
if (preg_match('#^/api/#', $requested)) {
    $backendFile = __DIR__ . '/backend' . $requested;
    if (file_exists($backendFile) && is_file($backendFile)) {
        include $backendFile;
        exit;
    }
}

// Default redirect to index
if ($requested === '/' || $requested === '') {
    header('Location: /index.html', true, 302);
    exit;
}

// 404
header('HTTP/1.1 404 Not Found');
echo "404 - Not found: $requested";
exit;
