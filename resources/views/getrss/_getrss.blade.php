<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

\App\Support\Html::stdhead($lang_getrss['head_rss_feeds']);
$allowed_showrows=array('10','50');
$stickyTypes = [
    0 => \App\Support\Locale::trans('torrent.pos_state_normal', [], null),
    1 => \App\Support\Locale::trans('torrent.pos_state_sticky', [], null),
    2 => \App\Support\Locale::trans('torrent.pos_state_r_sticky', [], null)
];
?>
<h1 align="center"><?php echo $lang_getrss['text_rss_feeds']?></h1>
<form method="post" action="getrss.php">
<table cellspacing="1" cellpadding="5" width="97%">
<tr>
<td class="rowhead"><?php echo $lang_getrss['row_categories_to_retrieve']?>
</td>
<td class="rowfollow" align="left">
<?php
/*
$categories = "<table><tr><td class=\"embedded\" align=\"left\"><b>".$lang_getrss['text_category']."</b></td></tr><tr>";
$i = 0;
foreach ($brcats as $cat)//print category list of Torrents section
{
	$numinrow = $i % $catsperrow;
	$rownum = (int)($i / $catsperrow);
	if ($i && $numinrow == 0){
		$categories .= "</tr>".($brenablecatrow ? "<tr><td class=\"embedded\" align=\"left\"><b>".$brcatrow[$rownum]."</b></td></tr>" : "")."<tr>";
	}
	$categories .= "<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input name=\"cat".$cat['id']."\" type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[cat".$cat['id']."]") !== false ? " checked=\"checked\"" : "")." value='yes' />".return_category_image($cat['id'], "torrents.php?allsec=1&amp;")."</td>\n";
	$i++;
}
$categories .= "</tr>";
			if ($showsubcat)//Show subcategory (i.e. source, codecs) selections
			{
				if ($showsource){
				$categories .= "<tr><td class=\"embedded\" align=\"left\"><b>".$lang_getrss['text_source']."</b></td></tr><tr>";
				$i = 0;
				foreach ($sources as $source)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input name=\"sou".$source['id']."\" type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[sou".$source['id']."]") !== false ? " checked=\"checked\"" : "") . " value='yes' />".$source['name']."</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
				if ($showmedium){
				$categories .= "<tr><td class=\"embedded\" align=\"left\"><b>".$lang_getrss['text_medium']."</b></td></tr><tr>";
				$i = 0;
				foreach ($media as $medium)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input name=\"med".$medium['id']."\" type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[med".$medium['id']."]") !== false ? " checked=\"checked\"" : "") . " value='yes' />".$medium['name']."</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
				if ($showcodec){
				$categories .= "<tr><td class=\"embedded\" align=\"left\"><b>".$lang_getrss['text_codec']."</b></td></tr><tr>";
				$i = 0;
				foreach ($codecs as $codec)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input name=\"cod".$codec['id']."\" type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[cod".$codec['id']."]") !== false ? " checked=\"checked\"" : "") . " value='yes' />".$codec['name']."</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
				if ($showaudiocodec){
				$categories .= "<tr><td class=\"embedded\" align=\"left\"><b>".$lang_getrss['text_audio_codec']."</b></td></tr><tr>";
				$i = 0;
				foreach ($audiocodecs as $audiocodec)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input name=\"aud".$audiocodec['id']."\" type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[aud".$audiocodec['id']."]") !== false ? " checked=\"checked\"" : "") . " value='yes' />".$audiocodec['name']."</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
				if ($showstandard){
				$categories .= "<tr><td class=\"embedded\" align=\"left\"><b>".$lang_getrss['text_standard']."</b></td></tr><tr>";
				$i = 0;
				foreach ($standards as $standard)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input name=\"sta".$standard['id']."\" type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[sta".$standard['id']."]") !== false ? " checked=\"checked\"" : "") . " value='yes' />".$standard['name']."</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
				if ($showprocessing){
				$categories .= "<tr><td class=\"embedded\" align=\"left\"><b>".$lang_getrss['text_processing']."</b></td></tr><tr>";
				$i = 0;
				foreach ($processings as $processing)
				{
					$categories .= ($i && $i % $catsperrow == 0) ? "</tr><tr>" : "";
					$categories .= "<td align=\"left\" class=\"bottom\" style=\"padding-bottom: 4px;padding-left: ".$catpadding."px\"><input name=\"pro".$processing['id']."\" type=\"checkbox\" " . (strpos($CURUSER['notifs'], "[pro".$processing['id']."]") !== false ? " checked=\"checked\"" : "") . " value='yes' />".$processing['name']."</td>\n";
					$i++;
				}
				$categories .= "</tr>";
				}
			}
$categories .= "</table>";
*/

print($categories);
?>
</td>
</tr>
<tr>
<td class="rowhead"><?php echo $lang_getrss['row_show_bookmarked']?>
</td>
<td class="rowfollow" align="left">
<input type="radio" name="inclbookmarked" id="inclbookmarked0" value="0" checked="checked" /><label for="inclbookmarked0"><?php echo $lang_getrss['text_all']?></label>&nbsp;<input type="radio" name="inclbookmarked" id="inclbookmarked1" value="1" /><label for="inclbookmarked1"><?php echo $lang_getrss['text_only_bookmarked']?></label><div><?php echo $lang_getrss['text_show_bookmarked_note']?></div>
</td>
</tr>
    <tr>
        <td class="rowhead"><?php echo $lang_getrss['row_sticky']?>
        </td>
        <td class="rowfollow" align="left">
            <?php
                foreach ($stickyTypes as $key => $value) {
                    echo sprintf('<label><input type="checkbox" name="sticky[]" value="%s">%s</label>', $key, $value);
                }
            ?>
        </td>
    </tr>
<tr>
    <?php if(\App\Support\Config\SiteConfig::current()->torrent->paidTorrentEnabled()){?>
<tr>
    <td class="rowhead"><?php echo $lang_getrss['row_paid']?>
    </td>
    <td class="rowfollow" align="left">
        <label><input type="radio" name="paid" value="0" checked><?php echo $lang_getrss['paid_no']?></label>
        <label><input type="radio" name="paid" value="1"><?php echo $lang_getrss['paid_yes']?></label>
        <label><input type="radio" name="paid" value="2"><?php echo $lang_getrss['paid_all']?></label>
        <div><?php echo $lang_getrss['row_paid_help'] ?></div>
    </td>
</tr>
    <?php }?>
<td class="rowhead"><?php echo $lang_getrss['row_item_title_type']?>
</td>
<td class="rowfollow" align="left">
<input type="checkbox" name="itemcategory" value="1" /><?php echo $lang_getrss['text_item_category']?>&nbsp;<input type="checkbox" name="itemtitle" checked="checked" disabled="disabled" /><?php echo $lang_getrss['text_item_title']?>&nbsp;<input type="checkbox" name="itemsize" value="1" /><?php echo $lang_getrss['text_item_size']?>&nbsp;<input type="checkbox" name="itemuploader" value="1" /><?php echo $lang_getrss['text_item_uploader']?>
</td>
</tr>
<tr><td class="rowhead"><?php echo $lang_getrss['row_rows_per_page']?></td><td class="rowfollow" align="left"><select name="showrows">
<?php
    foreach ($allowed_showrows as $showrow) {
        echo sprintf('<option value="%s">%s</option>', $showrow, $showrow);
    }
?>
</select></td></tr>
<tr>
<td colspan="2" align="center">
<input type="submit" value="<?php echo $lang_getrss['submit_generatte_rss_link']?>" />
</td>
</tr>
</table>
</form>
<?php
\App\Support\Html::stdfoot();
