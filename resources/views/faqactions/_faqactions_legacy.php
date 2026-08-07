<?php
extract($context, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
/*
+--------------------------------------------------------------------------
|   MySQL driven FAQ version 1.1 Beta
|   ========================================
|   by avataru
|   (c) 2002 - 2005 avataru
|   http://www.avataru.net
|   ========================================
|   Web: http://www.avataru.net
|   Release: 1/9/2005 1:03 AM
|   Email: avataru@avataru.net
|   Tracker: http://www.sharereactor.ro
+---------------------------------------------------------------------------
|
|   > FAQ Management actions
|   > Written by avataru
|   > Date started: 1/7/2005
|
+--------------------------------------------------------------------------
*/


if (get_user_class() < UC_ADMINISTRATOR) {
	stderr("Error","Only Administrators and above can modify the FAQ, sorry.");
}

function clear_faq_cache()
{
    \Nexus\Database\NexusDB::cache_del('faq');
}
//stdhead("FAQ Management");

// ACTION: reorder - reorder sections and items
if (((\App\Support\SupportContext::getQuery('action') !== null)) && \App\Support\SupportContext::getQuery('action') == "reorder") {
	foreach(\App\Support\SupportContext::getPost('order') as $id => $position) {
		\Nexus\Database\NexusDB::table('faq')->where('id', (int)$id)->update(['order' => (int)$position]);
	}
	header("Location: " . get_protocol_prefix() . "$BASEURL/faqmanage.php");
	return;
}

// ACTION: edit - edit a section or item
elseif (((\App\Support\SupportContext::getQuery('action') !== null)) && \App\Support\SupportContext::getQuery('action') == "edit" && ((\App\Support\SupportContext::getQuery('id') !== null))) {
	stdhead("FAQ Management");
	begin_main_frame();
	print("<h1 align=\"center\">Edit Section or Item</h1>");

	$row = \Nexus\Database\NexusDB::table('faq')->where('id', (int)\App\Support\SupportContext::getQuery('id'))->first();
	if ($row) {
		$arr = (array) $row;
		$arr['question'] = htmlspecialchars($arr['question']);
		$arr['answer'] = htmlspecialchars($arr['answer']);
		if ($arr['type'] == "item") {
			$lang_id = $arr['lang_id'];
			print("<form method=\"post\" action=\"faqactions.php?action=edititem\">");
			print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"10\" align=\"center\">\n");
			print("<tr><td>ID:</td><td>{$arr['id']} <input type=\"hidden\" name=\"id\" value=\"{$arr['id']}\" /></td></tr>\n");
			print("<tr><td>Question:</td><td><input style=\"width: 600px;\" type=\"text\" name=\"question\" value=\"{$arr['question']}\" /></td></tr>\n");
			print("<tr><td style=\"vertical-align: top;\">Answer:</td><td><textarea rows=20 style=\"width: 600px; height=600px;\" name=\"answer\">{$arr['answer']}</textarea></td></tr>\n");
			if ($arr['flag'] == "0") print("<tr><td>Status:</td><td><select name=\"flag\" style=\"width: 110px;\"><option value=\"0\" style=\"color: #FF0000;\" selected=\"selected\">Hidden</option><option value=\"1\" style=\"color: #000000;\">Normal</option><option value=\"2\" style=\"color: #0000FF;\">Updated</option><option value=\"3\" style=\"color: #008000;\">New</option></select></td></tr>");
			elseif ($arr['flag'] == "2") print("<tr><td>Status:</td><td><select name=\"flag\" style=\"width: 110px;\"><option value=\"0\" style=\"color: #FF0000;\">Hidden</option><option value=\"1\" style=\"color: #000000;\">Normal</option><option value=\"2\" style=\"color: #0000FF;\" selected=\"selected\">Updated</option><option value=\"3\" style=\"color: #008000;\">New</option></select></td></tr>");
			elseif ($arr['flag'] == "3") print("<tr><td>Status:</td><td><select name=\"flag\" style=\"width: 110px;\"><option value=\"0\" style=\"color: #FF0000;\">Hidden</option><option value=\"1\" style=\"color: #000000;\">Normal</option><option value=\"2\" style=\"color: #0000FF;\">Updated</option><option value=\"3\" style=\"color: #008000;\" selected=\"selected\">New</option></select></td></tr>");
			else print("<tr><td>Status:</td><td><select name=\"flag\" style=\"width: 110px;\"><option value=\"0\" style=\"color: #FF0000;\">Hidden</option><option value=\"1\" style=\"color: #000000;\" selected=\"selected\">Normal</option><option value=\"2\" style=\"color: #0000FF;\">Updated</option><option value=\"3\" style=\"color: #008000;\">New</option></select></td></tr>");
			print("<tr><td>Category:</td><td><select style=\"width: 400px;\" name=\"categ\" />");
			$rows2 = \Nexus\Database\NexusDB::table('faq')
			    ->where('type', 'categ')
			    ->where('lang_id', $lang_id)
			    ->orderBy('order')
			    ->get(['id', 'question', 'link_id']);
			foreach ($rows2 as $row2) {
				$arr2 = (array) $row2;
				$selected = ($arr2['link_id'] == $arr['categ']) ? " selected=\"selected\"" : "";
				print("<option value=\"{$arr2['link_id']}\"". $selected .">{$arr2['question']}</option>");
			}
			print("</td></tr>\n");
			print("<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" name=\"edit\" value=\"Edit\" style=\"width: 60px;\"></td></tr>\n");
			print("</table>");
		}
		elseif ($arr['type'] == "categ") {
			$lang_name = \Nexus\Database\NexusDB::table('language')->where('id', $arr['lang_id'])->value('lang_name') ?? '';
			print("<form method=\"post\" action=\"faqactions.php?action=editsect\">");
			print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"10\" align=\"center\">\n");
			print("<tr><td>ID:</td><td>{$arr['id']} <input type=\"hidden\" name=\"id\" value=\"{$arr['id']}\" /></td></tr>\n");
			print("<tr><td>Language:</td><td>$lang_name</td></tr>\n");
			print("<tr><td>Title:</td><td><input style=\"width: 300px;\" type=\"text\" name=\"title\" value=\"{$arr['question']}\" /></td></tr>\n");
			if ($arr['flag'] == "0") print("<tr><td>Status:</td><td><select name=\"flag\" style=\"width: 110px;\"><option value=\"0\" style=\"color: #FF0000;\" selected=\"selected\">Hidden</option><option value=\"1\" style=\"color: #000000;\">Normal</option></select></td></tr>");
			else print("<tr><td>Status:</td><td><select name=\"flag\" style=\"width: 110px;\"><option value=\"0\" style=\"color: #FF0000;\">Hidden</option><option value=\"1\" style=\"color: #000000;\" selected=\"selected\">Normal</option></select></td></tr>");
			print("<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" name=\"edit\" value=\"Edit\" style=\"width: 60px;\"></td></tr>\n");
			print("</table>");
		}
	}

	end_main_frame();
	stdfoot();
}

