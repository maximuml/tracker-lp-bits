<?php

$medalType = 'valid_medals';
$userInfo = $userModel ?? null;
if (! $userInfo instanceof \App\Models\User) {
    \App\Support\LegacyResponse::abort('Error', 'No user with this ID!');
}
$userRep = new \App\Repositories\UserRepository();

$torrentcomments = (int) ($torrentcomments ?? 0);
$forumposts = (int) ($forumposts ?? 0);
$temporaryInviteCount = (int) ($temporaryInviteCount ?? 0);
$modcomment = (string) ($modcomment ?? '');
$bonuscomment = (string) ($bonuscomment ?? '');

if ($user['added'] == "0000-00-00 00:00:00" || $user['added'] == null) {
    $joindate = $lang_userdetails['text_not_available'];
} else {
    $weeks = abs(number_format($userInfo->added->diffInWeeks(), 1)) . \App\Support\Locale::trans('nexus.time_units.week', [], null);
    $joindate = $user['added']." (" . \App\Support\Time::format($user["added"], true, false, true).", $weeks)";
}
$lastseen = $user["last_access"];
if ($lastseen == "0000-00-00 00:00:00" || $lastseen == null)
$lastseen = $lang_userdetails['text_not_available'];
else
{
	$lastseen .= " (" . \App\Support\Time::format($lastseen, true, false, true).")";
}
//$res = sql_query("SELECT COUNT(*) FROM comments WHERE user=" . $user['id']) or sqlerr();
//$arr3 = mysql_fetch_row($res);
//$torrentcomments = $arr3[0];
//$res = sql_query("SELECT COUNT(*) FROM posts WHERE userid=" . $user['id']) or sqlerr();
//$arr3 = mysql_fetch_row($res);
//$forumposts = $arr3[0];

	$arr = \App\Support\Country::rowWithContext($user['country']);
	$country = "<img src=\"pic/flag/".$arr['flagpic']."\" alt=\"".$arr['name']."\" style='margin-left: 8pt' />";


if ($user["gender"] == "Male")
$gender = "<img class='male' src='pic/trans.gif' alt='Male' title='".$lang_userdetails['title_male']."' style='margin-left: 4pt' />";
elseif ($user["gender"] == "Female")
$gender = "<img class='female' src='pic/trans.gif' alt='Female' title='".$lang_userdetails['title_female']."' style='margin-left: 4pt' />";
elseif ($user["gender"] == "N/A")
$gender = "<img class='no_gender' src='pic/trans.gif' alt='N/A' title='".$lang_userdetails['title_not_available']."' style='margin-left: 4pt' />";

$enabled = $user["enabled"] == 'yes';
$moviepicker = $user["picker"] == 'yes';

print("<h1 style='margin:0px'>" . \App\Support\UserDisplay::username($user['id'], true,false) . $country."</h1>");
if ($userInfo->valid_medals->isNotEmpty()) {
    print \App\Support\Medal::buildImages($userInfo->{$medalType}, 120, $CURUSER['id'] == $user['id']);
    $warnMedalJs = <<<JS
jQuery('#save-user-medal-btn').on("click", function (e) {
    let form = jQuery(this).closest('form');
    let data = form.serializeArray();
    console.log(data)
    jQuery.post('ajax.php', {params: data, action: 'saveUserMedal'}, function (response) {
        console.log(response)
        if (response.ret != 0) {
            layer.alert(response.msg)
        } else {
            window.location.reload()
        }
    }, 'json')
})
JS;
    \Nexus\Nexus::js($warnMedalJs, 'footer', false);
}

if (!$enabled)
print("<p><b>".$lang_userdetails['text_account_disabled_note']."</b></p>");
elseif ($CURUSER["id"] <> $user["id"])
{
	$friend = \App\Repositories\UserDetailRepository::isFriend((int)$CURUSER['id'], $id) ? 1 : 0;
	$block = \App\Repositories\UserDetailRepository::isBlocked((int)$CURUSER['id'], $id) ? 1 : 0;

	if ($friend)
	print("<p>(<a href=\"friends.php?action=delete&amp;type=friend&amp;targetid=".$id."\">".$lang_userdetails['text_remove_from_friends']."</a>)</p>\n");
	elseif($block)
	print("<p>(<a href=\"friends.php?action=delete&amp;type=block&amp;targetid=".$id."\">".$lang_userdetails['text_remove_from_blocks']."</a>)</p>\n");
	else
	{
		print("<p>(<a href=\"friends.php?action=add&amp;type=friend&amp;targetid=".$id."\">".$lang_userdetails['text_add_to_friends']."</a>)");
		print(" - (<a href=\"friends.php?action=add&amp;type=block&amp;targetid=".$id."\">".$lang_userdetails['text_add_to_blocks']."</a>)</p>");
	}
}
if ($CURUSER['id'] == $user['id'] || \App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::MANAGE_USER_CONFIDENTIAL_INFO))
	print("<h2>".$lang_userdetails['text_flush_ghost_torrents']."<a class=\"altlink\" href=\"takeflush.php?id=".$id."\">".$lang_userdetails['text_here']."</a></h2>\n");
