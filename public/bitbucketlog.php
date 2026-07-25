<?php
require "../include/bittorrent.php";
dbconn();
loggedinorreturn();
parked();
if (get_user_class() < UC_ADMINISTRATOR)
stderr("Sorry", "Access denied.");
$bucketpath = "$bitbucket";
if (get_user_class() >= UC_MODERATOR)
{
	 $delete = intval($_GET["delete"] ?? 0);
	 if (is_valid_id($delete)) {
		 $bitbucket = \Nexus\Database\NexusDB::table('bitbucket')->where('id', $delete)->first(['name', 'owner']);
		 if ($bitbucket) {
			 $a = (array) $bitbucket;
			 if (get_user_class() >= UC_MODERATOR || $a["owner"] == $CURUSER["id"]) {
				 \Nexus\Database\NexusDB::table('bitbucket')->where('id', $delete)->delete();
				 if (!unlink("$bucketpath/{$a['name']}"))
				 stderr("Warning", "Unable to unlink file: <b>{$a['name']}</b>. You should contact an administrator about this error.",false);
				 			} } }
}
stdhead("BitBucket Log");
$count = \Nexus\Database\NexusDB::table('bitbucket')->count();
$perpage = 10;
list($pagertop, $pagerbottom, , $offset, $rpp) = pager($perpage, $count, $_SERVER["PHP_SELF"] . "?out=" . ($_GET["out"] ?? '') . "&" );
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
		print("Uploaded by:  " . get_username($arr['owner']). "<br />");
		print("(#{$arr['id']}) Filename: $name ($width&nbsp;x&nbsp;$height)");
		if (get_user_class() >= UC_MODERATOR)
		print(" <b><a href=?delete={$arr['id']}>[Delete]</a></b><br />");
		print("Added: $date $time");
		print("</tr>");
		}
		print("</table>");
		}
		echo
		$pagerbottom;
		stdfoot();
?>
