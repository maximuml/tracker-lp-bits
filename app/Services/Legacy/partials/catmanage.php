<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

if (\App\Support\UserDisplay::currentClass() < UC_ADMINISTRATOR)
    \App\Support\LegacyResponse::permissionDenied();

$perpage = 50;
$pagerParam = '?action=view&type=' . (\App\Support\SupportContext::getQuery('type') ?? 'searchbox') . '&';
$lang_catmanage = (array) (\App\Support\SupportContext::getGlobal('lang_catmanage') ?? []);
\App\Support\SupportContext::setGlobal('perpage', $perpage);
\App\Support\SupportContext::setGlobal('pagerParam', $pagerParam);
if (!function_exists('return_category_db_table_name')) { function return_category_db_table_name($type)
{
	switch($type)
	{
		case 'category':
			$dbtablename = 'categories';
			break;
		case 'source':
			$dbtablename = 'sources';
			break;
		case 'medium':
			$dbtablename = 'media';
			break;
		case 'codec':
			$dbtablename = 'codecs';
			break;
		case 'standard':
			$dbtablename = 'standards';
			break;
		case 'processing':
			$dbtablename = 'processings';
			break;
		case 'audiocodec':
			$dbtablename = 'audiocodecs';
			break;
		case 'searchbox':
			$dbtablename = 'searchbox';
			break;
		case 'secondicon':
			$dbtablename = 'secondicons';
			break;
		case 'caticon':
			$dbtablename = 'caticons';
			break;
		default:
			return false;
	}
	return $dbtablename;
} }
if (!function_exists('return_category_mode_selection')) { function return_category_mode_selection($selname, $selectedid)
{
	$rows = \Nexus\Database\NexusDB::table('searchbox')->orderBy('id')->get(['id','name']);
	$selection = "<select name=\"".$selname."\">";
	foreach ($rows as $row) {
		$row = (array) $row;
		$selection .= "<option value=\"" . $row["id"] . "\"". ($row["id"]==$selectedid ? " selected=\"selected\"" : "").">" . htmlspecialchars($row["name"]) . "</option>\n";
	}
	$selection .= "</select>";
	return $selection;
} }

if (!function_exists('category_icon_selection')) { function category_icon_selection($iconId = 0)
{
    $rows = \Nexus\Database\NexusDB::table('caticons')->orderBy('id')->get(['id','name']);
    $selection = "<select name=\"icon_id\">";
    foreach ($rows as $row) {
        $row = (array) $row;
        $selection .= "<option value=\"" . $row["id"] . "\"". ($row["id"]==$iconId ? " selected=\"selected\"" : "").">" . htmlspecialchars($row["name"]) . "</option>\n";
    }
    $selection .= "</select>";
    return $selection;
} }

if (!function_exists('return_type_name')) { function return_type_name($type)
{
$lang_catmanage = (array) (\App\Support\SupportContext::getGlobal('lang_catmanage') ?? []);
	switch ($type)
	{
		case 'searchbox':
			$name = $lang_catmanage['text_searchbox'];
			break;
		case 'caticon':
			$name = $lang_catmanage['text_category_icons'];
			break;
		case 'secondicon':
			$name = $lang_catmanage['text_second_icons'];
			break;
		case 'category':
			$name = $lang_catmanage['text_categories'];
			break;
		case 'source':
			$name = $lang_catmanage['text_sources'];
			break;
		case 'medium':
			$name = $lang_catmanage['text_media'];
			break;
		case 'codec':
			$name = $lang_catmanage['text_codecs'];
			break;
		case 'standard':
			$name = $lang_catmanage['text_standards'];
			break;
		case 'processing':
			$name = $lang_catmanage['text_processings'];
			break;
		case 'audiocodec':
			$name = $lang_catmanage['text_audio_codecs'];
			break;
		default:
			return false;
	}
	return $name;
} }

