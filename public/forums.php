<?php
require_once __DIR__ . '/../include/bittorrent.php';
dbconn();
require_once(get_langfile_path('forums.php'));
loggedinorreturn();
parked();

require __DIR__ . '/nexus.php';