// subACTION: edititem - edit an item
elseif (((\App\Support\SupportContext::getQuery('action') !== null)) && \App\Support\SupportContext::getQuery('action') == "edititem" && \App\Support\SupportContext::getPost('id') != NULL && \App\Support\SupportContext::getPost('question') != NULL && \App\Support\SupportContext::getPost('answer') != NULL && \App\Support\SupportContext::getPost('flag') != NULL && \App\Support\SupportContext::getPost('categ') != NULL) {
	$question = \App\Support\SupportContext::getPost('question');
	$answer = \App\Support\SupportContext::getPost('answer');
	\Nexus\Database\NexusDB::table('faq')->where('id', (int)\App\Support\SupportContext::getPost('id'))->update([
	    'question' => $question,
	    'answer' => $answer,
	    'flag' => (int)\App\Support\SupportContext::getPost('flag'),
	    'categ' => (int)\App\Support\SupportContext::getPost('categ'),
	]);
    clear_faq_cache();
	header("Location: " . get_protocol_prefix() . "$BASEURL/faqmanage.php");
	return;
}

// subACTION: editsect - edit a section
elseif (((\App\Support\SupportContext::getQuery('action') !== null)) && \App\Support\SupportContext::getQuery('action') == "editsect" && \App\Support\SupportContext::getPost('id') != NULL && \App\Support\SupportContext::getPost('title') != NULL && \App\Support\SupportContext::getPost('flag') != NULL) {
	$title = \App\Support\SupportContext::getPost('title');
	\Nexus\Database\NexusDB::table('faq')->where('id', (int)\App\Support\SupportContext::getPost('id'))->update([
	    'question' => $title,
	    'answer' => '',
	    'flag' => (int)\App\Support\SupportContext::getPost('flag'),
	    'categ' => 0,
	]);
    clear_faq_cache();
	header("Location: " . get_protocol_prefix() . "$BASEURL/faqmanage.php");
	return;
}

