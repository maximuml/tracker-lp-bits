<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (!isset($Cache)) $Cache = \App\Support\SupportContext::getCache();
$__server_HTTP_USER_AGENT = \App\Support\SupportContext::getServerValue('HTTP_USER_AGENT');
$id = (int)\App\Support\SupportContext::getQuery("id");

if (!$id) {
	echo 'Invalid id.';
	return;
}
$dlkey = \App\Support\SupportContext::getQuery("dlkey");

if (!$dlkey) {
	echo 'Invalid key';
	return;
}
$row = (array) \Nexus\Database\NexusDB::table('attachments')->where('id', $id)->where('dlkey', $dlkey)->first();
if (!$row) {
	echo 'No attachment found.';
	return;
}
$basePath = realpath($httpdirectory_attachment);
$filelocation = $httpdirectory_attachment."/".$row['location'];
$realFile = realpath($filelocation);
if ($basePath === false || $realFile === false || !str_starts_with($realFile, $basePath) || !is_file($realFile) || !is_readable($realFile)) {
	echo 'File not found or cannot be read.';
	return;
}
$f = fopen($realFile, "rb");
if (!$f) {
	echo "Cannot open file";
	return;
}
header("Content-Type: application/octet-stream");

$filename = basename((string) ($row['filename'] ?? ''));
$filename = str_replace(['"', '\\', "\r", "\n"], '', $filename);
if ($filename === '') {
    $filename = 'attachment';
}
header('Content-Disposition: attachment; filename="' . $filename . '"');

do
{
$s = fread($f, 4096);
print($s);
} while (!feof($f));
\Nexus\Database\NexusDB::table('attachments')->where('id', $id)->increment('downloads');
$Cache->delete_value('attachment_'.$dlkey.'_content');
return;