?>
<table width="100%" border="1" cellspacing="0" cellpadding="5">
<?php
$userIdDisplay = $user['id'];
$userManageSystemUrl = sprintf('%s/%s/user/users/%s',\App\Support\Url::schemeAndHost(false), \App\Support\Env::get('FILAMENT_PATH', 'nexusphp'), $user['id']);
$userManageSystemText = sprintf('<a href="%s" target="_blank" class="altlink">%s</a>', $userManageSystemUrl, $lang_functions['text_management_system']);
$migratedHelp = "&nbsp;&nbsp;".sprintf($lang_userdetails['change_field_value_migrated'], $userManageSystemText);
if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::MANAGE_USER_BASIC_INFO) && $user["class"] < \App\Support\UserDisplay::currentClass()) {
    $userIdDisplay .= "&nbsp;[$userManageSystemText]";
}
if (($user["privacy"] != "strong") OR (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::MANAGE_USER_BASIC_INFO)) || $CURUSER['id'] == $user['id']){
    \App\Support\Html::trSmall($lang_userdetails['text_user_id'], $userIdDisplay, 1);
    $tmpInviteCount = $temporaryInviteCount;
	if ($CURUSER['id'] == $user['id'] || \App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::VIEW_INVITE)){
	if ($user["invites"] <= 0 && $tmpInviteCount <= 0)
	\App\Support\Html::trSmall($lang_userdetails['row_invitation'], $lang_userdetails['text_no_invitation'], 1);
	else
	\App\Support\Html::trSmall($lang_userdetails['row_invitation'], "<a href=\"invite.php?id=".$user['id']."\" title=\"".$lang_userdetails['link_send_invitation']."\">".sprintf('%s(%s)', $user['invites'], $tmpInviteCount)."</a>", 1);}
	else{
	if ($CURUSER['id'] != $user['id'] || \App\Support\UserDisplay::currentClass() != $viewinvite_class){
	if ($user["invites"] <= 0)
	\App\Support\Html::trSmall($lang_userdetails['row_invitation'], $lang_userdetails['text_no_invitation'], 1);
	else
	\App\Support\Html::tr($lang_userdetails['row_invitation'], $user['invites'], 1);}
	}
	if ($user["invited_by"] > 0) {
		\App\Support\Html::trSmall($lang_userdetails['row_invited_by'], \App\Support\UserDisplay::username($user['invited_by']), 1);
	}
	\App\Support\Html::trSmall($lang_userdetails['row_join_date'], $joindate, 1);
	\App\Support\Html::trSmall($lang_userdetails['row_last_seen'], $lastseen, 1);
if ($where_tweak == "yes") {
	\App\Support\Html::trSmall($lang_userdetails['row_last_seen_location'], $user['page'], 1);
}
if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO) OR $user["privacy"] == "low" ||  $user["id"] == $CURUSER["id"]) {
	\App\Support\Html::trSmall($lang_userdetails['row_email'], "<a href=\"mailto:".$user['email']."\">".$user['email']."</a>", 1);
}
if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO)) {
	$iphistory = \App\Repositories\UserDetailRepository::getIplogCount($id);

	if ($iphistory > 0)
	\App\Support\Html::trSmall($lang_userdetails['row_ip_history'], $lang_userdetails['text_user_earlier_used']."<b><a href=\"iphistory.php?id=" . $user['id'] . "\">" . $iphistory. $lang_userdetails['text_different_ips'].\App\Support\Strings::addS($iphistory, true)."</a></b>", 1);
}
$seedBoxRep = new \App\Repositories\SeedBoxRepository();
if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO) ||  $user["id"] == $CURUSER["id"])
{
    $seedBoxIcon = $seedBoxRep->renderIcon($CURUSER['ip'], $CURUSER['id']);
	if ($enablelocation_tweak == 'yes'){
		list($loc_pub, $loc_mod) = \App\Support\Network::ipLocationWithContext($user['ip']);
		$locationinfo = "<span title=\"" . $loc_mod . "\">[" . $loc_pub . "]</span>";
	}
	else $locationinfo = "";
//    $ip = $user["id"] == $CURUSER["id"] ? hide_text($user['ip']) : $user['ip'];
    $ip = $user["ip"];
	\App\Support\Html::trSmall($lang_userdetails['row_ip_address'], \App\Support\Strings::hidden($ip.$locationinfo.$seedBoxIcon), 1);
}
$clientselect = '';
$peerRows = \App\Repositories\UserDetailRepository::getPeers((int)$user['id']);
if (!empty($peerRows))
{
    $clientselect .= "<table border='1' cellspacing='0' cellpadding='5'><tr><td class='colhead'>Agent</td><td class='colhead'>IPV4</td><td class='colhead'>IPV6</td><td class='colhead'>Port</td></tr>";
	foreach ($peerRows as $arr)
	{
	    $clientselect .= "<tr>";
		$clientselect .= sprintf('<td>%s</td>', \App\Support\Strings::userAgentClient( $arr['agent']));
		if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO) ||  $user["id"] == $CURUSER["id"]) {
            $v4 = $user["id"] == $CURUSER["id"] ? \App\Support\Strings::hidden($arr['ipv4']) : $arr['ipv4'];
            $v6 = $user["id"] == $CURUSER["id"] ? \App\Support\Strings::hidden($arr['ipv6']) : $arr['ipv6'];
            $clientselect .= sprintf('<td>%s</td><td>%s</td><td>%s</td>', $v4.$seedBoxRep->renderIcon($arr['ipv4'], $user['id']), $v6.$seedBoxRep->renderIcon($arr['ipv6'], $user['id']), $arr['port']);
        } else {
            $clientselect .= sprintf('<td>%s</td><td>%s</td><td>%s</td>', '---', '---', '---');
        }
        $clientselect .= "</tr>";
	}
	$clientselect .= '</table>';
}
if ($clientselect)
	\App\Support\Html::trSmall($lang_userdetails['row_bt_client'], $clientselect, 1);


