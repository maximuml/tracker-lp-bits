<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());

loggedinorreturn();

$nexusId = (int) ($_GET['id'] ?? 0);
unset($_GET['id']);
$nexusRoute = '/details/' . $nexusId;

require __DIR__ . '/nexus.php';