if (!function_exists('print_type_list')) { function print_type_list($type){
$lang_catmanage = (array) (\App\Support\SupportContext::getGlobal('lang_catmanage') ?? []);
	$typename=return_type_name($type);
	\App\Support\Html::stdhead($lang_catmanage['head_category_management']." - ".$typename);
	\App\Support\Frame::mainFrameOpen();
?>
<h1 align="center"><?php echo $lang_catmanage['text_category_management']?> - <?php echo $typename?></h1>
<div>
<span id="item" onclick="dropmenu(this);"><span style="cursor: pointer;" class="big"><b><?php echo $lang_catmanage['text_manage']?></b></span>
<div id="itemlist" class="dropmenu" style="display: none"><ul>
<li><a href="?action=view&amp;type=searchbox"><?php echo $lang_catmanage['text_searchbox']?></a></li>
<li><a href="?action=view&amp;type=caticon"><?php echo $lang_catmanage['text_category_icons']?></a></li>
<li><a href="?action=view&amp;type=secondicon"><?php echo $lang_catmanage['text_second_icons']?></a></li>
<li><a href="?action=view&amp;type=category"><?php echo $lang_catmanage['text_categories']?></a></li>
<li><a href="?action=view&amp;type=source"><?php echo $lang_catmanage['text_sources']?></a></li>
<li><a href="?action=view&amp;type=medium"><?php echo $lang_catmanage['text_media']?></a></li>
<li><a href="?action=view&amp;type=codec"><?php echo $lang_catmanage['text_codecs']?></a></li>
<li><a href="?action=view&amp;type=standard"><?php echo $lang_catmanage['text_standards']?></a></li>
<li><a href="?action=view&amp;type=processing"><?php echo $lang_catmanage['text_processings']?></a></li>
<li><a href="?action=view&amp;type=audiocodec"><?php echo $lang_catmanage['text_audio_codecs']?></a></li>
</ul>
</div>
</span>
&nbsp;&nbsp;&nbsp;&nbsp;
<span id="add">
<a href="?action=add&amp;type=<?php echo $type?>" class="big"><b><?php echo $lang_catmanage['text_add']?></b></a>
</span>
</div>
<?php
} }
if (!function_exists('check_valid_type')) { function check_valid_type($type)
{
$lang_catmanage = (array) (\App\Support\SupportContext::getGlobal('lang_catmanage') ?? []);
	$validtype=array('searchbox', 'caticon', 'secondicon', 'category', 'source', 'medium', 'codec', 'standard', 'processing', 'audiocodec');
	if (!in_array($type, $validtype))
		\App\Support\LegacyResponse::abort($lang_catmanage['std_error'], $lang_catmanage['std_invalid_type']);
} }
if (!function_exists('print_sub_category_list')) { function print_sub_category_list($type)
{
$lang_catmanage = (array) (\App\Support\SupportContext::getGlobal('lang_catmanage') ?? []);
$perpage = \App\Support\SupportContext::getGlobal('perpage');
$pagerParam = \App\Support\SupportContext::getGlobal('pagerParam');
	$dbtablename = return_category_db_table_name($type);
	$num = \Nexus\Database\NexusDB::table($dbtablename)->count();
	if (!$num)
		print("<p align=\"center\">".$lang_catmanage['text_no_record_yet']."</p>");
	else{
		[$pagertop, $pagerbottom, , $offset, $perpage, ] = \App\Support\Pagination::pager($perpage, $num, $pagerParam);
		$rows = \Nexus\Database\NexusDB::table($dbtablename)->orderByDesc('id')->offset($offset)->limit($perpage)->get();
?>
<table border="1" cellspacing="0" cellpadding="5" width="97%">
<tr>
<td class="colhead"><?php echo $lang_catmanage['col_id']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_name']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_order']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_action']?></td>
</tr>
<?php
		foreach ($rows as $row) {
			$row = (array) $row;
?>
<tr>
<td class="colfollow"><?php echo $row['id']?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['name'])?></td>
<td class="colfollow"><?php echo $row['sort_index']?></td>
<td class="colfollow"><a href="javascript:confirm_delete('<?php echo $row['id']?>', '<?php echo $lang_catmanage['js_sure_to_delete_this']?>', 'type=<?php echo $type?>');"><?php echo $lang_catmanage['text_delete']?></a> | <a href="?action=edit&amp;type=<?php echo $type?>&amp;id=<?php echo $row['id']?>"><?php echo $lang_catmanage['text_edit']?></a></td>
</tr>
<?php
		}
?>
</table>
<?php
print($pagerbottom);
	}
} }
if (!function_exists('print_category_editor')) { function print_category_editor($type, $row='')
{
$lang_catmanage = (array) (\App\Support\SupportContext::getGlobal('lang_catmanage') ?? []);
$validsubcattype = \App\Support\SupportContext::getGlobal('validsubcattype');
	if (in_array($type, $validsubcattype))
		print_sub_category_editor($type, $row);
	else
	{
		$typename=return_type_name($type);
?>
<div style="width: 940px">
<h1 align="center"><a class="faqlink" href="?action=view&amp;type=<?php echo $type?>"><?php echo $typename?></a></h1>
<div>
<table border="1" cellspacing="0" cellpadding="10" width="100%">
<?php
		if ($type=='searchbox')
		{
			if ($row)
			{
				$name = $row['name'];
				$showsource = $row['showsource'];
				$showmedium = $row['showmedium'];
				$showcodec = $row['showcodec'];
				$showstandard = $row['showstandard'];
				$showprocessing = $row['showprocessing'];
				$showteam = $row['showteam'] ?? 0;
				$showaudiocodec = $row['showaudiocodec'];
				$catsperrow = $row['catsperrow'];
				$catpadding = $row['catpadding'];
				if (!empty($row['extra'])) {
				    $row['extra'] = json_decode($row['extra'], true);
                }
			}
			else
			{
				$name = '';
				$showsource = 0;
				$showmedium = 0;
				$showcodec = 0;
				$showstandard = 0;
				$showprocessing = 0;
				$showteam = 0;
				$showaudiocodec = 0;
				$catsperrow = 8;
				$catpadding = 3;
			}
			\App\Support\Html::tr($lang_catmanage['row_searchbox_name']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"name\" value=\"".htmlspecialchars($name)."\" style=\"width: 300px\" /> " . $lang_catmanage['text_searchbox_name_note'], 1);
			\App\Support\Html::tr($lang_catmanage['row_show_sub_category'], "<input type=\"checkbox\" name=\"showsource\" value=\"1\"".($showsource ? " checked=\"checked\"" : "")." /> " . $lang_catmanage['text_sources'] . "<input type=\"checkbox\" name=\"showmedium\" value=\"1\"".($showmedium ? " checked=\"checked\"" : "")." /> " . $lang_catmanage['text_media'] . "<input type=\"checkbox\" name=\"showcodec\" value=\"1\"".($showcodec ? " checked=\"checked\"" : "")." /> " . $lang_catmanage['text_codecs'] . "<input type=\"checkbox\" name=\"showstandard\" value=\"1\"".($showstandard ? " checked=\"checked\"" : "")." /> " . $lang_catmanage['text_standards'] . "<input type=\"checkbox\" name=\"showprocessing\" value=\"1\"".($showprocessing ? " checked=\"checked\"" : "")." /> " . $lang_catmanage['text_processings'] . "<input type=\"checkbox\" name=\"showaudiocodec\" value=\"1\"".($showaudiocodec ? " checked=\"checked\"" : "")." /> " . $lang_catmanage['text_audio_codecs']."<br />".$lang_catmanage['text_show_sub_category_note'], 1);

			//extra
            $extraCheckbox = "";
            foreach (\App\Models\SearchBox::listExtraText() as $name => $text) {
                $extraCheckbox .= sprintf(
                    '<label><input type="checkbox" name="extra[%s]" value="1"%s />%s</label>',
                    $name, !empty($row['extra'][$name]) ? ' checked' : '', $text
                );
            }
            \App\Support\Html::tr($lang_catmanage['row_searchbox_extras'], $extraCheckbox, 1);

			\App\Support\Html::tr($lang_catmanage['row_items_per_row']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"catsperrow\" value=\"".$catsperrow."\" style=\"width: 100px\" /> " . $lang_catmanage['text_items_per_row_note'], 1);
			\App\Support\Html::tr($lang_catmanage['row_padding_between_items']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"catpadding\" value=\"".$catpadding."\" style=\"width: 100px\" /> " . $lang_catmanage['text_padding_between_items_note'], 1);
            $field = new \Nexus\Field\Field();
            \App\Support\Html::tr($lang_catmanage['row_enable_custom_field'], $field->buildFieldCheckbox('custom_fields[]', $row['custom_fields'] ?? ''), 1);
            \App\Support\Html::tr($lang_catmanage['row_custom_field_display_name'], '<input type="text" name="custom_fields_display_name" style="width: 300px" value="' . ($row['custom_fields_display_name'] ?? '') . '" />', 1);
            $helpText = '<br/>' . $lang_catmanage['row_custom_field_display_help'];
            \App\Support\Html::tr($lang_catmanage['row_custom_field_display'], '<textarea name="custom_fields_display" style="width: 300px" rows="8">' . ($row['custom_fields_display'] ?? '') . '</textarea>' . $helpText, 1);
		}
		elseif ($type=='caticon')
		{
			if ($row)
			{
				$name = $row['name'];
				$folder = $row['folder'];
				$multilang = $row['multilang'];
				$secondicon = $row['secondicon'];
				$cssfile = $row['cssfile'];
				$designer = $row['designer'];
				$comment = $row['comment'];
			}
			else
			{
				$name = '';
				$folder = '';
				$multilang = 'no';
				$secondicon = 'no';
				$cssfile = '';
				$designer = '';
				$comment = '';
			}
?>
<tr><td colspan="2"><?php echo $lang_catmanage['text_icon_directory_note']?></td></tr>
<?php
			\App\Support\Html::tr($lang_catmanage['col_name']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"name\" value=\"".htmlspecialchars($name)."\" style=\"width: 300px\" /> ", 1);
			\App\Support\Html::tr($lang_catmanage['col_folder']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"folder\" value=\"".htmlspecialchars($folder)."\" style=\"width: 300px\" /><br />" . $lang_catmanage['text_folder_note'], 1);
			\App\Support\Html::tr($lang_catmanage['text_multi_language'], "<input type=\"checkbox\" name=\"multilang\" value=\"yes\"".($multilang == 'yes' ? " checked=\"checked\"" : "")." />".$lang_catmanage['text_yes'] ."<br />". $lang_catmanage['text_multi_language_note'], 1);
			\App\Support\Html::tr($lang_catmanage['text_second_icon'], "<input type=\"checkbox\" name=\"secondicon\" value=\"yes\"".($secondicon == 'yes' ? " checked=\"checked\"" : "")." />".$lang_catmanage['text_yes'] ."<br />". $lang_catmanage['text_second_icon_note'], 1);
			\App\Support\Html::tr($lang_catmanage['text_css_file'], "<input type=\"text\" name=\"cssfile\" value=\"".htmlspecialchars($cssfile)."\" style=\"width: 300px\" /> ". $lang_catmanage['text_css_file_note'], 1);
			\App\Support\Html::tr($lang_catmanage['text_designer'], "<input type=\"text\" name=\"designer\" value=\"".htmlspecialchars($designer)."\" style=\"width: 300px\" /> ". $lang_catmanage['text_designer_note'], 1);
			\App\Support\Html::tr($lang_catmanage['text_comment'], "<input type=\"text\" name=\"comment\" value=\"".htmlspecialchars($comment)."\" style=\"width: 300px\" /> ". $lang_catmanage['text_comment_note'], 1);
		}
		elseif ($type=='secondicon')
		{
			if ($row)
			{
				$name = $row['name'];
				$image = $row['image'];
				$class_name = $row['class_name'];
				$source = $row['source'];
				$medium = $row['medium'];
				$codec = $row['codec'];
				$standard = $row['standard'];
				$processing = $row['processing'];
				$team = 0;
				$audiocodec = $row['audiocodec'];
			}
			else
			{
				$name = '';
				$image = '';
				$class_name = '';
				$source = 0;
				$medium = 0;
				$codec = 0;
				$standard = 0;
				$processing = 0;
				$audiocodec = 0;
			}
			\App\Support\Html::tr($lang_catmanage['col_name']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"name\" value=\"".htmlspecialchars($name)."\" style=\"width: 300px\" /> " . $lang_catmanage['text_second_icon_name_note'], 1);
			\App\Support\Html::tr($lang_catmanage['col_image']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"image\" value=\"".htmlspecialchars($image)."\" style=\"width: 300px\" /><br />" . $lang_catmanage['text_image_note'], 1);
			\App\Support\Html::tr($lang_catmanage['text_class_name'], "<input type=\"text\" name=\"class_name\" value=\"".htmlspecialchars($class_name)."\" style=\"width: 300px\" /><br />" . $lang_catmanage['text_class_name_note'], 1);
			\App\Support\Html::tr($lang_catmanage['row_selections']."<font color=\"red\">*</font>", \App\Support\Html::torrentSelection(return_type_name('source'), 'source', return_category_db_table_name('source'), $source) . \App\Support\Html::torrentSelection(return_type_name('medium'), 'medium', return_category_db_table_name('medium'), $medium) . \App\Support\Html::torrentSelection(return_type_name('codec'), 'codec', return_category_db_table_name('codec'), $codec) . \App\Support\Html::torrentSelection(return_type_name('standard'), 'standard', return_category_db_table_name('standard'), $standard) . \App\Support\Html::torrentSelection(return_type_name('processing'), 'processing', return_category_db_table_name('processing'), $processing) . \App\Support\Html::torrentSelection(return_type_name('audiocodec'), 'audiocodec', return_category_db_table_name('audiocodec'), $audiocodec)."<br />".$lang_catmanage['text_selections_note'], 1);
		}
		elseif ($type=='category')
		{
			if ($row)
			{
				$name = $row['name'];
				$mode = $row['mode'];
				$image = $row['image'];
				$class_name = $row['class_name'];
				$sort_index = $row['sort_index'];
			}
			else
			{
				$name = '';
				$mode = 1;
				$image = '';
				$class_name = '';
				$sort_index = 0;
			}
			\App\Support\Html::tr($lang_catmanage['row_category_name']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"name\" value=\"".htmlspecialchars($name)."\" style=\"width: 300px\" /> " . $lang_catmanage['text_category_name_note'], 1);
			\App\Support\Html::tr($lang_catmanage['col_image']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"image\" value=\"".htmlspecialchars($image)."\" style=\"width: 300px\" /><br />" . $lang_catmanage['text_image_note'], 1);
			\App\Support\Html::tr($lang_catmanage['text_class_name'], "<input type=\"text\" name=\"class_name\" value=\"".htmlspecialchars($class_name)."\" style=\"width: 300px\" /><br />" . $lang_catmanage['text_class_name_note'], 1);
			\App\Support\Html::tr($lang_catmanage['row_mode']."<font color=\"red\">*</font>", return_category_mode_selection('mode', $mode), 1);
			\App\Support\Html::tr($lang_catmanage['text_category_icons']."<font color=\"red\">*</font>", category_icon_selection($row['icon_id'] ?? 0), 1);
			\App\Support\Html::tr($lang_catmanage['col_order'], "<input type=\"text\" name=\"sort_index\" value=\"".$sort_index."\" style=\"width: 100px\" /> " . $lang_catmanage['text_order_note'], 1);
		}
?>
</table>
</div>
<div style="text-align: center; margin-top: 10px;">
<input type="submit" value="<?php echo $lang_catmanage['submit_submit']?>" />
</div>
</div>
<?php
	}
} }
if (!function_exists('print_sub_category_editor')) { function print_sub_category_editor($type, $row='')
{
$lang_catmanage = (array) (\App\Support\SupportContext::getGlobal('lang_catmanage') ?? []);
	$typename=return_type_name($type);
	if ($row)
	{
		$name = $row['name'];
		$sort_index = $row['sort_index'];
	}
	else
	{
		$name = '';
		$sort_index = 0;
	}
?>
<div style="width: 940px">
<h1 align="center"><a class="faqlink" href="?action=view&amp;type=<?php echo $type?>"><?php echo $typename?></a></h1>
<table border="1" cellspacing="0" cellpadding="10" width="100%">
<?php
\App\Support\Html::tr($lang_catmanage['col_name']."<font color=\"red\">*</font>", "<input type=\"text\" name=\"name\" value=\"".htmlspecialchars($name)."\" style=\"width: 300px\" /> " . $lang_catmanage['text_subcategory_name_note'], 1);
\App\Support\Html::tr($lang_catmanage['col_order'], "<input type=\"text\" name=\"sort_index\" value=\"".$sort_index."\" style=\"width: 100px\" /> " . $lang_catmanage['text_order_note'], 1);
?>
</table>
<div style="text-align: center; margin-top: 10px;">
<input type="submit" value="<?php echo $lang_catmanage['submit_submit']?>" />
</div>
</div>
<?php
} }

