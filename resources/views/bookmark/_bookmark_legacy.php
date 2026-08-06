<?php
extract($context, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
//Send some headers to keep the user's browser from caching the response.
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT" );
header("Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . "GMT" );
header("Cache-Control: no-cache, must-revalidate" );
header("Pragma: no-cache" );
header("Content-Type: text/xml; charset=utf-8");

$torrentid = intval($_GET['torrentid'] ?? 0);
if(isset($CURUSER))
{
    $searchRep = new \App\Repositories\SearchRepository();
	$bookmark = \Nexus\Database\NexusDB::table('bookmarks')->where('torrentid', $torrentid)->where('userid', $CURUSER['id'])->first();
	if ($bookmark){
	    $bookmarkId = $bookmark->id;
        $searchRep->deleteBookmark($bookmarkId);
		\Nexus\Database\NexusDB::table('bookmarks')->where('id', $bookmarkId)->delete();
		$Cache->delete_value('user_'.$CURUSER['id'].'_bookmark_array');
		echo "deleted";
	} else {
		$bookmarkId = \Nexus\Database\NexusDB::table('bookmarks')->insertGetId([
		    'torrentid' => $torrentid,
		    'userid' => $CURUSER['id'],
		]);
		$Cache->delete_value('user_'.$CURUSER['id'].'_bookmark_array');
        $searchRep->addBookmark($bookmarkId);
		echo "added";
	}
}
else echo "failed";
