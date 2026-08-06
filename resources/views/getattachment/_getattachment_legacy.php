<?php
extract($GLOBALS, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
$id = (int)$_GET["id"];

if (!$id) {
	echo 'Invalid id.';
	return;
}
$dlkey = $_GET["dlkey"];

if (!$dlkey) {
	echo 'Invalid key';
	return;
}
$row = (array) \Nexus\Database\NexusDB::table('attachments')->where('id', $id)->where('dlkey', $dlkey)->first();
if (!$row) {
	echo 'No attachment found.';
	return;
}
$filelocation = $httpdirectory_attachment."/".$row['location'];
if (!is_file($filelocation) || !is_readable($filelocation)) {
	echo 'File not found or cannot be read.';
	return;
}
$f = fopen($filelocation, "rb");
if (!$f) {
	echo "Cannot open file";
	return;
}
header("Content-Length: " . $row['filesize']);
header("Content-Type: application/octet-stream");

if ( str_replace("Gecko", "", $_SERVER['HTTP_USER_AGENT']) != $_SERVER['HTTP_USER_AGENT'])
{
	header ("Content-Disposition: attachment; filename=\"$row[filename]\" ; charset=utf-8");
}
else if ( str_replace("Firefox", "", $_SERVER['HTTP_USER_AGENT']) != $_SERVER['HTTP_USER_AGENT'] )
{
	header ("Content-Disposition: attachment; filename=\"$row[filename]\" ; charset=utf-8");
}
else if ( str_replace("Opera", "", $_SERVER['HTTP_USER_AGENT']) != $_SERVER['HTTP_USER_AGENT'] )
{
	header ("Content-Disposition: attachment; filename=\"$row[filename]\" ; charset=utf-8");
}
else if ( str_replace("IE", "", $_SERVER['HTTP_USER_AGENT']) != $_SERVER['HTTP_USER_AGENT'] )
{
	header ("Content-Disposition: attachment; filename=".str_replace("+", "%20", rawurlencode($row[filename])));
}
else
{
	header ("Content-Disposition: attachment; filename=".str_replace("+", "%20", rawurlencode($row[filename])));
}

do
{
$s = fread($f, 4096);
print($s);
} while (!feof($f));
\Nexus\Database\NexusDB::table('attachments')->where('id', $id)->increment('downloads');
$Cache->delete_value('attachment_'.$dlkey.'_content');
return;
