<?php
extract($GLOBALS, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
if (isset($_GET['q']) && $_GET['q'] != '')
{
	$searchstr = trim($_GET['q']);
	
	$suggestRows = \Nexus\Database\NexusDB::table('suggest')
	    ->selectRaw('keywords AS suggest, COUNT(*) AS count')
	    ->where('keywords', 'like', $searchstr . '%')
	    ->groupBy('keywords')
	    ->orderByDesc('count')
	    ->orderByDesc('keywords')
	    ->limit(10)
	    ->get();
	$result = array(htmlspecialchars($searchstr), array(), array());
	$i = 0;
	foreach ($suggestRows as $suggest){
		$suggest = (array) $suggest;
		if (strlen($suggest['suggest']) > 25) continue;
		$result[1][] = $suggest['suggest'];
		$result[2][] = $suggest['count']." times";
		$i++;
		if ($i >= 5) break;
	}
	echo json_encode($result);
}