//真实分享、上传、下载率显示
$trueTraffic = \App\Repositories\UserDetailRepository::getTrueTraffic((int)$user['id']);
$true_download = $trueTraffic['downloaded'];
$true_upload = $trueTraffic['uploaded'];
if ($user["downloaded"] > 0 && $true_download > 0)
{
	$sr = floor($user["uploaded"] / $user["downloaded"] * 1000) / 1000;
	$true_ratio = floor($true_upload / $true_download * 1000) / 1000;
	$sr = "<tr><td class=\"embedded\"><strong>" . $lang_userdetails['row_share_ratio'] . "</strong>:  <font color=\"" . \App\Support\Ratio::color($sr) . "\">" . number_format($sr, 3) . "</font>（<strong>".$lang_userdetails['row_real_share_ratio']."</strong>：".number_format($true_ratio, 3)."）</td><td class=\"embedded\">&nbsp;&nbsp;" . \App\Support\Ratio::image($sr) . "</td></tr>";

}
//end

$xfer = "<tr><td class=\"embedded\"><strong>" . $lang_userdetails['row_uploaded'] . "</strong>:  ". \App\Support\Format::size($user["uploaded"]) . "</td><td class=\"embedded\">&nbsp;&nbsp;<strong>" . $lang_userdetails['row_downloaded'] . "</strong>:  " . \App\Support\Format::size($user["downloaded"]) . "</td></tr>";
$true_xfer = "<tr><td class=\"embedded\"><strong>" . $lang_userdetails['row_real_uploaded'] . "</strong>:  ". \App\Support\Format::size($true_upload) . "</td><td class=\"embedded\">&nbsp;&nbsp;<strong>" . $lang_userdetails['row_real_downloaded'] . "</strong>:  " . \App\Support\Format::size($true_download) . "</td><td class=\"embedded text-muted\">&nbsp;&nbsp;" . $lang_userdetails['row_real_ps'] . "</td></tr>";
\App\Support\Html::trSmall($lang_userdetails['row_transfer'], "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\">" . ($sr ?? '') . $xfer .  $true_xfer . "</table>", 1);


if ($user["leechtime"] > 0)
{
	$slr = floor($user["seedtime"] / $user["leechtime"] * 1000) / 1000;
	$slr = "<tr><td class=\"embedded\"><strong>" . $lang_userdetails['text_seeding_leeching_time_ratio'] . "</strong>:  <font color=\"" . \App\Support\Ratio::color($slr) . "\">" . number_format($slr, 3) . "</font></td><td class=\"embedded\">&nbsp;&nbsp;" . \App\Support\Ratio::image($slr) . "</td></tr>";
}

$slt = "<tr><td class=\"embedded\"><strong>" . $lang_userdetails['text_seeding_time'] . "</strong>:  ". \App\Support\Format::prettyTimeWithLocale($user["seedtime"]) . "</td><td class=\"embedded\">&nbsp;&nbsp;<strong>" . $lang_userdetails['text_leeching_time'] . "</strong>:  " . \App\Support\Format::prettyTimeWithLocale($user["leechtime"]) . "</td><td class=\"embedded text-muted\">&nbsp;&nbsp;(" . \App\Support\Locale::trans('label.updated_at', [], null) . ": " . $user['seed_time_updated_at'] . ")</td></tr>";

	\App\Support\Html::trSmall($lang_userdetails['row_sltime'], "<table border=\"0\" cellspacing=\"0\" cellpadding=\"0\">" . ($slr ?? '') . $slt . "</table>", 1);

\App\Support\Html::trSmall($lang_userdetails['row_gender'], $gender, 1);

if (($user['donated'] > 0 || $user['donated_cny'] > 0 )&& (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::VIEW_USER_CONFIDENTIAL_INFO) || $CURUSER["id"] == $user["id"]))
\App\Support\Html::trSmall($lang_userdetails['row_donated'], "$".htmlspecialchars($user['donated'])."&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;".htmlspecialchars($user['donated_cny']), 1);

if ($user["avatar"])
\App\Support\Html::trSmall($lang_userdetails['row_avatar'], \App\Support\UserDisplay::avatarImageWithContext(htmlspecialchars(trim($user["avatar"]))), 1);

$uclass = \App\Support\UserClass::imagePath($user["class"]);
$utitle = \App\Support\UserClass::name($user["class"],false,false,true);
$uclassImg = "<img alt=\"".\App\Support\UserClass::name($user["class"],false,false,true)."\" title=\"".\App\Support\UserClass::name($user["class"],false,false,true)."\" src=\"".$uclass."\" /> ".($user['title']!=="" ? "&nbsp;".htmlspecialchars(trim($user["title"]))."" :  "");
if ($user['class'] == UC_VIP && !empty($user['vip_until']) && strtotime($user['vip_until'])) {
    $uclassImg .= sprintf('%s: %s', $lang_userdetails['row_vip_until'], $user['vip_until']);
}
\App\Support\Html::trSmall($lang_userdetails['row_class'], $uclassImg, 1);