// ACTION: delete - delete a section or item
elseif (((\App\Support\SupportContext::getQuery('action') !== null)) && \App\Support\SupportContext::getQuery('action') == "delete" && ((\App\Support\SupportContext::getQuery('id') !== null))) {
	if (\App\Support\SupportContext::getQuery('confirm') == "yes") {
		\Nexus\Database\NexusDB::table('faq')->where('id', (int)(\App\Support\SupportContext::getQuery('id') ?? 0))->delete();
		header("Location: " . get_protocol_prefix() . "$BASEURL/faqmanage.php");
		return;
	}
	else {
		stdhead("FAQ Management");
		begin_main_frame();
		print("<h1 align=\"center\">Confirmation required</h1>");
        $id = intval(\App\Support\SupportContext::getQuery('id') ?? 0);
		print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"5\" align=\"center\" width=\"95%\">\n<tr><td align=\"center\">Please click <a href=\"faqactions.php?action=delete&id={$id}&confirm=yes\">here</a> to confirm.</td></tr>\n</table>\n");
		end_main_frame();
		stdfoot();
	}
}

// ACTION: additem - add a new item
elseif (((\App\Support\SupportContext::getQuery('action') !== null)) && \App\Support\SupportContext::getQuery('action') == "additem" && \App\Support\SupportContext::getQuery('inid') && \App\Support\SupportContext::getQuery('langid')) {
	stdhead("FAQ Management");
	begin_main_frame();
	print("<h1 align=\"center\">Add Item</h1>");
	print("<form method=\"post\" action=\"faqactions.php?action=addnewitem\">");
	print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"10\" align=\"center\">\n");
	print("<tr><td>Question:</td><td><input style=\"width: 600px;\" type=\"text\" name=\"question\" value=\"\" /></td></tr>\n");
	print("<tr><td style=\"vertical-align: top;\">Answer:</td><td><textarea rows=20 style=\"width: 600px; height=600px;\" name=\"answer\"></textarea></td></tr>\n");
	print("<tr><td>Status:</td><td><select name=\"flag\" style=\"width: 110px;\"><option value=\"0\" style=\"color: #FF0000;\">Hidden</option><option value=\"1\" style=\"color: #000000;\">Normal</option><option value=\"2\" style=\"color: #0000FF;\">Updated</option><option value=\"3\" style=\"color: #008000;\" selected=\"selected\">New</option></select></td></tr>");
	print("<input type=hidden name=categ value=\"".(intval(\App\Support\SupportContext::getQuery('inid') ?? 0))."\">");
	print("<input type=hidden name=langid value=\"".(intval(\App\Support\SupportContext::getQuery('langid') ?? 0))."\">");
	print("<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" value=\"Add\" style=\"width: 60px;\"></td></tr>\n");
	print("</table></form>");
	end_main_frame();
	stdfoot();
}

