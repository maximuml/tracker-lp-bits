<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
require_once(get_langfile_path());

require __DIR__ . '/nexus.php';