$validsubcattype=array('source', 'medium', 'codec', 'standard', 'processing', 'audiocodec');
\App\Support\SupportContext::setGlobal('validsubcattype', $validsubcattype);
$type = \App\Support\SupportContext::getQuery('type') ?? '';
if ($type == '')
	$type = 'searchbox';
else
	check_valid_type($type);
$action = \App\Support\SupportContext::getQuery('action') ?? '';
if ($action == '')
	$action = 'view';
if ($action == 'view')
{
	print_type_list($type);
?>
<div style="margin-top: 8px">
<?php
	if (in_array($type, $validsubcattype)){
		print_sub_category_list($type);
	}
	elseif ($type=='searchbox')
	{
	$dbtablename=return_category_db_table_name($type);
	$num = \Nexus\Database\NexusDB::table($dbtablename)->count();
	if (!$num)
		print("<p align=\"center\">".$lang_catmanage['text_no_record_yet']."</p>");
	else{
		[$pagertop, $pagerbottom, , $offset, $perpage, ] = \App\Support\Pagination::pager($perpage, $num, $pagerParam);
		$rows = \Nexus\Database\NexusDB::table($dbtablename)->orderBy('id')->offset($offset)->limit($perpage)->get();
?>
<table border="1" cellspacing="0" cellpadding="5" width="97%">
<tr>
<td class="colhead"><?php echo $lang_catmanage['col_id']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_name']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_sub_category']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_sources']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_media']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_codecs']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_standards']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_processings']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_audio_codecs']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_per_row']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_padding']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_action']?></td>
</tr>
<?php
		foreach ($rows as $row) {
			$row = (array) $row;
?>
<tr>
<td class="colfollow"><?php echo $row['id']?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['name'])?></td>
<td class="colfollow"><?php echo $row['showsubcat'] ? "<font color=\"green\">".$lang_catmanage['text_enabled']."</font>" : "<font color=\"red\">".$lang_catmanage['text_disabled']."</font>"?></td>
<td class="colfollow"><?php echo $row['showsource'] ? "<font color=\"green\">".$lang_catmanage['text_enabled']."</font>" : "<font color=\"red\">".$lang_catmanage['text_disabled']."</font>"?></td>
<td class="colfollow"><?php echo $row['showmedium'] ? "<font color=\"green\">".$lang_catmanage['text_enabled']."</font>" : "<font color=\"red\">".$lang_catmanage['text_disabled']."</font>"?></td>
<td class="colfollow"><?php echo $row['showcodec'] ? "<font color=\"green\">".$lang_catmanage['text_enabled']."</font>" : "<font color=\"red\">".$lang_catmanage['text_disabled']."</font>"?></td>
<td class="colfollow"><?php echo $row['showstandard'] ? "<font color=\"green\">".$lang_catmanage['text_enabled']."</font>" : "<font color=\"red\">".$lang_catmanage['text_disabled']."</font>"?></td>
<td class="colfollow"><?php echo $row['showprocessing'] ? "<font color=\"green\">".$lang_catmanage['text_enabled']."</font>" : "<font color=\"red\">".$lang_catmanage['text_disabled']."</font>"?></td>
<td class="colfollow"><?php echo $row['showaudiocodec'] ? "<font color=\"green\">".$lang_catmanage['text_enabled']."</font>" : "<font color=\"red\">".$lang_catmanage['text_disabled']."</font>"?></td>
<td class="colfollow"><?php echo $row['catsperrow']?></td>
<td class="colfollow"><?php echo $row['catpadding']?></td>
<td class="colfollow"><a href="javascript:confirm_delete('<?php echo $row['id']?>', '<?php echo $lang_catmanage['js_sure_to_delete_this']?>', 'type=<?php echo $type?>');"><?php echo $lang_catmanage['text_delete']?></a> | <a href="?action=edit&amp;type=<?php echo $type?>&amp;id=<?php echo $row['id']?>"><?php echo $lang_catmanage['text_edit']?></a></td>
</tr>
<?php
		}