// ACTION: addsection - add a new section
elseif (((\App\Support\SupportContext::getQuery('action') !== null)) && \App\Support\SupportContext::getQuery('action') == "addsection") {
	stdhead("FAQ Management");
	begin_main_frame();
	print("<h1 align=\"center\">Add Section</h1>");
	print("<form method=\"post\" action=\"faqactions.php?action=addnewsect\">");
	print("<table border=\"1\" cellspacing=\"0\" cellpadding=\"10\" align=\"center\">\n");
	print("<tr><td>Title:</td><td><input style=\"width: 300px;\" type=\"text\" name=\"title\" value=\"\" /></td></tr>\n");
	$s = "<select name=language>";
	$langs = langlist("rule_lang");
	foreach ($langs as $row)
	{
		if($row["site_lang_folder"] == $deflang) $se = " selected"; else $se = "";
		$s .= "<option value=". $row["id"] . $se. ">" . htmlspecialchars($row["lang_name"]) . "</option>\n";
	}
	$s .= "</select>";
	print("<tr><td>Language:</td><td>".$s."</td></tr>");
	print("<tr><td>Status:</td><td><select name=\"flag\" style=\"width: 110px;\"><option value=\"0\" style=\"color: #FF0000;\">Hidden</option><option value=\"1\" style=\"color: #000000;\" selected=\"selected\">Normal</option></select></td></tr>");
	print("<tr><td colspan=\"2\" align=\"center\"><input type=\"submit\" name=\"edit\" value=\"Add\" style=\"width: 60px;\"></td></tr>\n");
	print("</table>");
	end_main_frame();
	stdfoot();
}

// subACTION: addnewitem - add a new item to the db
elseif (((\App\Support\SupportContext::getQuery('action') !== null)) && \App\Support\SupportContext::getQuery('action') == "addnewitem" && \App\Support\SupportContext::getPost('question') != NULL && \App\Support\SupportContext::getPost('answer') != NULL) {
	$question = \App\Support\SupportContext::getPost('question');
	$answer = \App\Support\SupportContext::getPost('answer');
	$categ = intval(\App\Support\SupportContext::getPost('categ') ?? 0);
	$langid = intval(\App\Support\SupportContext::getPost('langid') ?? 0);
	$maxRow = \Nexus\Database\NexusDB::table('faq')
	    ->where('type', 'item')
	    ->where('categ', $categ)
	    ->where('lang_id', $langid)
	    ->selectRaw('MAX(`order`) AS maxorder, MAX(`link_id`) AS maxlinkid')
	    ->first();
	if ($maxRow) {
		$arr = (array) $maxRow;
		$order = ($arr['maxorder'] ?? 0) + 1;
		$link_id = ($arr['maxlinkid'] ?? 0) + 1;
	} else {
		$order = 1;
		$link_id = 1;
	}
	\Nexus\Database\NexusDB::table('faq')->insert([
	    'link_id' => $link_id,
	    'type' => 'item',
	    'lang_id' => $langid,
	    'question' => $question,
	    'answer' => $answer,
	    'flag' => (int)(\App\Support\SupportContext::getPost('flag') ?? 0),
	    'categ' => $categ,
	    'order' => $order,
	]);
    clear_faq_cache();
	header("Location: " . get_protocol_prefix() . "$BASEURL/faqmanage.php");
	return;
}

// subACTION: addnewsect - add a new section to the db
elseif (((\App\Support\SupportContext::getQuery('action') !== null)) && \App\Support\SupportContext::getQuery('action') == "addnewsect" && \App\Support\SupportContext::getPost('title') != NULL && \App\Support\SupportContext::getPost('flag') != NULL) {
	$title = \App\Support\SupportContext::getPost('title');
	$language = intval(\App\Support\SupportContext::getPost('language') ?? 0);
	$maxRow = \Nexus\Database\NexusDB::table('faq')
	    ->where('type', 'categ')
	    ->where('lang_id', $language)
	    ->selectRaw('MAX(`order`) AS maxorder, MAX(`link_id`) AS maxlinkid')
	    ->first();
	if ($maxRow) {
		$arr = (array) $maxRow;
		$order = ($arr['maxorder'] ?? 0) + 1;
		$link_id = ($arr['maxlinkid'] ?? 0) + 1;
	} else {
		$order = 1;
		$link_id = 1;
	}
	\Nexus\Database\NexusDB::table('faq')->insert([
	    'link_id' => $link_id,
	    'type' => 'categ',
	    'lang_id' => $language,
	    'question' => $title,
	    'answer' => '',
	    'flag' => (int)(\App\Support\SupportContext::getPost('flag') ?? 0),
	    'categ' => 0,
	    'order' => $order,
	]);
    clear_faq_cache();
	header("Location: " . get_protocol_prefix() . "$BASEURL/faqmanage.php");
	return;
} else {
	header("Location: " . get_protocol_prefix() . "$BASEURL/faqmanage.php");
	return;
}
