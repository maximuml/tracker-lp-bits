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


$torrentOld = \App\Models\Torrent::query()->find($id);
if (!$torrentOld)
	die();
$row = $torrentOld->toArray();
$torrentAddedTimeString = $row['added'];
if ($CURUSER["id"] != $row["owner"] && !user_can('torrentmanage'))
	bark($lang_takeedit['std_not_owner']);
$oldcatmode = (int) \App\Models\Category::query()->where('id', $row['category'])->value('mode');
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
$newcatmode = (int) \App\Models\Category::query()->where('id', $catid)->value('mode');
if ($enablespecial == 'yes' && user_can('movetorrent'))
	$allowmove = true; //enable moving torrent to other section
else $allowmove = false;
if ($oldcatmode != $newcatmode && !$allowmove)
	bark($lang_takeedit['std_cannot_move_torrent']);

$updateset['anonymous'] = !empty($_POST["anonymous"]) ? "yes" : "no";
$updateset['name'] = $name;
//$updateset[] = "descr = " . sqlesc($descr);
$extraUpdate["descr"] = $descr;
$updateset['url'] = $url;
//$updateset[] = "ori_descr = " . sqlesc($descr);
$updateset['category'] = $catid;
$updateset['source'] = intval($_POST["source_sel"][$newcatmode] ?? 0);
$updateset['medium'] = intval($_POST["medium_sel"][$newcatmode] ?? 0);
$updateset['codec'] = intval($_POST["codec_sel"][$newcatmode] ?? 0);
$updateset['standard'] = intval($_POST["standard_sel"][$newcatmode] ?? 0);
$updateset['processing'] = intval($_POST["processing_sel"][$newcatmode] ?? 0);
$updateset['audiocodec'] = intval($_POST["audiocodec_sel"][$newcatmode] ?? 0);
if (user_can('torrentmanage')) {
    $updateset['visible'] = (isset($_POST["visible"]) && $_POST["visible"] ? "yes" : "no");
}
if(user_can('torrentonpromotion'))
{
    $spState = 1;
    if (isset($_POST["sel_spstate"])) {
        $selSpState = intval($_POST["sel_spstate"]);
        if (in_array($selSpState, [2, 3, 4, 5, 6, 7])) {
            $spState = $selSpState;
        }
    }
    $updateset['sp_state'] = $spState;

	//promotion expiration type
    $promotionTimeType = 0;
    $promotionUntil = null;
	if(!isset($_POST["promotion_time_type"]) || $_POST["promotion_time_type"] == 0) {
        $promotionTimeType = 0;
        $promotionUntil = null;
	} elseif ($_POST["promotion_time_type"] == 1) {
        $promotionTimeType = 1;
        $promotionUntil = null;
	} elseif ($_POST["promotion_time_type"] == 2) {
		if (!empty($_POST["promotionuntil"]) && strtotime($torrentAddedTimeString) <= strtotime($_POST["promotionuntil"])) {
            $promotionTimeType = 2;
            $promotionUntil = $_POST["promotionuntil"];
		} else {
            $promotionTimeType = 0;
            $promotionUntil = null;
		}
	}
    $updateset['promotion_time_type'] = $promotionTimeType;
    $updateset['promotion_until'] = $promotionUntil;
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
        $updateset['pos_state'] = $posState;
        $updateset['pos_state_until'] = $posStateUntil;
    }

}

/**
 * get cover
 * @since 1.7.8
 */
$descriptionArr = format_description($descr);
$cover = get_image_from_description($descriptionArr, true, false);
$updateset['cover'] = $cover;

/**
 * hr
 * @since 1.6.0-beta12
 */
if (isset($_POST['hr'][$newcatmode]) && isset(\App\Models\Torrent::$hrStatus[$_POST['hr'][$newcatmode]]) && user_can('torrent_hr')) {
    $updateset['hr'] = $_POST['hr'][$newcatmode];
}
/**
 * price
 * @since 1.8.0
 */
if (user_can('torrent-set-price') && $paidTorrentEnabled) {
    $updateset['price'] = $_POST['price'] ?? 0;
}

$affectedRows = \App\Models\Torrent::query()->where('id', $id)->update($updateset);
do_log("[UPDATE_TORRENT]: " . nexus_json_encode($updateset));
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