?>
</table>
<?php
print($pagerbottom);
	}
	}
	elseif($type=='caticon')
	{
	$dbtablename=return_category_db_table_name($type);
	$num = \Nexus\Database\NexusDB::table($dbtablename)->count();
	if (!$num)
		print("<p align=\"center\">".$lang_catmanage['text_no_record_yet']."</p>");
	else{
		[$pagertop, $pagerbottom, , $offset, $perpage, ] = \App\Support\Pagination::pager($perpage, $num, $pagerParam);
		$rows = \Nexus\Database\NexusDB::table($dbtablename)->orderBy('id')->offset($offset)->limit($perpage)->get();
?>
<table border="1" cellspacing="0" cellpadding="5" width="97%">
<tr>
<td class="colhead"><?php echo $lang_catmanage['col_id']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_name']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_folder']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_multi_language']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_second_icon']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_css_file']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_designer']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_comment']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_action']?></td>
</tr>
<?php
		foreach ($rows as $row) {
			$row = (array) $row;
?>
<tr>
<td class="colfollow"><?php echo $row['id']?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['name'])?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['folder'])?></td>
<td class="colfollow"><?php echo $row['multilang']=='yes' ? "<font color=\"green\">".$lang_catmanage['text_yes']."</font>" : "<font color=\"red\">".$lang_catmanage['text_no']."</font>"?></td>
<td class="colfollow"><?php echo $row['secondicon']=='yes' ? "<font color=\"green\">".$lang_catmanage['text_yes']."</font>" : "<font color=\"red\">".$lang_catmanage['text_no']."</font>"?></td>
<td class="colfollow"><?php echo $row['cssfile'] ? htmlspecialchars($row['cssfile']) : $lang_catmanage['text_none']?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['designer'])?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['comment'])?></td>
<td class="colfollow"><a href="javascript:confirm_delete('<?php echo $row['id']?>', '<?php echo $lang_catmanage['js_sure_to_delete_this']?>', 'type=<?php echo $type?>');"><?php echo $lang_catmanage['text_delete']?></a> | <a href="?action=edit&amp;type=<?php echo $type?>&amp;id=<?php echo $row['id']?>"><?php echo $lang_catmanage['text_edit']?></a></td>
</tr>
<?php
		}
