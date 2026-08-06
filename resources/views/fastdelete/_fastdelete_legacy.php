<?php
extract($context, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
function bark($msg) {
  global $lang_fastdelete;
  stdhead();
  stdmsg($lang_fastdelete['std_delete_failed'], $msg);
  stdfoot();
  return;
}

$id = \App\Support\SupportContext::getRequestInput('id');
if ($id === null) {
    bark($lang_fastdelete['std_missing_form_data']);
    return;
}

$id = intval($id ?? 0);
int_check($id);
$sure = $_GET["sure"];

$torrent = \App\Models\Torrent::query()->where('id', $id)->first(['name', 'owner', 'seeders', 'anonymous']);
if (!$torrent)
    return;
$row = $torrent->toArray();

if (!user_can('torrentmanage') || !user_can('torrent-delete')) {
    bark($lang_fastdelete['text_no_permission']);
    return;
}

if (!$sure)
{
	stderr($lang_fastdelete['std_delete_torrent'], $lang_fastdelete['std_delete_torrent_note']."<a class=altlink href=fastdelete.php?id=$id&sure=1>".$lang_fastdelete['std_here_if_sure'],false);
	return;
}

$searchRep = new \App\Repositories\SearchRepository();
$deleteEsResult = $searchRep->deleteTorrent($id);
if ($deleteEsResult === false) {
    bark('Delete es fail.');
    return;
}
deletetorrent($id);
KPS("-",$uploadtorrent_bonus,$row["owner"]);
if ($row['anonymous'] == 'yes' && $CURUSER["id"] == $row["owner"]) {
	write_log("Torrent $id ($row[name]) was deleted by its anonymous uploader",'normal');
} else {
	write_log("Torrent $id ($row[name]) was deleted by $CURUSER[username]",'normal');
}
//Send pm to torrent uploader
if (\App\Models\User::query()->where("id", $row['owner'])->exists()) {
    if ($CURUSER["id"] != $row['owner']){
        $locale = get_user_locale($row["owner"]);
        $dt = date("Y-m-d H:i:s");
        $subject = nexus_trans("torrent.msg_torrent_deleted", [], $locale);
        $msg = nexus_trans("torrent.msg_the_torrent_you_uploaded", [], $locale)
            .$row['name']
            .nexus_trans("torrent.msg_was_deleted_by", ['admin' => $CURUSER['username']], $locale)
        ;
        \App\Models\Message::add([
            'sender' => 0,
            'receiver' => $row['owner'],
            'subject' => $subject,
            'msg' => $msg,
            'added' => $dt,
        ]);
    }
}
header("Location: torrents.php");
return;
