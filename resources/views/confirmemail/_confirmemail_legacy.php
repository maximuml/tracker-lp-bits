<?php
extract($GLOBALS, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


if (!preg_match(':^/(\d{1,10})/([\w]{32})/(.+)$:', $_SERVER["PATH_INFO"], $matches))
	httperr();

$id = intval($matches[1] ?? 0);
$md5 = $matches[2];
$email = urldecode($matches[3]);
//print($email);
//die();

if (!$id)
	httperr();

$user = \App\Models\User::query()->where('id', $id)->first(['editsecret']);
if (!$user)
	httperr();
$row = $user->toArray();

$sec = hash_pad($row["editsecret"]);
if (preg_match('/^ *$/s', $sec))
	httperr();
if ($md5 != md5($sec . $email . $sec))
	httperr();

$affected = \App\Models\User::query()->where('id', $id)->where('editsecret', $row['editsecret'])->update(['editsecret' => '', 'email' => $email]);

if (!$affected)
	httperr();

header("Location: " . get_protocol_prefix() . "$BASEURL/usercp.php?action=security&type=saved");