?>
</table>
<?php
print($pagerbottom);
	}
	}
	elseif($type=='secondicon')
	{
	    $allSource = \App\Models\Source::query()->get()->keyBy('id');
	    $allMedia = \App\Models\Media::query()->get()->keyBy('id');
	    $allCodec = \App\Models\Codec::query()->get()->keyBy('id');
	    $allStandard = \App\Models\Standard::query()->get()->keyBy('id');
	    $allProcessing = \App\Models\Processing::query()->get()->keyBy('id');
		    $allAudioCodec = \App\Models\AudioCodec::query()->get()->keyBy('id');
	$dbtablename=return_category_db_table_name($type);
	$num = \Nexus\Database\NexusDB::table($dbtablename)->count();
	if (!$num)
		print("<p align=\"center\">".$lang_catmanage['text_no_record_yet']."</p>");
	else{
		[$pagertop, $pagerbottom, , $offset, $perpage, ] = \App\Support\Pagination::pager($perpage, $num, $pagerParam);
		$rows = \Nexus\Database\NexusDB::table($dbtablename)->orderBy('id')->offset($offset)->limit($perpage)->get();
?>
<table border="1" cellspacing="0" cellpadding="5" width="97%">
<tr>
<td class="colhead"><?php echo $lang_catmanage['col_id']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_name']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_image']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_class_name']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_sources']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_media']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_codecs']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_standards']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_processings']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_audio_codecs']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_action']?></td>
</tr>
<?php
		foreach ($rows as $row) {
			$row = (array) $row;
?>
<tr>
<td class="colfollow"><?php echo $row['id']?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['name'])?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['image'])?></td>
<td class="colfollow"><?php echo $row['class_name'] ? htmlspecialchars($row['class_name']) : $lang_catmanage['text_none']?></td>
<td class="colfollow"><?php echo optional($allSource->get($row['source']))->name?></td>
<td class="colfollow"><?php echo optional($allMedia->get($row['medium']))->name?></td>
<td class="colfollow"><?php echo optional($allCodec->get($row['codec']))->name?></td>
<td class="colfollow"><?php echo optional($allStandard->get($row['standard']))->name?></td>
<td class="colfollow"><?php echo optional($allProcessing->get($row['processing']))->name?></td>
<td class="colfollow"><?php echo optional($allAudioCodec->get($row['audiocodec']))->name?></td>
<td class="colfollow"><a href="javascript:confirm_delete('<?php echo $row['id']?>', '<?php echo $lang_catmanage['js_sure_to_delete_this']?>', 'type=<?php echo $type?>');"><?php echo $lang_catmanage['text_delete']?></a> | <a href="?action=edit&amp;type=<?php echo $type?>&amp;id=<?php echo $row['id']?>"><?php echo $lang_catmanage['text_edit']?></a></td>
</tr>
<?php
		}
