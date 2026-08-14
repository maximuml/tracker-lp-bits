<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (!isset($CURUSER)) $CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
if (!isset($Cache)) $Cache = \App\Support\SupportContext::getCache();
if (!isset($BASEURL)) $BASEURL = \App\Support\SupportContext::getGlobal('BASEURL', '');
if (!isset($lang_log)) $lang_log = (array) (\App\Support\SupportContext::getGlobal('lang_log') ?? []);
if (!\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::LOG))
{
\App\Support\LegacyResponse::abort($lang_log['std_sorry'], $lang_log['std_permission_denied_only'].\App\Support\UserClass::name($log_class,false,true,true).sprintf($lang_log['std_or_above_can_view'], \App\Models\Setting::getSiteName()), false);
}

$q = htmlspecialchars(trim(\App\Support\SupportContext::getQuery('query') ?? ''));

if (!function_exists('permissiondeny')) { function permissiondeny(){
$lang_log = (array) (\App\Support\SupportContext::getGlobal('lang_log') ?? []);
	\App\Support\LegacyResponse::abort($lang_log['std_sorry'], $lang_log['std_permission_denied'], false);
} }

if (!function_exists('logmenu')) { function logmenu($selected = "dailylog"){
$lang_log = (array) (\App\Support\SupportContext::getGlobal('lang_log') ?? []);
		\App\Support\Frame::mainFrameOpen();
		print ("<div id=\"lognav\"><ul id=\"logmenu\" class=\"menu\">");
		print ("<li" . ($selected == "dailylog" ? " class=selected" : "") . "><a href=\"?action=dailylog\">".$lang_log['text_daily_log']."</a></li>");
		print ("<li" . ($selected == "chronicle" ? " class=selected" : "") . "><a href=\"?action=chronicle\">".$lang_log['text_chronicle']."</a></li>");
		print ("<li" . ($selected == "news" ? " class=selected" : "") . "><a href=\"?action=news\">".$lang_log['text_news']."</a></li>");
		print ("<li" . ($selected == "poll" ? " class=selected" : "") . "><a href=\"?action=poll\">".$lang_log['text_poll']."</a></li>");
		print ("</ul></div>");
		\App\Support\Frame::mainFrameClose();
} }

if (!function_exists('searchtable')) { function searchtable($title, $action, $opts = array()){
$lang_log = (array) (\App\Support\SupportContext::getGlobal('lang_log') ?? []);
$q = \App\Support\SupportContext::getGlobal('q');
		print("<table border=1 cellspacing=0 width=940 cellpadding=5>\n");
		print("<tr><td class=colhead align=left>".$title."</td></tr>\n");
		print("<tr><td class=toolbox align=left><form method=\"get\" action='" . \App\Support\SupportContext::getServerValue('REQUEST_URI') . "'>\n");
		print("<input type=\"text\" name=\"query\" style=\"width:500px\" value=\"".$q."\">\n");
		if ($opts) {
			print($lang_log['text_in']."<select name=search>");
			$selectedSearchValue = is_scalar(\App\Support\SupportContext::getQuery('search') ?? '') ? (string) (\App\Support\SupportContext::getQuery('search') ?? '') : '';
			foreach($opts as $value => $text) {
				print("<option value='".$value."'". ($value === $selectedSearchValue ? " selected" : "").">".$text."</option>");
			}
			print("</select>");
			}
		print("<input type=\"hidden\" name=\"action\" value='".$action."'>&nbsp;&nbsp;");
		print("<input type=submit value=" . $lang_log['submit_search'] . "></form>\n");
		print("</td></tr></table><br />\n");
} }

