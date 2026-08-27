@php
/** @var array<string, mixed> $home */
/** @var array<string, mixed> $lang */
/** @var array<string, mixed> $curUser */
$lang_usercp = $lang;
$lang_functions = (array) (\app(\App\Support\Globals::class)->get('lang_functions') ?? []);
$CONTENT_WIDTH = $contentWidth;
@endphp
@include('usercp.sections._menu', ['selected' => 'home'])

<table border="0" cellspacing="0" cellpadding="5" width={{ $CONTENT_WIDTH }}>
@php
\App\Support\Html::trSmall($lang_usercp['row_join_date'] ?? 'Join date', $home['joinDate'], 1);
\App\Support\Html::trSmall($lang_usercp['row_email_address'] ?? 'Email', $home['email'], 1);
\App\Support\Html::trSmall($lang_usercp['row_ip_location'] ?? 'IP location', $home['ipLocation'], 1);
if ($home['showAvatar']) {
    \App\Support\Html::trSmall($lang_usercp['row_avatar'] ?? 'Avatar', '<img src="'.htmlspecialchars($home['avatarUrl']).'" border=0>', 1);
}
\App\Support\Html::trSmall($lang_usercp['row_passkey'] ?? 'Passkey', $home['passkey'], 1);
if ($home['passkeyLoginForm'] !== '') {
    \App\Support\Html::trSmall($lang_usercp['row_passkey_login_url'] ?? 'Passkey login URL', $home['passkeyLoginForm'], 1);
}
\App\Support\Html::trSmall($lang_usercp['row_invitations'] ?? 'Invitations', $home['invites'].' [<a href="invite.php?id='.(int) ($curUser['id'] ?? 0).'" title="'.htmlspecialchars($lang_usercp['link_send_invitation'] ?? '').'">'.htmlspecialchars($lang_usercp['text_send'] ?? '').'</a>]', 1);
\App\Support\Html::trSmall($lang_usercp['row_karma_points'] ?? 'Karma', $home['seedbonus'].' [<a href="mybonus.php" title="'.htmlspecialchars($lang_usercp['link_use_karma_points'] ?? '').'">'.htmlspecialchars($lang_usercp['text_use'] ?? '').'</a>]', 1);
\App\Support\Html::trSmall($lang_usercp['row_written_comments'] ?? 'Comments', $home['commentCount'].' [<a href="userhistory.php?action=viewcomments&id='.(int) ($curUser['id'] ?? 0).'" title="'.htmlspecialchars($lang_usercp['link_view_comments'] ?? '').'">'.htmlspecialchars($lang_usercp['text_view'] ?? '').'</a>]', 1);
@endphp

@php
// Tokens
$tok = $home['tokens'];
$tokenHtml = '';
if (! empty($tok['tokens'])):
    $tokenHtml .= "<table border='1' cellspacing='0' cellpadding='5' id='token-table'><tr><td class='colhead'>ID</td><td class='colhead'>{$tok['columnName']}</td><td class='colhead'>{$tok['columnPermission']}</td><td class='colhead'>{$tok['columnCreatedAt']}</td><td class='colhead'>{$tok['actionLabel']}</td></tr>";
    foreach ($tok['tokens'] as $tokenRecord):
        $tokenHtml .= '<tr>';
        $tokenHtml .= sprintf('<td>%s</td>', (int) $tokenRecord['id']);
        $tokenHtml .= sprintf('<td>%s</td>', htmlspecialchars((string) $tokenRecord['name']));
        $tokenHtml .= sprintf('<td>%s</td>', htmlspecialchars((string) $tokenRecord['abilitiesText']));
        $tokenHtml .= sprintf('<td>%s</td>', htmlspecialchars((string) $tokenRecord['created_at']));
        $tokenHtml .= sprintf('<td><img style="cursor: pointer" class="staff_delete token-del" src="pic/trans.gif" alt="D" title="%s" data-id="%s"></td>', htmlspecialchars($tok['deleteLabel']), (int) $tokenRecord['id']);
        $tokenHtml .= '</tr>';
    endforeach;
    $tokenHtml .= '</table>';
endif;
$tokenHtml .= sprintf('<div><input type="button" id="add-token-box-btn" value="%s"/></div>', htmlspecialchars($tok['actionCreate']));
\App\Support\Html::trSmall($tok['label'], $tokenHtml, 1);