?>
</table>
<?php
print($pagerbottom);
	}
	}
	elseif($type=='category')
	{
	$dbtablename=return_category_db_table_name($type);
	$num = \Nexus\Database\NexusDB::table($dbtablename)->count();
	if (!$num)
		print("<p align=\"center\">".$lang_catmanage['text_no_record_yet']."</p>");
	else{
		[$pagertop, $pagerbottom, , $offset, $perpage, ] = \App\Support\Pagination::pager($perpage, $num, $pagerParam);
		$rows = \Nexus\Database\NexusDB::table($dbtablename)
			->select([$dbtablename.'.*', 'searchbox.name as catmodename', 'caticons.name as icon_name'])
			->leftJoin('searchbox', $dbtablename.'.mode', '=', 'searchbox.id')
			->leftJoin('caticons', 'caticons.id', '=', $dbtablename.'.icon_id')
			->orderBy($dbtablename.'.mode')
			->orderBy($dbtablename.'.id')
			->offset($offset)
			->limit($perpage)
			->get();

?>
<table border="1" cellspacing="0" cellpadding="5" width="97%">
<tr>
<td class="colhead"><?php echo $lang_catmanage['col_id']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_mode']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_category_icons']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_name']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_image']?></td>
<td class="colhead"><?php echo $lang_catmanage['text_class_name']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_order']?></td>
<td class="colhead"><?php echo $lang_catmanage['col_action']?></td>
</tr>
<?php
		foreach ($rows as $row) {
			$row = (array) $row;
?>
<tr>
<td class="colfollow"><?php echo $row['id']?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['catmodename'])?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['icon_name'] ?? '')?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['name'])?></td>
<td class="colfollow"><?php echo htmlspecialchars($row['image'])?></td>
<td class="colfollow"><?php echo $row['class_name'] ? htmlspecialchars($row['class_name']) : $lang_catmanage['text_none']?></td>
<td class="colfollow"><?php echo $row['sort_index']?></td>
<td class="colfollow"><a href="javascript:confirm_delete('<?php echo $row['id']?>', '<?php echo $lang_catmanage['js_sure_to_delete_this']?>', 'type=<?php echo $type?>');"><?php echo $lang_catmanage['text_delete']?></a> | <a href="?action=edit&amp;type=<?php echo $type?>&amp;id=<?php echo $row['id']?>"><?php echo $lang_catmanage['text_edit']?></a></td>
</tr>
<?php
		}