//User meta
$metas = $userRep->listMetas($user['id']);
$props = [];
$metaKey = \App\Models\UserMeta::META_KEY_CHANGE_USERNAME;
if ($metas->has($metaKey)) {
    $triggerId = "consume-$metaKey";
    $changeUsernameCards = $metas->get($metaKey);
    $cardName = $changeUsernameCards->first()->meta_key_text;
    $useInput = '';
    if ($CURUSER['id'] == $user['id']) {
        $useInput = sprintf('<input type="button" value="%s" id="%s">', $lang_userdetails['consume'], $triggerId);
    }
    $props[] = sprintf(
        '<div><strong>[%s]</strong>(%s)</div>%s',
        $cardName, $changeUsernameCards->count(), $useInput
    );
    if ($useInput) {
        $consumeChangeUsernameForm = <<<HTML
<div class="layer-form">
<form id="layer-form-$metaKey">
    <input type="hidden" name="params[meta_key]" value="$metaKey">
    <div class="form-control-row">
        <div class="label">{$lang_userdetails['meta_key_change_username_username']}</div>
        <div class="field"><input type="text" name="params[username]"></div>
    </div>
</form>
</div>
HTML;
        $consumeChangeUsernameJs = <<<JS
jQuery('#{$triggerId}').on("click", function () {
    layer.open({
        type: 1,
        title: "{$lang_userdetails['consume']} {$cardName}",
        content: `$consumeChangeUsernameForm`,
        btn: ['OK'],
        btnAlign: 'c',
        yes: function () {
            let params = jQuery('#layer-form-{$metaKey}').serialize()
            jQuery.post('ajax.php', params + "&action=consumeBenefit", function (response) {
                console.log(response)
                if (response.ret != 0) {
                    layer.alert(response.msg)
                    return
                }
                window.location.reload()
            }, 'json')
        }
    })
})
JS;
        \Nexus\Nexus::js($consumeChangeUsernameJs, 'footer', false);
    }
}

$metaKey = \App\Models\UserMeta::META_KEY_PERSONALIZED_USERNAME;
if ($metas->has($metaKey)) {
    $rainbowID = $metas->get($metaKey)->first();
    if ($rainbowID->isValid()) {
        $props[] = sprintf(
            '<div><strong>[%s]</strong>(%s)</div>',
            $rainbowID->metaKeyText, $rainbowID->getDeadlineText()
        );
    }
}

if (!empty($props)) {
    \App\Support\Html::trSmall($lang_userdetails['row_user_props'], sprintf('<div style="display: flex;align-items: center">%s</div>', implode('&nbsp;|&nbsp;', $props)), 1);
}

\App\Support\Hooks::doAction('user_detail_rows', $user['id'], 'web');

\App\Support\Html::trSmall($lang_userdetails['row_torrent_comment'], ($torrentcomments && ($user["id"] == $CURUSER["id"] || \App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::VIEW_USER_HISTORY)) ? "<a href=\"userhistory.php?action=viewcomments&amp;id=".$id."\" title=\"".$lang_userdetails['link_view_comments']."\">".$torrentcomments."</a>" : $torrentcomments), 1);

\App\Support\Html::trSmall($lang_userdetails['row_forum_posts'], ($forumposts && ($user["id"] == $CURUSER["id"] || \App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::VIEW_USER_HISTORY)) ? "<a href=\"userhistory.php?action=viewposts&amp;id=".$id."\" title=\"".$lang_userdetails['link_view_posts']."\">".$forumposts."</a>" : $forumposts), 1);

if ($user["id"] == $CURUSER["id"] || \App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::VIEW_USER_HISTORY)) {
    if (\App\Models\HitAndRun::getIsEnabled()) {
        $hrStatus = (new \App\Repositories\HitAndRunRepository())->getStatusStats($user['id']);
        \App\Support\Html::trSmall('H&R', sprintf('<a href="myhr.php?userid=%s" target="_blank">%s</a>', $user['id'], $hrStatus), 1);
    }

    $bonusLogText = sprintf('&nbsp;&nbsp;<a href="bonus-log.php?uid=%s" target="_blank" class="altlink">[%s]</a>', $user['id'], \App\Support\Locale::trans("bonus-log.view_detail", [], null));
    \App\Support\Html::trSmall($lang_userdetails['row_karma_points'], number_format($user['seedbonus'], 1) . $bonusLogText, 1);
    \App\Support\Html::trSmall($lang_functions['text_seed_points'], number_format($user['seed_points'], 1) . "&nbsp;&nbsp;<span class='text-muted'>(" . \App\Support\Locale::trans('label.updated_at', [], null) . ": " . $user['seed_points_updated_at'] . ")</span>", 1);
}

if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::MANAGE_USER_BASIC_INFO) && $user["class"] < \App\Support\UserDisplay::currentClass()) {
    $bonusTable = \App\Support\Bonus::buildBonusTableForUser($user);
    \App\Support\Html::trSmall($lang_userdetails['text_bonus_table'], $bonusTable['table'], 1);
}

