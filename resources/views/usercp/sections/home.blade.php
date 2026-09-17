@include('usercp.sections._menu', ['selected' => 'home'])

<div class="nx-fgrid nx-fgrid--flat">
<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_join_date')">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($home['joinDate']))</x-settings-row-small>
<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_email_address')">{{ $home['email'] }}</x-settings-row-small>
<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_ip_location')">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($home['ipLocation']))</x-settings-row-small>
@if ($home['showAvatar'])
<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_avatar')">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($home['avatarHtml']))</x-settings-row-small>
@endif
<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_passkey')">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($home['passkey']))</x-settings-row-small>
@if ($home['passkeyLoginForm'] !== '')
<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_passkey_login_url')">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($home['passkeyLoginForm']))</x-settings-row-small>
@endif
<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_invitations')">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($home['invitesHtml']))</x-settings-row-small>
<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_karma_points')">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($home['karmaHtml']))</x-settings-row-small>
<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_written_comments')">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($home['commentsHtml']))</x-settings-row-small>

<x-settings-row-small layout="grid" :label="$home['tokens']['label']">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($home['tokens']['tableHtml']))</x-settings-row-small>

@if ($home['forumPostsHtml'] !== null)
<x-settings-row layout="grid" :label="__('legacy/usercp.row_forum_posts')">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($home['forumPostsHtml']))</x-settings-row>
@endif
</div>
<div class="nx-center nx-cell-5"><b>{{ $home['readTopics']['title'] }}</b></div>
<table data-nx="data" border=0 cellspacing=0 cellpadding=3 width={{ $contentWidth }}><tr>
<td class=colhead align=left width=80%>{{ $home['readTopics']['colTopicTitle'] }}</td>
<td class=colhead align=center><nobr>{{ $home['readTopics']['colReplies'] }}/{{ $home['readTopics']['colViews'] }}</nobr></td>
<td class=colhead align=center>{{ $home['readTopics']['colTopicStarter'] }}</td>
<td class=colhead align=center width=20%>{{ $home['readTopics']['colLastPost'] }}</td>
</tr>
@foreach ($home['readTopics']['items'] as $topic)
<tr class=tableb><td style='padding-left: 10px' align=left class=rowfollow><a href=forums.php?action=viewtopic&topicid={{ (int) $topic['id'] }}><b>{{ $topic['subject'] }}</b></a></td>
<td align=center class=rowfollow>{{ $topic['replies'] }}/{{ $topic['views'] }}</td>
<td align=center class=rowfollow>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($topic['author']))</td>
<td align=center class=rowfollow><nobr>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($topic['lastPostAdded'])) | @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($topic['lastPostUsername']))</nobr></td></tr>
@endforeach
</table>
