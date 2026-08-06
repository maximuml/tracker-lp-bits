<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());

loggedinorreturn();
parked();

if ($enableoffer == 'no')
    permissiondenied();

require __DIR__ . '/nexus.php';
