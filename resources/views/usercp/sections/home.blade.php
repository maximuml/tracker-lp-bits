@include('usercp.sections._menu', ['selected' => 'home'])

<table border="0" cellspacing="0" cellpadding="5" width={{ $contentWidth }}>
<x-settings-row-small :label="\App\Support\Html\SafeHtml::fromTrustedHtml($lang['row_join_date'] ?? 'Join date')">{{ $home['joinDate'] }}</x-settings-row-small>
<x-settings-row-small :label="\App\Support\Html\SafeHtml::fromTrustedHtml($lang['row_email_address'] ?? 'Email')">{{ $home['email'] }}</x-settings-row-small>
<x-settings-row-small :label="\App\Support\Html\SafeHtml::fromTrustedHtml($lang['row_ip_location'] ?? 'IP location')">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($home['ipLocation']))</x-settings-row-small>
@if ($home['showAvatar'])
<x-settings-row-small :label="\App\Support\Html\SafeHtml::fromTrustedHtml($lang['row_avatar'] ?? 'Avatar')">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($home['avatarHtml']))</x-settings-row-small>
@endif
<x-settings-row-small :label="\App\Support\Html\SafeHtml::fromTrustedHtml($lang['row_passkey'] ?? 'Passkey')">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($home['passkey']))</x-settings-row-small>
@if ($home['passkeyLoginForm'] !== '')
<x-settings-row-small :label="\App\Support\Html\SafeHtml::fromTrustedHtml($lang['row_passkey_login_url'] ?? 'Passkey login URL')">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($home['passkeyLoginForm']))</x-settings-row-small>
@endif
<x-settings-row-small :label="\App\Support\Html\SafeHtml::fromTrustedHtml($lang['row_invitations'] ?? 'Invitations')">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($home['invitesHtml']))</x-settings-row-small>
<x-settings-row-small :label="\App\Support\Html\SafeHtml::fromTrustedHtml($lang['row_karma_points'] ?? 'Karma')">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($home['karmaHtml']))</x-settings-row-small>
<x-settings-row-small :label="\App\Support\Html\SafeHtml::fromTrustedHtml($lang['row_written_comments'] ?? 'Comments')">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($home['commentsHtml']))</x-settings-row-small>

<x-settings-row-small :label="\App\Support\Html\SafeHtml::fromTrustedHtml($home['tokens']['label'])">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($home['tokens']['tableHtml']))</x-settings-row-small>

@if ($home['forumPostsHtml'] !== null)
<x-settings-row :label="\App\Support\Html\SafeHtml::fromTrustedHtml($lang['row_forum_posts'] ?? 'Forum posts')">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($home['forumPostsHtml']))</x-settings-row>
@endif
</table>
<table border="0" cellspacing="0" cellpadding="5" width={{ $contentWidth }}>
    <td align=center class=tabletitle><b>{{ $home['readTopics']['title'] }}</b></td>
</table>
<table border=0 cellspacing=0 cellpadding=3 width={{ $contentWidth }}><tr>
<td class=colhead align=left width=80%>{{ $home['readTopics']['colTopicTitle'] }}</td>
<td class=colhead align=center><nobr>{{ $home['readTopics']['colReplies'] }}/{{ $home['readTopics']['colViews'] }}</nobr></td>
<td class=colhead align=center>{{ $home['readTopics']['colTopicStarter'] }}</td>
<td class=colhead align=center width=20%>{{ $home['readTopics']['colLastPost'] }}</td>
</tr>
@foreach ($home['readTopics']['items'] as $topic)
<tr class=tableb><td style='padding-left: 10px' align=left class=rowfollow><a href=forums.php?action=viewtopic&topicid={{ (int) $topic['id'] }}><b>{{ $topic['subject'] }}</b></a></td>
<td align=center class=rowfollow>{{ $topic['replies'] }}/{{ $topic['views'] }}</td>
<td align=center class=rowfollow>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($topic['author']))</td>
<td align=center class=rowfollow><nobr>{{ $topic['lastPostAdded'] }} | @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($topic['lastPostUsername']))</nobr></td></tr>
@endforeach
</table>
