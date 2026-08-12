<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

//require_once(get_langfile_path("",true));
\App\Auth\Permission::assertCan(\App\Enums\Permission\PermissionEnum::TORRENT_DELETE);


$id = \App\Support\SupportContext::getRequestInput('id');
if ($id === null)
	\App\Support\LegacyResponse::abort($lang_delete['std_delete_failed'], $lang_delete['std_missing_form_date']);

$id = intval($id ?? 0);
if (!$id)
	return;
$torrent = \App\Models\Torrent::query()->select('name', 'owner', 'seeders', 'anonymous')->where('id', $id)->first();
if (!$torrent)
	return;
$row = $torrent->toArray();

if ($CURUSER["id"] != $row["owner"] && !\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::TORRENT_MANAGE))
	\App\Support\LegacyResponse::abort($lang_delete['std_delete_failed'], $lang_delete['std_not_owner']);

$rt = intval(\App\Support\SupportContext::getPost("reasontype") ?? 0);

if (!is_int($rt) || $rt < 1 || $rt > 5)
	\App\Support\LegacyResponse::abort($lang_delete['std_delete_failed'], $lang_delete['std_invalid_reason']."$rt.");

$reason = \App\Support\SupportContext::getPost("reason") ?? [];

if ($rt == 1)
	$reasonstr = "Dead: 0 seeders, 0 leechers = 0 peers total";
elseif ($rt == 2)
	$reasonstr = "Dupe" . (!empty($reason[0]) ? (": " . trim($reason[0])) : "!");
elseif ($rt == 3)
	$reasonstr = "Nuked" . (!empty($reason[1]) ? (": " . trim($reason[1])) : "!");
elseif ($rt == 4)
{
	if (empty($reason[2]))
		\App\Support\LegacyResponse::abort($lang_delete['std_delete_failed'], $lang_delete['std_describe_violated_rule']);
  $reasonstr = $SITENAME." rules broken: " . trim($reason[2]);
}
else
{
	if (empty($reason[3]))
		\App\Support\LegacyResponse::abort($lang_delete['std_delete_failed'], $lang_delete['std_enter_reason']);
  $reasonstr = trim($reason[3]);
}
$searchRep = new \App\Repositories\SearchRepository();
$deleteEsResult = $searchRep->deleteTorrent($id);
if ($deleteEsResult === false) {
    \App\Support\LegacyResponse::abort($lang_delete['std_delete_failed'], 'Delete es fail.');
}
deletetorrent($id);

if ($row['anonymous'] == 'yes' && $CURUSER["id"] == $row["owner"]) {
	\App\Support\Log::writeWithContext("Torrent $id ({$row['name']}) was deleted by its anonymous uploader ($reasonstr)",'normal');
} else {
	\App\Support\Log::writeWithContext("Torrent $id ({$row['name']}) was deleted by {$CURUSER['username']} ($reasonstr)",'normal');
}

//===remove karma
KPS("-",$uploadtorrent_bonus,$row["owner"]);

//Send pm to torrent uploader
if ($CURUSER["id"] != $row["owner"] && \App\Models\User::exists($row["owner"])){
	$dt = date("Y-m-d H:i:s");
    $locale = get_user_locale($row["owner"]);
    $subject = nexus_trans("torrent.msg_torrent_deleted", [], $locale);
    $msg = nexus_trans("torrent.msg_the_torrent_you_uploaded", [], $locale).$row['name'].nexus_trans("torrent.msg_was_deleted_by", [], $locale)."[url=userdetails.php?id=".$CURUSER['id']."]".$CURUSER['username']."[/url]".nexus_trans("torrent.msg_reason_is", [], $locale).$reasonstr;
    \App\Models\Message::add([
        'sender' => 0,
        'receiver' => $row['owner'],
        'subject' => $subject,
        'msg' => $msg,
        'added' => $dt,
    ]);
}
\App\Support\Html::stdhead($lang_delete['head_torrent_deleted']);

if (((\App\Support\SupportContext::getPost("returnto") !== null)))
	$ret = "<a href=\"" . htmlspecialchars(\App\Support\SupportContext::getPost("returnto")) . "\">".$lang_delete['text_go_back']."</a>";
else
	$ret = "<a href=\"index.php\">".$lang_delete['text_back_to_index']."</a>";

?>
<h1><?php echo $lang_delete['text_torrent_deleted'] ?></h1>
<p><?php echo  $ret ?></p>
<?php
\App\Support\Html::stdfoot();