$tokenForm = <<<FORM
<div class="form-box">
<form id="token-box-form">
    <div class="form-control-row">
        <div class="label">{$tok['columnName']}</div>
        <div class="field"><input type="text" name="name"></div>
    </div>
    <div class="form-control-row">
        <div class="label">{$tok['columnPermission']}</div>
        <div class="field">{$tok['permissionCheckbox']}</div>
    </div>
</form>
</div>
FORM;
$tokLabel = addslashes($tok['label']);
$tokCreate = addslashes($tok['actionCreate']);
$tokConfirmRemove = addslashes($tok['confirmRemoveLabel']);
$tokenJs = <<<JS
jQuery('#add-token-box-btn').on('click', function () {
    layer.open({
        type: 1,
        title: "{$tokLabel} {$tokCreate}",
        content: `{$tokenForm}`,
        btn: ['OK'],
        btnAlign: 'c',
        yes: function (index) {
            layer.close(index);
            jQuery('body').loading({stoppable: false});
            let params = jQuery('#token-box-form').serialize()
            jQuery.post('/web/token/add', params, function (response) {
                 jQuery('body').loading('stop');
                console.log(response)
                if (response.ret != 0) {
                    layer.alert(response.msg, window.nexusLayerOptions.alert)
                } else {
                    layer.alert(response.msg, window.nexusLayerOptions.alert, function(index) {
                        layer.close(index);
                        window.location.reload()
                    })
                }
            }, 'json')
        }
    })
});
jQuery('#token-table').on('click', '.token-del', function () {
    let params = {id: jQuery(this).attr("data-id")}
    layer.confirm("{$tokConfirmRemove}", window.nexusLayerOptions.confirm, function (index) {
        layer.close(index)
        jQuery('body').loading({stoppable: false});
        jQuery.post('/web/token/del', params, function (response) {
            console.log(response)
            if (response.ret != 0) {
                jQuery('body').loading('stop');
                layer.alert(response.msg, window.nexusLayerOptions.alert)
                return
            }
            window.location.reload()
        }, 'json')
    })
});
JS;
\Nexus\Nexus::js($tokenJs, 'footer', false);

// Forum posts row
if ($home['forumPosts']):
    $fpHtml = $home['forumPosts'].' [<a href="userhistory.php?action=viewposts&id='.(int) ($curUser['id'] ?? 0).'" title="'.htmlspecialchars($lang_usercp['link_view_posts'] ?? '').'">'.htmlspecialchars($lang_usercp['text_view'] ?? '').'</a>] ('.$home['dayPosts'].htmlspecialchars($lang_usercp['text_posts_per_day'] ?? '').'; '.$home['percentages'].htmlspecialchars($lang_usercp['text_of_total_posts'] ?? '').')';
    \App\Support\Html::tr($lang_usercp['row_forum_posts'] ?? 'Forum posts', $fpHtml, 1);
endif;
@endphp
</table>
<table border="0" cellspacing="0" cellpadding="5" width={{ $CONTENT_WIDTH }}>
    <td align=center class=tabletitle><b>{{ $home['readTopics']['title'] }}</b></td>
</table>
@php
$rt = $home['readTopics'];
@endphp
<table border=0 cellspacing=0 cellpadding=3 width={{ $CONTENT_WIDTH }}><tr>
<td class=colhead align=left width=80%>{{ $rt['colTopicTitle'] }}</td>
<td class=colhead align=center><nobr>{{ $rt['colReplies'] }}/{{ $rt['colViews'] }}</nobr></td>
<td class=colhead align=center>{{ $rt['colTopicStarter'] }}</td>
<td class=colhead align=center width=20%>{{ $rt['colLastPost'] }}</td>
</tr>
@foreach ($rt['items'] as $topic)
@php
$subject = '<a href=forums.php?action=viewtopic&topicid='.(int) $topic['id'].'><b>'.htmlspecialchars($topic['subject']).'</b></a>';
@endphp
<tr class=tableb><td style='padding-left: 10px' align=left class=rowfollow>{!! $subject !!}</td>
<td align=center class=rowfollow>{{ $topic['replies'] }}/{{ $topic['views'] }}</td>
<td align=center class=rowfollow>{!! $topic['author'] !!}</td>
<td align=center class=rowfollow><nobr>{{ $topic['lastPostAdded'] }} | {!! $topic['lastPostUsername'] !!}</nobr></td></tr>
@endforeach
</table>