if (!function_exists('additem')) { function additem($title, $action){
$lang_log = (array) (\App\Support\SupportContext::getGlobal('lang_log') ?? []);
		print("<table border=1 cellspacing=0 width=940 cellpadding=5>\n");
		print("<tr><td class=colhead align=left>".$title."</td></tr>\n");
		print("<tr><td class=toolbox align=left><form method=\"post\" action='" . \App\Support\SupportContext::getServerValue('REQUEST_URI') . "'>\n");
		print("<textarea name=\"txt\" style=\"width:500px\" rows=\"3\" >".$title."</textarea>\n");
		print("<input type=\"hidden\" name=\"action\" value=".$action.">");
		print("<input type=\"hidden\" name=\"do\" value=\"add\">");
		print("<input type=submit value=" . $lang_log['submit_add'] . "></form>\n");
		print("</td></tr></table><br />\n");
} }

if (!function_exists('edititem')) { function edititem($title, $action, $id){
$lang_log = (array) (\App\Support\SupportContext::getGlobal('lang_log') ?? []);
		$row = \App\Repositories\LogRepository::getGenericById($action, $id);
		if ($row) {
		print("<table border=1 cellspacing=0 width=940 cellpadding=5>\n");
		print("<tr><td class=colhead align=left>".$title."</td></tr>\n");
		print("<tr><td class=toolbox align=left><form method=\"post\" action='" . \App\Support\SupportContext::getServerValue('REQUEST_URI') . "'>\n");
		print("<textarea name=\"txt\" style=\"width:500px\" rows=\"3\" >".$row["txt"]."</textarea>\n");
		print("<input type=\"hidden\" name=\"action\" value=".$action.">");
		print("<input type=\"hidden\" name=\"do\" value=\"update\">");
		print("<input type=\"hidden\" name=\"id\" value=".$id.">");
		print("<input type=submit value=" . $lang_log['submit_okay'] . " style='height: 20px' /></form>\n");
		print("</td></tr></table><br />\n");
		}
} }

$action = ((\App\Support\SupportContext::getPost('action') !== null)) ? htmlspecialchars(\App\Support\SupportContext::getPost('action')) : (((\App\Support\SupportContext::getQuery('action') !== null)) ? htmlspecialchars(\App\Support\SupportContext::getQuery('action')) : '');
$allowed_actions = array("dailylog","chronicle","news","poll");
if (!$action)
	$action='dailylog';
