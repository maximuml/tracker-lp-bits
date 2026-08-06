<?php
require "../include/bittorrent.php";
dbconn();
$langFile = get_langfile_path();
if (!is_file(__DIR__ . '/../' . $langFile)) {
    $langFile = __DIR__ . '/../lang/en/lang_takeflush.php';
}
require_once $langFile;
loggedinorreturn();

require __DIR__ . '/nexus.php';
