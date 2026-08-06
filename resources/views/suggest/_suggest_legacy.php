<?php
extract($context, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


//Send some headers to keep the user's browser from caching the response.
header("Expires: Mon, 26 Jul 1997 05:00:00 GMT" ); 
header("Last-Modified: " . gmdate( "D, d M Y H:i:s" ) . "GMT" ); 
header("Cache-Control: no-cache, must-revalidate" ); 
header("Pragma: no-cache" );
header("Content-Type: text/xml; charset=utf-8");

if (isset($_GET['q']) && $_GET['q'] != '')
{
	$searchstr = unesc(trim($_GET['q']));
	
	$suggestRows = \Nexus\Database\NexusDB::table('suggest')
	    ->selectRaw('keywords AS suggest, COUNT(*) AS count')
	    ->where('keywords', 'like', $searchstr . '%')
	    ->groupBy('keywords')
	    ->orderByDesc('count')
	    ->orderByDesc('keywords')
	    ->limit(10)
	    ->get();
	$result = "";
	$i = 0;
	foreach ($suggestRows as $suggest){
		$suggest = (array) $suggest;
		if (strlen($suggest['suggest']) > 25) continue;
		$result .= ($result == "" ? "" : "\r\n" ). $suggest['suggest'] . "\r\n" . $suggest['count'];
		$i++;
		if ($i >= 5) break;
	}
	echo $result;
}
