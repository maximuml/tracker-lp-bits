<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
require_once(get_langfile_path('edit.php'));

loggedinorreturn();
parked();

require __DIR__ . '/nexus.php';