?>
</table>
<?php
print($pagerbottom);
	}
	}
?>
</div>
<?php
	\App\Support\Frame::mainFrameClose();
	\App\Support\Html::stdfoot();
}
elseif($action == 'del')
{
	$id = intval(\App\Support\SupportContext::getQuery('id') ?? 0);
	if (!$id)
	{
		\App\Support\LegacyResponse::abort($lang_catmanage['std_error'], $lang_catmanage['std_invalid_id']);
	}
	$dbtablename=return_category_db_table_name($type);
	$row = \Nexus\Database\NexusDB::table($dbtablename)->where('id', $id)->first();
	if ($row) {
		$row = (array) $row;
		\Nexus\Database\NexusDB::table($dbtablename)->where('id', $row['id'])->delete();
		if(in_array($type, $validsubcattype))
			\App\Support\SupportContext::getCache()->delete_value($dbtablename.'_list');
		elseif ($type=='searchbox')
			\App\Support\SupportContext::getCache()->delete_value('searchbox_content');
		elseif ($type=='caticon')
			\App\Support\SupportContext::getCache()->delete_value('category_icon_content');
		elseif ($type=='secondicon')
			\App\Support\SupportContext::getCache()->delete_value('secondicon_'.$row['source'].'_'.$row['medium'].'_'.$row['codec'].'_'.$row['standard'].'_'.$row['processing'].'_'.$row['audiocodec'].'_content');
		elseif ($type=='category'){
			\App\Support\SupportContext::getCache()->delete_value('category_content');
			\App\Support\SupportContext::getCache()->delete_value('category_list_mode_'.$row['mode']);
		}
	}
	header("Location: ".\App\Support\Http::protocolPrefix(\App\Support\Url::isSecure()) . \App\Support\SupportContext::getGlobal('BASEURL', '')."/catmanage.php?action=view&type=".$type);
	return;
}
elseif($action == 'edit')
{
	$id = intval(\App\Support\SupportContext::getQuery('id') ?? 0);
	if (!$id)
	{
		\App\Support\LegacyResponse::abort($lang_catmanage['std_error'], $lang_catmanage['std_invalid_id']);
	}
	else
	{
		$dbtablename=return_category_db_table_name($type);
		$row = (array) \Nexus\Database\NexusDB::table($dbtablename)->where('id', $id)->first();
		if (!$row)
			\App\Support\LegacyResponse::abort($lang_catmanage['std_error'], $lang_catmanage['std_invalid_id']);
		else
		{
			$typename=return_type_name($type);
			\App\Support\Html::stdhead($typename);
			print("<form method=\"post\" action=\"?action=submit&amp;type=".$type."\">");
			print("<input type=\"hidden\" name=\"isedit\" value=\"1\" />");
			print("<input type=\"hidden\" name=\"id\" value=\"".$id."\" />");
			print_category_editor($type, $row);
			print("</form>");
			\App\Support\Html::stdfoot();
		}
	}
}
elseif($action == 'add')
{
	$typename=return_type_name($type);
	\App\Support\Html::stdhead($lang_catmanage['head_add']." - ".$typename);
	print("<form method=\"post\" action=\"?action=submit&amp;type=".$type."\">");
	print("<input type=\"hidden\" name=\"isedit\" value=\"0\" />");
	print_category_editor($type);
	print("</form>");
	\App\Support\Html::stdfoot();
}
elseif($action == 'submit')
{
    echo "This method is deprecated! This method is no longer available in 1.8, it does not save data correctly, please go to the management system!"; return;
}
