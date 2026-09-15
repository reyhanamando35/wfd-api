<?php

// Entry point untuk runtime vercel-php: semua request diteruskan ke front controller Laravel.
// Matikan tampilan error PHP sejak awal: Laravel baru mematikannya setelah config dimuat, dan output
// yang keburu tercetak membuat header (status 500) gagal terkirim.
ini_set('display_errors', '0');

// vercel-php mengisi SCRIPT_NAME=/api/index.php, sehingga Symfony menganggap "/api" sebagai base path dan
// membuangnya dari URL: /api/market/illustrations terbaca /market/illustrations (404). Samakan dengan public/index.php.
$_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/index.php';
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/../public/index.php';

require __DIR__ . '/../public/index.php';
