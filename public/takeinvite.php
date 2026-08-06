<?php
require "../include/bittorrent.php";
dbconn();
$langFile = get_langfile_path();
if (!is_file(ROOT_PATH . $langFile)) {
	$langFile = 'lang/en/lang_takeinvite.php';
}
require_once ROOT_PATH . $langFile;
loggedinorreturn();

require __DIR__ . '/nexus.php';