if ($user["ip"] && (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::TORRENT_HISTORY) || $user["id"] == $CURUSER["id"])){

\App\Support\Html::trSmall($lang_userdetails['row_uploaded_torrents'], "<a href=\"javascript: getusertorrentlistajax('".$user['id']."', 'uploaded', 'ka'); klappe_news('a')\"><img class=\"plus\" src=\"pic/trans.gif\" id=\"pica\" alt=\"Show/Hide\" title=\"".$lang_userdetails['title_show_or_hide'] ."\" />   <u>".$lang_userdetails['text_show_or_hide']."</u></a><div id=\"ka\" style=\"display: none;\" data-type='uploaded'></div>", 1);


\App\Support\Html::trSmall($lang_userdetails['row_current_seeding'], "<a href=\"javascript: getusertorrentlistajax('".$user['id']."', 'seeding', 'ka1'); klappe_news('a1')\"><img class=\"plus\" src=\"pic/trans.gif\" id=\"pica1\" alt=\"Show/Hide\" title=\"".$lang_userdetails['title_show_or_hide']."\" />   <u>".$lang_userdetails['text_show_or_hide']."</u></a><div id=\"ka1\" style=\"display: none;\" data-type='seeding'></div>", 1);


\App\Support\Html::trSmall($lang_userdetails['row_current_leeching'], "<a href=\"javascript: getusertorrentlistajax('".$user['id']."', 'leeching', 'ka2'); klappe_news('a2')\"><img class=\"plus\" src=\"pic/trans.gif\" id=\"pica2\" alt=\"Show/Hide\" title=\"".$lang_userdetails['title_show_or_hide']."\" />   <u>".$lang_userdetails['text_show_or_hide']."</u></a><div id=\"ka2\" style=\"display: none;\" data-type='leeching'></div>", 1);


\App\Support\Html::trSmall($lang_userdetails['row_completed_torrents'], "<a href=\"javascript: getusertorrentlistajax('".$user['id']."', 'completed', 'ka3'); klappe_news('a3')\"><img class=\"plus\" src=\"pic/trans.gif\" id=\"pica3\" alt=\"Show/Hide\" title=\"".$lang_userdetails['title_show_or_hide']."\" />   <u>".$lang_userdetails['text_show_or_hide']."</u></a><div id=\"ka3\" style=\"display: none;\" data-type='completed'></div>", 1);


\App\Support\Html::trSmall($lang_userdetails['row_incomplete_torrents'], "<a href=\"javascript: getusertorrentlistajax('".$user['id']."', 'incomplete', 'ka4'); klappe_news('a4')\"><img class=\"plus\" src=\"pic/trans.gif\" id=\"pica4\" alt=\"Show/Hide\" title=\"".$lang_userdetails['title_show_or_hide']."\" />   <u>".$lang_userdetails['text_show_or_hide']."</u></a><div id=\"ka4\" style=\"display: none;\" data-type='incomplete'></div>", 1);
}
if ($user["info"])
	print("<tr><td align=\"left\" colspan=\"2\" class=\"text\">" . \App\Support\Format::formatComment($user["info"],false) . "</td></tr>\n");
}
else
{
	print("<tr><td align=\"left\" colspan=\"2\" class=\"text\"><font color=\"blue\">".$lang_userdetails['text_public_access_denied'].$user['username'].$lang_userdetails['text_user_wants_privacy']."</font></td></tr>\n");
}
$showpmbutton = 0;
if ($CURUSER["id"] != $user["id"])
if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::STAFF_MEMBER))
$showpmbutton = 1;
elseif ($user["acceptpms"] == "yes")
{
	$showpmbutton = \App\Repositories\UserDetailRepository::isBlocked((int)$user['id'], (int)$CURUSER['id']) ? 0 : 1;
}
elseif ($user["acceptpms"] == "friends")
{
	$showpmbutton = \App\Repositories\UserDetailRepository::isFriend((int)$user['id'], (int)$CURUSER['id']) ? 1 : 0;
}
if ($CURUSER["id"] != $user["id"]){
print("<tr><td colspan=\"2\" align=\"center\">");
if ($showpmbutton)
print("<a href=\"sendmessage.php?receiver=".htmlspecialchars($user['id'])."\"><img class=\"f_pm\" src=\"pic/trans.gif\" alt=\"PM\" title=\"".$lang_userdetails['title_send_pm']."\" /></a>");

print("<a href=\"report.php?user=".htmlspecialchars($user['id'])."\"><img class=\"f_report\" src=\"pic/trans.gif\" alt=\"Report\" title=\"".$lang_userdetails['title_report_user']."\" /></a>");
print("</td></tr>");
}
print("</table>\n");

