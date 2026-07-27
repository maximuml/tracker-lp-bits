<?php
require_once("../include/bittorrent.php");
dbconn();

$mode = $_GET['mode'] ?? 'toggle';
$current = ($_COOKIE['c_theme'] ?? '') === 'dark';

if ($mode === 'toggle') {
    $enableDark = !$current;
} else {
    $enableDark = ($mode === 'dark');
}

if ($enableDark) {
    setcookie('c_theme', 'dark', time() + 31536000, '/');
} else {
    setcookie('c_theme', '', time() - 3600, '/');
}

$back = $_SERVER['HTTP_REFERER'] ?? 'index.php';
header('Location: ' . $back);
