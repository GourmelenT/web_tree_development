<?php

declare(strict_types=1);

$route = $_GET['route'] ?? '';

if (is_string($route) && $route !== '') {
	switch ($route) {
		case 'get_arbres':
			require __DIR__ . '/backend/get_arbres.php';
			exit;

		case 'add_arbre':
			require __DIR__ . '/backend/add_arbre.php';
			exit;

		case 'options':
			require __DIR__ . '/backend/api/options.php';
			exit;
	}
}

$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
$basePath = rtrim($scriptDir, '/');
$target = ($basePath === '' || $basePath === '.')
	? '/frontend/index.html'
	: $basePath . '/frontend/index.html';

header('Location: ' . $target, true, 302);
exit;
