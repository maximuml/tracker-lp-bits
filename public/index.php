<?php
require "../include/bittorrent.php";
dbconn(true);
require_once(get_langfile_path());
loggedinorreturn(true);

require __DIR__ . '/nexus.php';
