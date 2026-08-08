<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


$pollid = intval(\App\Support\SupportContext::getQuery('id') ?? 0);

if ($pollid)
{
	$poll = (array) \Nexus\Database\NexusDB::table('polls')->where('id', $pollid)->first();
	if (!$poll)
		stderr($lang_polloverview['std_error'], $lang_polloverview['text_no_poll_id']);

	stdhead($lang_polloverview['head_poll_overview']);
	print("<h1 align=\"center\">".$lang_polloverview['text_polls_overview']."</h1>\n");

	print("<table width=737 border=1 cellspacing=0 cellpadding=5><tr>\n" .
 "<td class=colhead align=center><nobr>".$lang_polloverview['col_id']."</nobr></td><td class=colhead><nobr>".$lang_polloverview['col_added']."</nobr></td><td class=colhead><nobr>".$lang_polloverview['col_question']."</nobr></td></tr>\n");

	$added = gettime($poll['added']);
	print("<tr><td align=center><a href=\"polloverview.php?id=".$poll['id']."\">".$poll['id']."</a></td><td>".$added."</td><td><a href=\"polloverview.php?id=".$poll['id']."\">".$poll['question']."</a></td></tr>\n");
	print("</table>\n");

	print("<h1 align=\"center\">".$lang_polloverview['text_poll_question']."</h1><br />\n");
	print("<table width=737 border=1 cellspacing=0 cellpadding=5><tr><td class=colhead>".$lang_polloverview['col_option_no']."</td><td class=colhead>".$lang_polloverview['col_options']."</td></tr>\n");
	for ($i = 0; $i < 20; $i++) {
		if ($poll['option'.$i] != "")
			print("<tr><td>".$i."</td><td>".$poll['option'.$i]."</td></tr>\n");
	}
 	print("</table>\n");

	$count = \Nexus\Database\NexusDB::table('pollanswers')->where('pollid', $pollid)->where('selection', '<', 20)->count();

	print("<h1 align=\"center\">".$lang_polloverview['text_polls_user_overview']."</h1>\n");

	if ($count == 0) {
		print("<p align=\"center\">".$lang_polloverview['text_no_users_voted']."</p>");
	}
	else{
		$perpage = 100;
		list($pagertop, $pagerbottom, , $offset, $rpp) = pager($perpage, $count, "?id=".$pollid."&");
		$answers = \Nexus\Database\NexusDB::table('pollanswers')
		    ->leftJoin('users', 'pollanswers.userid', '=', 'users.id')
		    ->where('pollanswers.pollid', $pollid)
		    ->where('pollanswers.selection', '<', 20)
		    ->orderBy('users.username')
		    ->offset($offset)
		    ->limit($rpp)
		    ->get(['pollanswers.*', 'users.username']);
		print($pagertop);
 		print("<table width=737 border=1 cellspacing=0 cellpadding=5>");
		print("<tr><td class=colhead align=center><nobr>".$lang_polloverview['col_username']."</nobr></td><td class=colhead align=center><nobr>".$lang_polloverview['col_selection']."<nobr></td></tr>\n");
		foreach ($answers as $answerRow) {
		    $useras = (array) $answerRow;
			$username = get_username($useras['userid']);
  			print("<tr><td>".$username."</td><td>".$poll['option'.$useras['selection']]."</td></tr>\n");
 		}
		print("</table>\n");
		print($pagerbottom);
	}
	stdfoot();
}
else
{
	$polls = \Nexus\Database\NexusDB::table('polls')->orderByDesc('id')->get(['id', 'added', 'question']);
 	if ($polls->isEmpty())
  		stderr($lang_polloverview['std_error'], $lang_polloverview['text_no_users_voted']);
	stdhead($lang_polloverview['head_poll_overview']);
	print("<h1 align=\"center\">".$lang_polloverview['text_polls_overview']."</h1>\n");

	print("<table width=737 border=1 cellspacing=0 cellpadding=5><tr>\n" .
 "<td class=colhead align=center><nobr>".$lang_polloverview['col_id']."</nobr></td><td class=colhead>".$lang_polloverview['col_added']."</td><td class=colhead><nobr>".$lang_polloverview['col_question']."</nobr></td></tr>\n");
	foreach ($polls as $pollRow) {
	    $poll = (array) $pollRow;
		$added = gettime($poll['added']);
		print("<tr><td align=center><a href=\"polloverview.php?id=".$poll['id']."\">".$poll['id']."</a></td><td>".$added."</td><td><a href=\"polloverview.php?id=".$poll['id']."\">".$poll['question']."</a></td></tr>\n");
	}
	print("</table>\n");
	stdfoot();
}
?>
