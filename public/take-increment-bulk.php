<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path('increment-bulk.php'));
loggedinorreturn();

require __DIR__ . '/nexus.php';
