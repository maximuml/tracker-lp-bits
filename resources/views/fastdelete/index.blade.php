@php
$lang_fastdelete = (array) (\App\Support\SupportContext::getGlobal('lang_fastdelete') ?? []);

$id = \App\Support\SupportContext::getRequestInput('id');
if ($id === null) {
    \App\Support\LegacyResponse::abort($lang_fastdelete['std_delete_failed'], $lang_fastdelete['std_missing_form_data']);
    return;
}

$id = intval($id ?? 0);
\App\Support\LegacyResponse::assertId($id);
$sure = \App\Support\SupportContext::getQuery("sure");

$torrent = \App\Models\Torrent::query()->where('id', $id)->first(['name', 'owner', 'seeders', 'anonymous']);
if (!$torrent)
    return;
$row = $torrent->toArray();

if (!\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::TORRENT_MANAGE) || !\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::TORRENT_DELETE)) {
    \App\Support\LegacyResponse::abort($lang_fastdelete['std_delete_failed'], $lang_fastdelete['text_no_permission']);
    return;
}

if (!$sure)
{
    \App\Support\LegacyResponse::abort($lang_fastdelete['std_delete_torrent'], $lang_fastdelete['std_delete_torrent_note']."<a class=altlink href=fastdelete.php?id=$id&sure=1>".$lang_fastdelete['std_here_if_sure'], false);
    return;
}

$searchRep = new \App\Repositories\SearchRepository();
$deleteEsResult = $searchRep->deleteTorrent($id);
if ($deleteEsResult === false) {
    \App\Support\LegacyResponse::abort($lang_fastdelete['std_delete_failed'], 'Delete es fail.');
    return;
}
\App\Support\TorrentOps::deleteTorrents($id, (bool) false);
\App\Support\Bonus::updatePoints((string) "-", (float) $uploadtorrent_bonus, $row["owner"]);
if ($row['anonymous'] == 'yes' && $CURUSER["id"] == $row["owner"]) {
    \App\Support\Log::writeWithContext("Torrent $id ($row[name]) was deleted by its anonymous uploader", 'normal');
} else {
    \App\Support\Log::writeWithContext("Torrent $id ($row[name]) was deleted by $CURUSER[username]", 'normal');
}
//Send pm to torrent uploader
if (\App\Models\User::query()->where("id", $row['owner'])->exists()) {
    if ($CURUSER["id"] != $row['owner']){
        $locale = \App\Support\Locale::userLocale($row["owner"]);
        $dt = date("Y-m-d H:i:s");
        $subject = \App\Support\Locale::trans("torrent.msg_torrent_deleted", [], $locale);
        $msg = \App\Support\Locale::trans("torrent.msg_the_torrent_you_uploaded", [], $locale)
            .$row['name']
            .\App\Support\Locale::trans("torrent.msg_was_deleted_by", ['admin' => $CURUSER['username']], $locale)
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
@endphp
