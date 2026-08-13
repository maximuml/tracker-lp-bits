<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);
header('Content-Type: application/json');

$userid = $CURUSER["id"];
$torrentid = (int) \App\Support\SupportContext::getPost("id");
$value = (int) abs(\App\Support\SupportContext::getPost('value'));
if (!in_array($value, \App\Models\Setting::getBonusRewardOptions())) {
    echo json_encode(\App\Support\Api::failWithContext("Invalid value.", \App\Support\SupportContext::allPost()));
    return;
}

if ($value > $CURUSER['seedbonus']) {
    echo json_encode(\App\Support\Api::failWithContext('You do not have such bonus!', \App\Support\SupportContext::allPost()));
    return;
}
$torrentowner = \App\Models\Torrent::query()->where('id', $torrentid)->value('owner');
if (!$torrentowner) {
    echo json_encode(\App\Support\Api::failWithContext("Invalid torrent id!", \App\Support\SupportContext::allPost()));
    return;
}
if ($torrentowner == $userid) {
    echo json_encode(\App\Support\Api::failWithContext('You are giving magic to yourself.', \App\Support\SupportContext::allPost()));
    return;
}
$t_ab = \Nexus\Database\NexusDB::table('magic')->where('torrentid', $torrentid)->where('userid', $userid)->count();
if ($t_ab != 0) {
    echo json_encode(\App\Support\Api::failWithContext("You already gave the magic value!", \App\Support\SupportContext::allPost()));
    return;
}
$todayStr = now()->startOfDay();
$todayCount = \App\Models\Reward::query()
    ->where('userid', $userid)
    ->where('created_at', ">=", $todayStr)
    ->count();
$timesLimit = \App\Models\Setting::getBonusRewardTimesLimit();
if ($timesLimit > 0 && $todayCount >= $timesLimit) {
    echo json_encode(\App\Support\Api::failWithContext("You already reach times limit!", \App\Support\SupportContext::allPost()));
    return;
}
$torrentOwnerInfo = \App\Models\User::query()->find($torrentowner, \App\Models\User::$commonFields);
if (!$torrentOwnerInfo) {
    echo json_encode(\App\Support\Api::failWithContext("Invalid torrent owner!", \App\Support\SupportContext::allPost()));
    return;
}
if ((isset($userid)) && (isset($torrentid))&& (isset($value))) {
    \Nexus\Database\NexusDB::table('magic')->insert([
        'torrentid' => $torrentid,
        'userid' => $userid,
        'value' => $value,
    ]);
    \App\Support\Bonus::updatePoints((string) "-", (float) $value, $CURUSER['id']);//selete
    \App\Models\BonusLogs::add($CURUSER['id'], $CURUSER['seedbonus'], $value, $CURUSER['seedbonus'] - $value, "", \App\Models\BonusLogs::BUSINESS_TYPE_REWARD_TORRENT);
    \App\Support\Bonus::updatePoints((string) "+", (float) $value, $torrentowner);//add to the owner
    \App\Models\BonusLogs::add($torrentOwnerInfo['id'], $torrentOwnerInfo['seedbonus'], $value, $torrentOwnerInfo['seedbonus'] + $value, "", \App\Models\BonusLogs::BUSINESS_TYPE_TORRENT_BE_REWARD);
    echo json_encode(\App\Support\Api::successWithContext("OK", \App\Support\SupportContext::allPost()));
    return;
}