if (!in_array($action, $allowed_actions))
\App\Support\LegacyResponse::abort($lang_log['std_error'], $lang_log['std_invalid_action']);
else {
	switch ($action){
	case "dailylog":
		\App\Support\Html::stdhead($lang_log['head_site_log']);

		$search = \App\Support\SupportContext::getQuery("search") ?? '';

		$addparam = "";
		if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::CONFIDENTIAL_LOG)){
			if (in_array($search, ['mod', 'normal', 'all'])) {
				$addparam = "search=".rawurlencode($search)."&";
			}
		}

		if($q){
				$addparam .= "query=".rawurlencode($q)."&";
		}

		logmenu('dailylog');
		$opt = array ('all' => $lang_log['text_all'], 'normal' => $lang_log['text_normal'], 'mod' => $lang_log['text_mod']);
		searchtable($lang_log['text_search_log'], 'dailylog',$opt);

		$filters = ['search' => $search, 'query' => $q];
		$count = \App\Repositories\LogRepository::countSiteLog($filters);

		$perpage = 50;

		list($pagertop, $pagerbottom, $limit, $offset) = \App\Support\Pagination::pager($perpage, $count, "log.php?action=dailylog&".$addparam);

		$logRows = \App\Repositories\LogRepository::getSiteLog($filters, (int)$offset, $perpage);
		if (empty($logRows))
		print($lang_log['text_log_empty']);
		else
		{

		//echo $pagertop;

			print("<table width=940 border=1 cellspacing=0 cellpadding=5>\n");
			print("<tr><td class=colhead align=center><img class=\"time\" src=\"pic/trans.gif\" alt=\"time\" title=\"".$lang_log['title_time_added']."\" /></td><td class=colhead align=left>".$lang_log['col_event']);
            if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::CONFIDENTIAL_LOG)){
                print("<td class=colhead align=left>".$lang_log['col_user']."</td>");
            }
            print("</td></tr>\n");
			foreach ($logRows as $arr)
			{
				$color = "";
				if (strpos($arr['txt'],'was uploaded by')) $color = "green";
				if (strpos($arr['txt'],'was deleted by')) $color = "red";
				if (strpos($arr['txt'],'was added to the Request section')) $color = "purple";
				if (strpos($arr['txt'],'was edited by')) $color = "blue";
				if (strpos($arr['txt'],'settings updated by')) $color = "darkred";
				print("<tr><td class=\"rowfollow nowrap\" align=center>".\App\Support\Time::format($arr['added'],true,false)."</td><td class=rowfollow align=left><font color='".$color."'>".htmlspecialchars($arr['txt'])."</font></td>");
                if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::CONFIDENTIAL_LOG)){
                    print("<td class=rowfollow align=left>".($arr['uid'] > 0 ? \App\Support\UserDisplay::username($arr['uid']) : "System")."</td>");
                }
                print("</tr>\n");
			}
			print("</table>");

			echo $pagerbottom;
		}

		print($lang_log['time_zone_note']);

		\App\Support\Html::stdfoot();
		return;
		break;
	case "chronicle":
		\App\Support\Html::stdhead($lang_log['head_chronicle']);
		$addparam = $q ? "query=".rawurlencode($q)."&" : "";
		logmenu("chronicle");
		searchtable($lang_log['text_search_chronicle'], 'chronicle');
		if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::CHR_MANAGE))
			additem($lang_log['text_add_chronicle'], 'chronicle');
		if (
			(((\App\Support\SupportContext::getQuery('do') !== null)) && \App\Support\SupportContext::getQuery('do') == "del")
			|| (((\App\Support\SupportContext::getQuery('do') !== null)) && \App\Support\SupportContext::getQuery('do') == 'edit')
			|| (((\App\Support\SupportContext::getPost('do') !== null)) && \App\Support\SupportContext::getPost('do') == "add")
			|| (((\App\Support\SupportContext::getPost('do') !== null)) && \App\Support\SupportContext::getPost('do') == "update")
		)
		{
			$txt = \App\Support\SupportContext::getPost('txt') ?? '';
            if (\App\Support\UserDisplay::currentClass() < $chrmanage_class)
                permissiondeny();
			elseif (((\App\Support\SupportContext::getPost('do') !== null)) && \App\Support\SupportContext::getPost('do') == "add")
					\App\Repositories\LogRepository::addChronicle((int)$CURUSER["id"], $txt);
			elseif (((\App\Support\SupportContext::getPost('do') !== null)) && \App\Support\SupportContext::getPost('do') == "update"){
				$id = intval(\App\Support\SupportContext::getPost('id') ?? 0);
				if (!$id) { header("Location: log.php?action=chronicle"); return;}
				else \App\Repositories\LogRepository::updateChronicle($id, $txt);}
			else {$id = (intval(\App\Support\SupportContext::getQuery('id') ?? 0));
				if (!$id) { header("Location: log.php?action=chronicle"); return;}
				elseif (\App\Support\SupportContext::getQuery('do') == "del")
					\App\Repositories\LogRepository::deleteChronicle($id);
				elseif (((\App\Support\SupportContext::getQuery('do') !== null)) && \App\Support\SupportContext::getQuery('do') == "edit")
					edititem($lang_log['text_edit_chronicle'],'chronicle', $id);
				}
		}

		$count = \App\Repositories\LogRepository::countChronicle($q);

		$perpage = 50;

		list($pagertop, $pagerbottom, $limit, $offset) = \App\Support\Pagination::pager($perpage, $count, "log.php?action=chronicle&".$addparam);
		$chronicleRows = \App\Repositories\LogRepository::getChronicle($q, (int)$offset, $perpage);
		if (empty($chronicleRows))
		print($lang_log['text_chronicle_empty']);
		else
		{

		//echo $pagertop;

			print("<table width=940 border=1 cellspacing=0 cellpadding=5>\n");
			print("<tr><td class=colhead align=center>".$lang_log['col_date']."</td><td class=colhead align=left>".$lang_log['col_event']."</td>".(\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::CHR_MANAGE) ? "<td class=colhead align=center>".$lang_log['col_modify']."</td>" : "")."</tr>\n");
			foreach ($chronicleRows as $arr)
			{
				$date = \App\Support\Time::format($arr['added'],true,false);
				print("<tr><td class=rowfollow align=center><nobr>$date</nobr></td><td class=rowfollow align=left>".\App\Support\Format::formatComment($arr["txt"],true,false,true)."</td>".(\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::CHR_MANAGE) ? "<td align=center nowrap><b><a href=\"?action=chronicle&do=edit&id=".$arr["id"]."\">".$lang_log['text_edit']."</a>&nbsp;|&nbsp;<a href=\"?action=chronicle&do=del&id=".$arr["id"]."\"><font color=red>".$lang_log['text_delete']."</font></a></b></td>" : "")."</tr>\n");
			}
			print("</table>");
			echo $pagerbottom;
		}

		print($lang_log['time_zone_note']);

		\App\Support\Html::stdfoot();
		return;
		break;
	case "news":
		\App\Support\Html::stdhead($lang_log['head_news']);
		$search = \App\Support\SupportContext::getQuery("search") ?? '';
		$addparam = $q ? "search=".rawurlencode($search)."&query=".rawurlencode($q)."&" : "";
		logmenu("news");
		$opt = array ('title' => $lang_log['text_title'], 'body' => $lang_log['text_body'], 'both' => $lang_log['text_both']);
		searchtable($lang_log['text_search_news'], 'news', $opt);

		$filters = ['search' => $search, 'query' => $q];
		$count = \App\Repositories\LogRepository::countNews($filters);

		$perpage = 20;

		list($pagertop, $pagerbottom, $limit, $offset) = \App\Support\Pagination::pager($perpage, $count, "log.php?action=news&".$addparam);
		$newsRows = \App\Repositories\LogRepository::getNews($filters, (int)$offset, $perpage);
		if (empty($newsRows))
		print($lang_log['text_news_empty']);
		else
		{

		//echo $pagertop;
			foreach ($newsRows as $arr){
				$date = \App\Support\Time::format($arr['added'],true,false);
			print("<table width=940 border=1 cellspacing=0 cellpadding=5>\n");
			print("<tr><td class=rowhead width='10%'>".$lang_log['col_title']."</td><td class=rowfollow align=left>".$arr["title"]."</td></tr><tr><td class=rowhead width='10%'>".$lang_log['col_date']."</td><td class=rowfollow align=left>".$date."</td></tr><tr><td class=rowhead width='10%'>".$lang_log['col_body']."</td><td class=rowfollow align=left>".\App\Support\Format::formatComment($arr["body"],false,false,true)."</td></tr>\n");
			print("</table><br />");
			}
			echo $pagerbottom;
		}

		print($lang_log['time_zone_note']);

		\App\Support\Html::stdfoot();
		return;
		break;
	case "poll":
		$do = \App\Support\SupportContext::getQuery("do") ?? '';
  		$pollid = intval(\App\Support\SupportContext::getQuery("pollid") ?? 0);
  		$returnto = htmlspecialchars(\App\Support\SupportContext::getQuery("returnto") ?? '');
  		if ($do == "delete")
  		{
  		if (!\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::CHR_MANAGE))
  		\App\Support\LegacyResponse::abort($lang_log['std_error'], $lang_log['std_permission_denied']);

  		\App\Support\LegacyResponse::assertId($pollid, true);

   		$sure = \App\Support\SupportContext::getQuery("sure") ?? '';
   		if (!$sure)
    		\App\Support\LegacyResponse::abort($lang_log['std_delete_poll'], $lang_log['std_delete_poll_confirmation'] .
    		"<a href=?action=poll&do=delete&pollid=$pollid&returnto=$returnto&sure=1>".$lang_log['std_here_if_sure'], false);

		\App\Repositories\LogRepository::deletePoll($pollid);
		$Cache->delete_value('current_poll_content');
		$Cache->delete_value('current_poll_result', true);
		if ($returnto == "main")
			header("Location: " . \App\Support\Http::protocolPrefix(\App\Support\Url::isSecure()) . "$BASEURL");
		else
			header("Location: " . \App\Support\Http::protocolPrefix(\App\Support\Url::isSecure()) . "$BASEURL/log.php?action=poll&deleted=1");
		return;
  }

  $pollcount = \App\Repositories\LogRepository::getPollCount();
  if ($pollcount == 0)
  	\App\Support\LegacyResponse::abort($lang_log['std_sorry'], $lang_log['std_no_polls']);
  $polls = \App\Repositories\LogRepository::getPollsExceptFirst();
  \App\Support\Html::stdhead($lang_log['head_previous_polls']);
  		logmenu("poll");
  		print("<table border=1 cellspacing=0 width=940 cellpadding=5>\n");
		//print("<tr><td class=colhead align=center>".$lang_log['text_previous_polls']."</td></tr>\n");

    if (!function_exists('srt')) { function srt($a,$b)
    {
      if ($a[0] > $b[0]) return -1;
      if ($a[0] < $b[0]) return 1;
      return 0;
    } }

  foreach ($polls as $poll)
  {
    $o = array($poll["option0"], $poll["option1"], $poll["option2"], $poll["option3"], $poll["option4"],
    $poll["option5"], $poll["option6"], $poll["option7"], $poll["option8"], $poll["option9"],
    $poll["option10"], $poll["option11"], $poll["option12"], $poll["option13"], $poll["option14"],
    $poll["option15"], $poll["option16"], $poll["option17"], $poll["option18"], $poll["option19"]);

    print("<tr><td align=center>\n");

    print("<p class=sub>");
    $added = \App\Support\Time::format($poll['added'], true, false);

    print($added);

    if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::POLL_MANAGE))
    {
    	print(" - [<a href=makepoll.php?action=edit&pollid={$poll['id']}><b>".$lang_log['text_edit']."</b></a>]\n");
			print(" - [<a href=?action=poll&do=delete&pollid={$poll['id']}><b>".$lang_log['text_delete']."</b></a>]\n");
		}

		print("<a name={$poll['id']}>");

		print("</p>\n");

    print("<table class=main border=1 cellspacing=0 cellpadding=5><tr><td class=text>\n");

    print("<p align=center><b>" . $poll["question"] . "</b></p>");

    $vs = \App\Repositories\LogRepository::getPollVoteCounts((int)$poll['id']);

    $tvotes = array_sum($vs);

    $os = array(); // votes and options: array(array(123, "Option 1"), array(45, "Option 2"))

    for ($i = 0; $i < count($o); ++$i)
		if ($o[$i])
			$os[$i] = array($vs[$i] ?? 0, $o[$i]);

    print("<table width=100% class=main border=0 cellspacing=0 cellpadding=0>\n");
    $i = 0;
    while ((isset($os[$i])))
    {
		$a = $os[$i];
	  	if ($tvotes > 0)
	  		$p = round($a[0] / $tvotes * 100);
	  	else
				$p = 0;
      print("<tr><td class=embedded>" . $a[1] . "&nbsp;&nbsp;</td><td class=\"embedded nowrap\">" .
        "<img class=\"bar_end\" src=\"pic/trans.gif\" alt=\"\" /><img class=\"unsltbar\" src=\"pic/trans.gif\" style=\"width: " . ($p * 3) . "px\" /><img class=\"bar_end\" src=\"pic/trans.gif\" alt=\"\" /> $p%</td></tr>\n");
      ++$i;
    }
    print("</table>\n");
	$tvotes = number_format($tvotes);
    print("<p align=center>".$lang_log['text_votes']."$tvotes</p>\n");

    print("</td></tr></table><br /><br />\n");

    print("</p></td></tr>\n");
}
	print("</table>");
		print($lang_log['time_zone_note']);
		\App\Support\Html::stdfoot();
		return;
		break;
	}
}

?>