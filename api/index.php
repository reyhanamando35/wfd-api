<?php

// Entry point untuk runtime vercel-php: semua request diteruskan ke front controller Laravel.
// Matikan tampilan error PHP sejak awal: Laravel baru mematikannya setelah config dimuat, dan output
// yang keburu tercetak membuat header (status 500) gagal terkirim.
ini_set('display_errors', '0');

require __DIR__ . '/../public/index.php';
