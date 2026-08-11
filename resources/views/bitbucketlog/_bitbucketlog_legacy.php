<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


$__server_PHP_SELF = \App\Support\SupportContext::getServerValue('PHP_SELF');
if (\App\Support\UserDisplay::currentClass() < UC_ADMINISTRATOR)
\App\Support\LegacyResponse::abort("Sorry", "Access denied.");
$bucketpath = "$bitbucket";
if (\App\Support\UserDisplay::currentClass() >= UC_MODERATOR)
{
	 $delete = intval(\App\Support\SupportContext::getQuery("delete") ?? 0);
	 if (\App\Support\Validators::isId($delete)) {
		 $bitbucket = \Nexus\Database\NexusDB::table('bitbucket')->where('id', $delete)->first(['name', 'owner']);
		 if ($bitbucket) {
			 $a = (array) $bitbucket;
			 if (\App\Support\UserDisplay::currentClass() >= UC_MODERATOR || $a["owner"] == $CURUSER["id"]) {
				 \Nexus\Database\NexusDB::table('bitbucket')->where('id', $delete)->delete();
				 if (!unlink("$bucketpath/{$a['name']}"))
				 \App\Support\LegacyResponse::abort("Warning", "Unable to unlink file: <b>{$a['name']}</b>. You should contact an administrator about this error.", false);
				 			} } }
}
\App\Support\Html::stdhead("BitBucket Log");
$count = \Nexus\Database\NexusDB::table('bitbucket')->count();
$perpage = 10;
list($pagertop, $pagerbottom, , $offset, $rpp) = \App\Support\Pagination::pager($perpage, $count, $__server_PHP_SELF . "?out=" . (\App\Support\SupportContext::getQuery("out") ?? '') . "&");
print("<h1>BitBucket Log</h1>\n");
print("Total Images Stored: $count");
echo $pagertop;
$bitbucketRows = \Nexus\Database\NexusDB::table('bitbucket')->orderByDesc('added')->offset($offset)->limit($rpp)->get();
if ($bitbucketRows->isEmpty())
print("<b>BitBucket Log is empty</b>\n");
else {
	print("<table align='center' border='0' cellspacing='0' cellpadding='5'>\n");
	foreach ($bitbucketRows as $row) {
	    $arr = (array) $row;
		$date = substr($arr['added'], 0, strpos($arr['added'], " "));
		$time = substr($arr['added'], strpos($arr['added'], " ") + 1);
		$name = $arr["name"];
		list($width, $height, $type, $attr) = getimagesize("" . get_protocol_prefix() . "$BASEURL/$bitbucket/$name");
		$url = str_replace(" ", "%20", htmlspecialchars("$bitbucket/$name"));
		print("<tr>");
		print("<td><center><a href=$url><img src=\"".$url."\" border=0 onLoad='SetSize(this, 400)'></a></center>");
		print("Uploaded by:  " . \App\Support\UserDisplay::username($arr['owner']). "<br />");
		print("(#{$arr['id']}) Filename: $name ($width&nbsp;x&nbsp;$height)");
		if (\App\Support\UserDisplay::currentClass() >= UC_MODERATOR)
		print(" <b><a href=?delete={$arr['id']}>[Delete]</a></b><br />");
		print("Added: $date $time");
		print("</tr>");
		}
		print("</table>");
		}
		echo
		$pagerbottom;
		\App\Support\Html::stdfoot();
