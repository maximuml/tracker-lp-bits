<?php
require_once("../include/bittorrent.php");
dbconn(true);
require_once(get_langfile_path('torrents.php'));
require_once(get_langfile_path('special.php'));
loggedinorreturn();
parked();

require __DIR__ . '/nexus.php';
