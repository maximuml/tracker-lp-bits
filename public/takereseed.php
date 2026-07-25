<?php
require_once("../include/bittorrent.php");
dbconn();
require_once(get_langfile_path());
//require(get_langfile_path("",true));
loggedinorreturn();
user_can('askreseed', true);

$reseedid = intval($_GET["reseedid"] ?? 0);
$torrent = \App\Models\Torrent::query()->find($reseedid);
$row = $torrent === null ? null : $torrent->toArray();
$seederCount = \App\Models\Peer::query()->where('torrent', $reseedid)->count();
if ($seederCount > 0)
	stderr($lang_takereseed['std_error'], $lang_takereseed['std_torrent_not_dead']);
elseif (strtotime($row['last_reseed']) > (TIMENOW - 900))
	stderr($lang_takereseed['std_error'], $lang_takereseed['std_reseed_sent_recently']);
else{
$snatchedRows = \Nexus\Database\NexusDB::table('snatched')
    ->join('users', 'snatched.userid', '=', 'users.id')
    ->join('torrents', 'snatched.torrentid', '=', 'torrents.id')
    ->where('snatched.finished', 'Yes')
    ->where('snatched.torrentid', $reseedid)
    ->select('snatched.userid', 'snatched.torrentid', 'torrents.name as torrent_name', 'users.id')
    ->get()
    ->map(fn ($r) => (array) $r)
    ->all();
foreach ($snatchedRows as $row) {
    $locale = get_user_locale($row['userid']);
$rs_subject = nexus_trans("torrent.msg_reseed_request", [], $locale);
$pn_msg = nexus_trans("torrent.msg_reseed_user", [], $locale).$CURUSER["username"].nexus_trans("torrent.msg_ask_reseed", [], $locale)."[url=" . get_protocol_prefix() . "$BASEURL/details.php?id=".$reseedid."]".$row["torrent_name"]."[/url]".nexus_trans("torrent.msg_thank_you", [], $locale);
//sql_query("INSERT INTO messages (sender, receiver, added, subject, msg) VALUES(0, $row[userid], '" . date("Y-m-d H:i:s") . "'," . sqlesc($rs_subject) . ", " . sqlesc($pn_msg) . ")") or sqlerr(__FILE__, __LINE__);
    \App\Models\Message::add([
        'sender' => 0,
        'receiver' => $row['userid'],
        'subject' => $rs_subject,
        'msg' => $pn_msg,
        'added' => now(),
    ]);
}
//sql_query("UPDATE torrents SET last_reseed = ".sqlesc(date("Y-m-d H:i:s"))." WHERE id=".sqlesc($reseedid));
\App\Models\Torrent::query()->where("id", $reseedid)->update([
    "last_reseed" => now(),
    "seeders" => $seederCount,
]);
stdhead($lang_takereseed['head_reseed_request']);
begin_main_frame();
print("<center>".$lang_takereseed['std_it_worked']."</center>");
end_main_frame();
stdfoot();
}
?>
