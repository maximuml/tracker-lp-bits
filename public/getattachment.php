<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
parked();

require __DIR__ . '/nexus.php';