if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::MANAGE_USER_BASIC_INFO) && $user["class"] < \App\Support\UserDisplay::currentClass())
{
	\App\Support\Html::beginFrame($lang_userdetails['text_edit_user'], true);
	print("<form method=\"post\" action=\"modtask.php\">");
	print("<input type=\"hidden\" name=\"action\" value=\"edituser\" />");
	print("<input type=\"hidden\" name=\"userid\" value=\"".$id."\" />");
	print("<input type=\"hidden\" name=\"returnto\" value=\"".htmlspecialchars("userdetails.php?id=$id")."\" />");
	print("<table width=\"100%\" class=\"main\" border=\"1\" cellspacing=\"0\" cellpadding=\"5\">\n");
	\App\Support\Html::tr($lang_userdetails['row_title'], "<input type=\"text\" size=\"60\" name=\"title\" value=\"" . htmlspecialchars(trim((string)$user['title'])) . "\" />", 1);
	$avatar = htmlspecialchars(trim((string)$user["avatar"]));

	\App\Support\Html::tr($lang_userdetails['row_privacy_level'], "<input type=\"radio\" name=\"privacy\" value=\"low\"".($user["privacy"] == "low" ? " checked=\"checked\"" : "")." />".$lang_userdetails['radio_low']."<input type=\"radio\" name=\"privacy\" value=\"normal\"".($user["privacy"] == "normal" ? " checked=\"checked\"" : "")." />".$lang_userdetails['radio_normal']."<input type=\"radio\" name=\"privacy\" value=\"strong\"".($user["privacy"] == "strong" ? " checked=\"checked\"" : "")." />".$lang_userdetails['radio_strong'], 1);
	\App\Support\Html::tr($lang_userdetails['row_avatar_url'], "<input type=\"text\" size=\"60\" name=\"avatar\" value=\"".$avatar."\" />", 1);
	$signature = trim((string)$user["signature"]);
	\App\Support\Html::tr($lang_userdetails['row_signature'], "<textarea cols=\"60\" rows=\"6\" name=\"signature\">".$signature."</textarea>", 1);

	if (\App\Support\UserDisplay::currentClass() == UC_STAFFLEADER)
	{
		\App\Support\Html::tr($lang_userdetails['row_donor_status'], "<input type=\"radio\" name=\"donor\" value=\"yes\"" .($user["donor"] == "yes" ? " checked=\"checked\"" : "")." />".$lang_userdetails['radio_yes']." <input type=\"radio\" name=\"donor\" value=\"no\"" .($user["donor"] == "no" ? " checked=\"checked\"" : "").">".$lang_userdetails['radio_no'], 1);
		\App\Support\Html::tr($lang_userdetails['row_donated'], "USD: <input type=\"text\" size=\"5\" name=\"donated\" value=\"" . htmlspecialchars((string)$user['donated']) . "\" />&nbsp;&nbsp;&nbsp;&nbsp;CNY: <input type=\"text\" size=\"5\" name=\"donated_cny\" value=\"" . htmlspecialchars((string)$user['donated_cny']) . "\" />" . $lang_userdetails['text_transaction_memo'] . "<input type=\"text\" size=\"50\" name=\"donation_memo\" />", 1);
        \App\Support\Html::tr($lang_userdetails['row_donoruntil'], "<input type=\"text\" name=\"donoruntil\" value=\"".htmlspecialchars((string)$user["donoruntil"])."\" /> ".$lang_userdetails['text_donoruntil_note'], 1);
	}
	if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::USER_CHANGE_CLASS)) {
        $maxclass = \App\Support\UserDisplay::currentClass() - 1;
        $classselect=\App\Support\UserClass::classSelectWithContext('class', $maxclass, $user["class"], 0, false, true);
        \App\Support\Html::tr($lang_userdetails['row_class'], $classselect . $migratedHelp, 1);
    }
	\App\Support\Html::tr($lang_userdetails['row_vip_by_bonus'], "<input type=\"radio\" name=\"vip_added\" value=\"yes\"" .($user["vip_added"] == "yes" ? " checked=\"checked\"" : "")." disabled='disabled'/>".$lang_userdetails['radio_yes']." <input type=\"radio\" name=\"vip_added\" value=\"no\"" .($user["vip_added"] == "no" ? " checked=\"checked\"" : "")." disabled='disabled'/>".$lang_userdetails['radio_no'].$migratedHelp, 1);
	\App\Support\Html::tr($lang_userdetails['row_vip_until'], "<input type=\"text\" name=\"vip_until\" value=\"".htmlspecialchars((string)$user["vip_until"])."\" disabled='disabled'/> ".$lang_userdetails['text_vip_until_note']. $migratedHelp, 1);
	$supportlang = htmlspecialchars((string)$user["supportlang"]);
	$supportfor = htmlspecialchars((string)$user["supportfor"]);
	$pickfor = htmlspecialchars((string)$user["pickfor"]);
	$staffduties = htmlspecialchars((string)$user["stafffor"]);

	\App\Support\Html::tr($lang_userdetails['row_staff_duties'], "<textarea cols=\"60\" rows=\"6\" name=\"staffduties\">".$staffduties."</textarea>", 1);
	\App\Support\Html::tr($lang_userdetails['row_support_language'], "<input type=\"text\" name=\"supportlang\" value=\"".$supportlang."\" />", 1);
	\App\Support\Html::tr($lang_userdetails['row_support'], "<input type=\"radio\" name=\"support\" value=\"yes\"" .($user["support"] == "yes" ? " checked=\"checked\"" : "")." />".$lang_userdetails['radio_yes']." <input type=\"radio\" name=\"support\" value=\"no\"" .($user["support"] == "no" ? " checked=\"checked\"" : "")." />".$lang_userdetails['radio_no'], 1);
	\App\Support\Html::tr($lang_userdetails['row_support_for'], "<textarea cols=\"60\" rows=\"6\" name=\"supportfor\">".$supportfor."</textarea>", 1);

	\App\Support\Html::tr($lang_userdetails['row_movie_picker'], "<input name=\"moviepicker\" value=\"yes\" type=\"radio\"" . ($moviepicker ? " checked=\"checked\"" : "") . " />".$lang_userdetails['radio_yes']."<input name=\"moviepicker\" value=\"no\" type=\"radio\"" . (!$moviepicker ? " checked=\"checked\"" : "") . " />".$lang_userdetails['radio_no'], 1);
	\App\Support\Html::tr($lang_userdetails['row_pick_for'], "<textarea cols=\"60\" rows=\"6\" name=\"pickfor\">".$pickfor."</textarea>", 1);

	if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::MANAGE_USER_CONFIDENTIAL_INFO))
	{
		\App\Support\Html::tr($lang_userdetails['row_comment'], "<textarea cols=\"60\" rows=\"6\" name=\"modcomment\">".$modcomment."</textarea>", 1);
		\App\Support\Html::tr($lang_userdetails['row_seeding_karma'], "<textarea cols=\"60\" rows=\"6\" name=\"bonuscomment\" readonly=\"readonly\">".$bonuscomment."</textarea>", 1);
	}
	$warned = $user["warned"] == "yes";

	print("<tr><td class=\"rowhead\">".$lang_userdetails['row_warning_system']."</td><td class=\"rowfollow\" align=\"left\" ><table class=\"main\" cellspacing=\"0\" cellpadding=\"5\"><tr><td class=\"rowfollow\">" . ($warned ? "<input name=\"warned\" value=\"yes\" type=\"radio\" checked=\"checked\" />".$lang_userdetails['radio_yes']."<input name=\"warned\" value=\"no\" type=\"radio\" />".$lang_userdetails['radio_no'] : $lang_userdetails['text_not_warned'] ) ."</td>");

	if ($warned)
	{
		$warneduntil = $user['warneduntil'];
		if ($warneduntil == '0000-00-00 00:00:00' || $warneduntil == null)
		print("<td align=\"center\" class=\"rowfollow\">".$lang_userdetails['text_arbitrary_duration']."</td>\n");
		else
		{
			print("<td align=\"left\" class=\"rowfollow\">".$lang_userdetails['text_until'].$warneduntil);
			print("<br />(" . \App\Support\Format::prettyTimeWithLocale(strtotime($warneduntil) - strtotime(date("Y-m-d H:i:s"))) .$lang_userdetails['text_to_go'] .")</td>\n");
		}
		print("</tr>");

	}else{

		print("<td align=\"left\" class=\"rowfollow\">".$lang_userdetails['text_warn_for']."<select name=\"warnlength\">\n");
		print("<option value=\"0\">------</option>\n");
		print("<option value=\"1\">1 ".$lang_userdetails['text_week']."</option>\n");
		print("<option value=\"2\">2 ".$lang_userdetails['text_weeks']."</option>\n");
		print("<option value=\"4\">4 ".$lang_userdetails['text_weeks']."</option>\n");
		print("<option value=\"8\">8 ".$lang_userdetails['text_weeks']."</option>\n");
		print("<option value=\"255\">".$lang_userdetails['text_unlimited']."</option>\n");
		print("</select></td></tr>\n");
		print("<tr><td align=\"left\" class=\"rowfollow\">".$lang_userdetails['text_reason_of_warning']."</td><td align=\"left\" class=\"rowfollow\"><input type=\"text\" size=\"60\" name=\"warnpm\" /></td></tr>");
	}


	$elapsedlw = $user["lastwarned"] ? \App\Support\Format::getElapsedTime(strtotime($user["lastwarned"])) : '';
	print("<tr><td align=\"left\" class=\"rowfollow\">".$lang_userdetails['text_times_warned']."</td><td align=\"left\" class=\"rowfollow\">".$user['timeswarned']."</td></tr>\n");

	if ($user["timeswarned"] == 0)
	{
		print("<tr><td align=\"left\" class=\"rowfollow\">".$lang_userdetails['text_last_warning']."</td><td align=\"left\" class=\"rowfollow\">".$lang_userdetails['text_not_warned_note']."</td></tr>\n");
	}else{
		if ($user["warnedby"] != "System")
		{
			$arr = \App\Repositories\UserDetailRepository::getWarnedBy((int)$user['warnedby']);
			$warnedby = $arr ? "<br />[".$lang_userdetails['text_by']."<u>" . \App\Support\UserDisplay::username($arr['id']) . "</u></a>]" : "";
		}else{
			$warnedby = "<br />[".$lang_userdetails['text_by_system']."]";
			print("<tr><td class=\"rowfollow\">".$lang_userdetails['text_last_warning']."</td><td align=\"left\" class=\"rowfollow\"> {$user['lastwarned']} .(".$lang_userdetails['text_until'] ."$elapsedlw)   $warnedby</td></tr>\n");
		}
		print("<tr><td class=\"rowfollow\">".$lang_userdetails['text_last_warning']."</td><td align=\"left\" class=\"rowfollow\"> {$user['lastwarned']} ($elapsedlw".$lang_userdetails['text_ago'].")   ".$warnedby."</td></tr>\n");
	}

	$leechwarn = $user["leechwarn"] == "yes";
	print("<tr><td class=\"rowfollow\">".$lang_userdetails['row_auto_warning']."<br /><i>(".$lang_userdetails['text_low_ratio'].")</i></td>");

	if ($leechwarn)
	{
		print("<td align=\"left\" class=\"rowfollow\"><font color=\"red\">".$lang_userdetails['text_leech_warned']."</font> ");
		$leechwarnuntil = $user['leechwarnuntil'];
		if ($leechwarnuntil != '0000-00-00 00:00:00' || $leechwarnuntil != null)
		{
			print($lang_userdetails['text_until'].$leechwarnuntil);
			print("<br />(" . \App\Support\Format::prettyTimeWithLocale(strtotime($leechwarnuntil) - strtotime(date("Y-m-d H:i:s"))) .$lang_userdetails['text_to_go'].")");
			printf('&nbsp;<input id="remove-leech-warn" type="button" class="btn" value="Remove" data-uid="%s" />', $user['id']);
			$removeLeechWarnJs = <<<JS
jQuery('#remove-leech-warn').on('click', function () {
    if (!window.confirm('{$lang_userdetails['sure_to_remove_leech_warn']}')) {
        return
    }
    let params = {action: 'removeUserLeechWarn', params: {uid: jQuery(this).attr('data-uid')}}
    jQuery.post('ajax.php', params, function (response) {
        console.log(response)
        if (response.ret == 0) {
            location.reload()
        } else {
            alert(response.msg)
        }
    }, 'json')
})
JS;
            \Nexus\Nexus::js($removeLeechWarnJs, 'footer', false);
		}else{
			print("<i>".$lang_userdetails['text_for_unlimited_time']."</i>");
		}
		print("</td></tr>");
	}else{
		print("<td class=\"rowfollow\">".$lang_userdetails['text_not_warned']."</td></tr>\n");
	}
	print("</table></td></tr>");
	\App\Support\Html::tr($lang_userdetails['row_enabled'], $migratedHelp, 1);
	\App\Support\Html::tr($lang_userdetails['row_forum_post_possible'], "<input type=\"radio\" name=\"forumpost\" value=\"yes\"" .($user["forumpost"]=="yes" ? " checked=\"checked\"" : "") . " />".$lang_userdetails['radio_yes']."<input type=\"radio\" name=\"forumpost\" value=\"no\"" .($user["forumpost"]=="no" ? " checked=\"checked\"" : "") . " />".$lang_userdetails['radio_no'], 1);
	\App\Support\Html::tr($lang_userdetails['row_upload_possible'], "<input type=\"radio\" name=\"uploadpos\" value=\"yes\"" .($user["uploadpos"]=="yes" ? " checked=\"checked\"" : "") . " />".$lang_userdetails['radio_yes']."<input type=\"radio\" name=\"uploadpos\" value=\"no\"" .($user["uploadpos"]=="no" ? " checked=\"checked\"" : "") . " />".$lang_userdetails['radio_no'], 1);
	\App\Support\Html::tr($lang_userdetails['row_download_possible'], "<input type=\"radio\" name=\"downloadpos\" value=\"yes\"" .($user["downloadpos"]=="yes" ? " checked=\"checked\"" : "") . " />".$lang_userdetails['radio_yes']."<input type=\"radio\" name=\"downloadpos\" value=\"no\"" .($user["downloadpos"]=="no" ? " checked=\"checked\"" : "") . " />".$lang_userdetails['radio_no'], 1);
	if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::MANAGE_USER_CONFIDENTIAL_INFO))
	{
		\App\Support\Html::tr($lang_userdetails['row_change_username'], "<input type=\"text\" size=\"25\" name=\"username\" value=\"" . htmlspecialchars($user['username']) . "\" />", 1);

		\App\Support\Html::tr($lang_userdetails['row_change_email'], "<input type=\"text\" size=\"80\" name=\"email\" value=\"" . htmlspecialchars($user['email']) . "\" />", 1);
	}

	\App\Support\Html::tr($lang_userdetails['row_change_password'], "<input disabled type=\"password\" name=\"chpassword\" size=\"50\" />".$migratedHelp, 1);
	\App\Support\Html::tr($lang_userdetails['row_repeat_password'], "<input disabled type=\"password\" name=\"passagain\" size=\"50\" />".$migratedHelp, 1);

	if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::MANAGE_USER_CONFIDENTIAL_INFO))
	{
		\App\Support\Html::tr($lang_userdetails['row_amount_uploaded'], "<input disabled type=\"text\" size=\"60\" name=\"uploaded\" value=\"" . htmlspecialchars($user['uploaded']) . "\" /><input type=\"hidden\" name=\"ori_uploaded\" value=\"" . htmlspecialchars($user['uploaded']) . "\" />".$migratedHelp, 1);
		\App\Support\Html::tr($lang_userdetails['row_amount_downloaded'], "<input disabled type=\"text\" size=\"60\" name=\"downloaded\" value=\"" .htmlspecialchars($user['downloaded']) . "\" /><input type=\"hidden\" name=\"ori_downloaded\" value=\"" .htmlspecialchars($user['downloaded']) . "\" />".$migratedHelp, 1);
		\App\Support\Html::tr($lang_userdetails['row_seeding_karma'], "<input disabled type=\"text\" size=\"60\" name=\"bonus\" value=\"" .number_format($user['seedbonus'], 1) . "\" /><input type=\"hidden\" name=\"ori_bonus\" value=\"" .number_format($user['seedbonus'], 1) . "\" />".$migratedHelp, 1);
		\App\Support\Html::tr($lang_userdetails['row_invites'], "<input disabled type=\"text\" size=\"60\" name=\"invites\" value=\"" .htmlspecialchars($user['invites']) . "\" />".$migratedHelp, 1);
	}
	\App\Support\Html::tr($lang_userdetails['row_passkey'], "<input name=\"resetkey\" value=\"yes\" type=\"checkbox\" />".$lang_userdetails['checkbox_reset_passkey'], 1);

	print("<tr><td class=\"toolbox\" colspan=\"2\" align=\"center\"><input type=\"submit\" class=\"btn\" value=\"".$lang_userdetails['submit_okay']."\" /></td></tr>\n");
	print("</table>\n");
	print("</form>\n");
	\App\Support\Html::endFrame();
	if (\App\Auth\Permission::can(\App\Enums\Permission\PermissionEnum::USER_DELETE))
	{
		\App\Support\Html::beginFrame($lang_userdetails['text_delete_user'], true);
		print("<form method=\"post\" action=\"delacctadmin.php\" name=\"deluser\">
		<input name=\"userid\" size=\"10\" type=\"hidden\" value=\"". $user["id"] ."\" />
		<input name=\"delenable\" type=\"checkbox\" onclick=\"if (this.checked) {enabledel('".$lang_userdetails['js_delete_user_note']."');}else{disabledel();}\" /><input name=\"submit\" type=\"submit\" value=\"".$lang_userdetails['submit_delete']."\" disabled=\"disabled\" /></form>");
		\App\Support\Html::endFrame();
	}
}

$claimAllSeedingConfirmation = \App\Support\Locale::trans('claim.claim_all_seeding_confirmation', [], null);
$claimJs = '';
if ($userInfo->id == $CURUSER['id'] && \App\Support\Permissions::hasRoleWorkSeeding((int) $userInfo->id)) {
    $claimJs = <<<JS
jQuery("body").on("click", "#claim-all-seeding", function (e) {
    layer.confirm("$claimAllSeedingConfirmation", {}, function () {
        jQuery.post('/plugin/claim_all_seeding', {"action": "claimAllSeeding"}, function (response) {
            if (response.ret == 0) {
                window.location.reload()
            } else {
                layer.alert(response.msg)
            }
        }, 'json')
    })
})
JS;
}
$paginationJs = <<<JS
jQuery("body").on("click", ".nexus-pagination a", function (e) {
    e.preventDefault()
    let _this = jQuery(this)
    let box = _this.closest("[data-type]")
    let type = box.attr("data-type");
    let url = _this.attr("href") + "&userid={$user['id']}&type=" + type;
    let result = ajax.gets(url);
    box.html(result)
})
$claimJs
JS;

\Nexus\Nexus::js($paginationJs, 'footer', false);