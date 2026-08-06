<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());

loggedinorreturn();
parked();

$action = $_GET['action'] ?? '';
$commentId = (int) ($_GET['cid'] ?? 0);

unset($_GET['action']);
if (in_array($action, ['edit', 'delete', 'vieworiginal'], true)) {
    unset($_GET['cid']);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$nexusRoute = match ($action) {
    'add' => $method === 'POST' ? '/comment' : '/comment/add',
    'edit' => '/comment/' . $commentId . '/edit',
    'delete' => '/comment/' . $commentId . '/delete',
    'vieworiginal' => '/comment/' . $commentId . '/original',
    default => '/comment',
};

require __DIR__ . '/nexus.php';
