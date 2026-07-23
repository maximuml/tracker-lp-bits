<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
loggedinorreturn();

function bark($msg) {
	global $lang_takeedit;
	genbark($msg, $lang_takeedit['std_edit_failed']);
}

if (!mkglobal("id:name:descr:type")){
	global $lang_takeedit;
	bark($lang_takeedit['std_missing_form_data']);
}
//check max price
$maxPrice = get_setting("torrent.max_price");
$paidTorrentEnabled = get_setting("torrent.paid_torrent_enabled") == "yes";
if ($maxPrice > 0 && ($_POST['price'] ?? 0) > $maxPrice && $paidTorrentEnabled) {
    bark('price too much');
}

$id = intval($id ?? 0);
if (!$id)
	die();


$res = sql_query("SELECT id, category, owner, filename, save_as, anonymous, added, banned FROM torrents WHERE id = ".mysql_real_escape_string($id));
$row = mysql_fetch_array($res);
$torrentAddedTimeString = $row['added'];
if (!$row)
	die();
$torrentOld = \App\Models\Torrent::query()->find($id);
if ($CURUSER["id"] != $row["owner"] && !user_can('torrentmanage'))
	bark($lang_takeedit['std_not_owner']);
$oldcatmode = get_single_value("categories","mode","WHERE id=".sqlesc($row['category']));
$updateset = array();
$extraUpdate = [];

//$fname = $row["filename"];
//preg_match('/^(.+)\.torrent$/si', $fname, $matches);
//$shortfname = $matches[1];
//$dname = $row["save_as"];

$url = null;
$extraUpdate["media_info"] = $_POST['technical_info'] ?? '';
$torrentOperationLog = [];

$catid = intval($type ?? 0);
if (!is_valid_id($catid))
bark($lang_takeedit['std_missing_form_data']);
if (!$name || !$descr)
bark($lang_takeedit['std_missing_form_data']);
$newcatmode = get_single_value("categories","mode","WHERE id=".sqlesc($catid));
if ($enablespecial == 'yes' && user_can('movetorrent'))
	$allowmove = true; //enable moving torrent to other section
else $allowmove = false;
if ($oldcatmode != $newcatmode && !$allowmove)
	bark($lang_takeedit['std_cannot_move_torrent']);
$updateset[] = "anonymous = '" . (!empty($_POST["anonymous"]) ? "yes" : "no") . "'";
$updateset[] = "name = " . sqlesc($name);
//$updateset[] = "descr = " . sqlesc($descr);
$extraUpdate["descr"] = $descr;
$updateset[] = "url = " . sqlesc($url);
$updateset[] = "small_descr = " . sqlesc($_POST["small_descr"]);
//$updateset[] = "ori_descr = " . sqlesc($descr);
$updateset[] = "category = " . sqlesc($catid);
$updateset[] = "source = " . sqlesc(intval($_POST["source_sel"][$newcatmode] ?? 0));
$updateset[] = "medium = " . sqlesc(intval($_POST["medium_sel"][$newcatmode] ?? 0));
$updateset[] = "codec = " . sqlesc(intval($_POST["codec_sel"][$newcatmode] ?? 0));
$updateset[] = "standard = " . sqlesc(intval($_POST["standard_sel"][$newcatmode] ?? 0));
$updateset[] = "processing = " . sqlesc(intval($_POST["processing_sel"][$newcatmode] ?? 0));
$updateset[] = "team = " . sqlesc(intval($_POST["team_sel"][$newcatmode] ?? 0));
$updateset[] = "audiocodec = " . sqlesc(intval($_POST["audiocodec_sel"][$newcatmode] ?? 0));
if (user_can('torrentmanage')) {
    $updateset[] = "visible = '" . (isset($_POST["visible"]) && $_POST["visible"] ? "yes" : "no") . "'";
}
if(user_can('torrentonpromotion'))
{
	if(!isset($_POST["sel_spstate"]) || $_POST["sel_spstate"] == 1)
		$updateset[] = "sp_state = 1";
	elseif(intval($_POST["sel_spstate"] ?? 0) == 2)
		$updateset[] = "sp_state = 2";
	elseif(intval($_POST["sel_spstate"] ?? 0) == 3)
		$updateset[] = "sp_state = 3";
	elseif(intval($_POST["sel_spstate"] ?? 0) == 4)
		$updateset[] = "sp_state = 4";
	elseif(intval($_POST["sel_spstate"] ?? 0) == 5)
		$updateset[] = "sp_state = 5";
	elseif(intval($_POST["sel_spstate"] ?? 0) == 6)
		$updateset[] = "sp_state = 6";
	elseif(intval($_POST["sel_spstate"] ?? 0) == 7)
		$updateset[] = "sp_state = 7";

	//promotion expiration type
	if(!isset($_POST["promotion_time_type"]) || $_POST["promotion_time_type"] == 0) {
		$updateset[] = "promotion_time_type = 0";
		$updateset[] = "promotion_until = null";
	} elseif ($_POST["promotion_time_type"] == 1) {
		$updateset[] = "promotion_time_type = 1";
		$updateset[] = "promotion_until = null";
	} elseif ($_POST["promotion_time_type"] == 2) {
		if ($_POST["promotionuntil"] && strtotime($torrentAddedTimeString) <= strtotime($_POST["promotionuntil"])) {
			$updateset[] = "promotion_time_type = 2";
			$updateset[] = "promotion_until = ".sqlesc($_POST["promotionuntil"]);
		} else {
			$updateset[] = "promotion_time_type = 0";
			$updateset[] = "promotion_until = null";
		}
	}
}
if(user_can('torrentsticky'))
{
    if (isset($_POST['pos_state']) && isset(\App\Models\Torrent::$posStates[$_POST['pos_state']])) {
        $posStateUntil = $_POST['pos_state_until'] ?: null;
        $posState = $_POST['pos_state'];
        if ($posState == \App\Models\Torrent::POS_STATE_STICKY_NONE) {
            $posStateUntil = null;
        }
        if ($posStateUntil && \Carbon\Carbon::parse($posStateUntil)->lte(now())) {
            $posState = \App\Models\Torrent::POS_STATE_STICKY_NONE;
            $posStateUntil = null;
        }
        $updateset[] = sprintf("pos_state = %s", sqlesc($posState));
        $updateset[] = sprintf("pos_state_until = %s", sqlesc($posStateUntil));
    }

}

