<?php
require "../include/bittorrent.php";
dbconn();
require_once(get_langfile_path('torrents.php'));
loggedinorreturn();

require __DIR__ . '/nexus.php';