/**
 * get cover
 * @since 1.7.8
 */
$descriptionArr = format_description($descr);
$cover = get_image_from_description($descriptionArr, true, false);
$updateset[] = "cover = " . sqlesc($cover);

/**
 * hr
 * @since 1.6.0-beta12
 */
if (isset($_POST['hr'][$newcatmode]) && isset(\App\Models\Torrent::$hrStatus[$_POST['hr'][$newcatmode]]) && user_can('torrent_hr')) {
    $updateset[] = "hr = " . sqlesc($_POST['hr'][$newcatmode]);
}
/**
 * price
 * @since 1.8.0
 */
if (user_can('torrent-set-price') && $paidTorrentEnabled) {
    $updateset[] = "price = " . sqlesc($_POST['price'] ?? 0);
}

$sql = "UPDATE torrents SET " . join(",", $updateset) . " WHERE id = $id";
do_log("[UPDATE_TORRENT]: $sql");
$affectedRows = sql_query($sql) or sqlerr(__FILE__, __LINE__);
$torrentInfo = \App\Models\Torrent::query()->find($id);
$torrentInfo->extra()->updateOrCreate(['torrent_id' => $id], $extraUpdate);
fire_event("torrent_updated", $torrentInfo, $torrentOld);
$dateTimeStringNow = date("Y-m-d H:i:s");

/**
 * add custom fields
 * @since v1.6
 */
if (!empty($_POST['custom_fields'][$newcatmode])) {
    $customField = new \Nexus\Field\Field();
    $customField->saveFieldValues($newcatmode, $id, $_POST['custom_fields'][$newcatmode]);
}

/**
 * handle tags
 *
 * @since v1.6
 */
$tagIdArr = array_filter($_POST['tags'][$newcatmode] ?? []);
insert_torrent_tags($id, $tagIdArr, true);

if($CURUSER["id"] == $row["owner"])
{
	if ($row["anonymous"]=='yes')
	{
		write_log("Torrent $id ($name) was edited by Anonymous");
	}
	else
	{
		write_log("Torrent $id ($name) was edited by {$CURUSER['username']}");
	}
}
else
{
	write_log("Torrent $id ($name) was edited by {$CURUSER['username']}, Mod Edit");
}

$searchRep = new \App\Repositories\SearchRepository();
$searchRep->updateTorrent($id);

if ($affectedRows == 1) {
    $torrentUrl = sprintf('details.php?id=%s', $row['id']);
    if ($row['banned'] == 'yes' && $row['owner'] == $CURUSER['id']) {
        \App\Models\StaffMessage::query()->insert([
            'sender' => $CURUSER['id'],
            'subject' => nexus_trans('torrent.owner_update_torrent_subject', ['detail_url' => $torrentUrl, 'torrent_name' => $_POST['name']]),
            'msg' => nexus_trans('torrent.owner_update_torrent_msg', ['detail_url' => $torrentUrl, 'torrent_name' => $_POST['name']]),
            'added' => now(),
            'permission' => 'torrent-approval',
        ]);
        clear_staff_message_cache();
    }
    if ($row['owner'] != $CURUSER['id']) {
        \App\Models\TorrentOperationLog::add([
            'torrent_id' => $row['id'],
            'uid' => $CURUSER['id'],
            'action_type' => \App\Models\TorrentOperationLog::ACTION_TYPE_EDIT,
            'comment' => '',
        ], true);
    }
    $meiliSearch = new \App\Repositories\MeiliSearchRepository();
    $meiliSearch->doImportFromDatabase($row['id']);
}

$returl = "details.php?id=$id&edited=1";
if (isset($_POST["returnto"]))
	$returl = $_POST["returnto"];
header("Location: $returl");
