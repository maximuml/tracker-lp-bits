@include('usercp.sections._menu', ['selected' => 'home'])

<div class="nx-ucards">
<div class="nx-ucard">
	<div class="nx-ucard__title">{{ __('legacy/usercp.section_account') }}</div>
	<div class="nx-fgrid nx-fgrid--flat">
	<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_join_date')">@if ($home->joinDate === null) N/A @else {{ $home->joinDate }} (<x-time :value="$home->joinDate" :force="true" />) @endif</x-settings-row-small>
	<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_email_address')">{{ $home->email }}</x-settings-row-small>
	<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_ip_location')">{{ $home->ipLocation }}</x-settings-row-small>
	@if ($home->showAvatar)
	<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_avatar')"><img src="{{ $home->avatarUrl }}" alt=""></x-settings-row-small>
	@endif
	<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_invitations')">{{ $home->invites }} [<a href="/web/invite?id={{ $home->userId }}" title="{{ $home->invitesLinkTitle }}">{{ __('legacy/usercp.text_send') }}</a>]</x-settings-row-small>
	<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_karma_points')">{{ $home->seedbonus }} [<a href="/web/mybonus" title="{{ $home->karmaLinkTitle }}">{{ __('legacy/usercp.text_use') }}</a>]</x-settings-row-small>
	</div>
</div>

<div class="nx-ucard">
	<div class="nx-ucard__title">{{ __('legacy/usercp.section_security') }}</div>
	<div class="nx-fgrid nx-fgrid--flat">
	<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_passkey')">
		<span class="nx-copyfield">
			<input type="text" class="nx-copyfield__input" id="ucp-passkey" readonly aria-label="{{ __('legacy/usercp.row_passkey') }}" value="&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;" data-copy-value="{{ (string) ($curUser['passkey'] ?? '') }}" data-mask-value="&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;&#8226;" />
			<button type="button" class="nx-postbtn" data-reveal="#ucp-passkey" data-label-show="{{ __('legacy/functions.text_show') }}" data-label-hide="{{ __('legacy/functions.text_hide') }}">{{ __('legacy/functions.text_show') }}</button>
			<button type="button" class="nx-postbtn" data-copy="#ucp-passkey" data-copy-done="{{ __('legacy/functions.text_copied') }}">{{ __('legacy/functions.text_copy') }}</button>
		</span>
	</x-settings-row-small>
	@if ($home->passkeyLogin !== null)
	<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_passkey_login_url')"><form method="POST" action="{{ $home->passkeyLogin->action }}"><input type="hidden" name="passkey" value="{{ $home->passkeyLogin->passkey }}"><input type="hidden" name="timestamp" value="{{ $home->passkeyLogin->timestamp }}"><input type="hidden" name="signature" value="{{ $home->passkeyLogin->signature }}"><button type="submit" class="btn">Login</button></form></x-settings-row-small>
	@endif
	</div>
</div>

<div class="nx-ucard">
	<div class="nx-ucard__title">{{ __('legacy/usercp.section_tokens') }}</div>
	<div class="nx-fgrid nx-fgrid--flat">
	<x-settings-row-small layout="grid" :label="$home->tokens->label">
		@if ($home->tokens->items !== [])
		<x-data-table id="token-table" :headers="['ID', $home->tokens->columnName, $home->tokens->columnPermission, $home->tokens->columnCreatedAt, $home->tokens->actionLabel]">
			@foreach ($home->tokens->items as $token)
			<tr>
				<td>{{ $token['id'] }}</td>
				<td>{{ $token['name'] }}</td>
				<td>{{ $token['abilities'] }}</td>
				<td>{{ $token['createdAt'] }}</td>
				<td><img class="staff_delete token-del" src="pic/trans.gif" alt="D" title="{{ $home->tokens->deleteLabel }}" data-id="{{ $token['id'] }}"></td>
			</tr>
			@endforeach
		</x-data-table>
		@endif
		<div><input type="button" id="add-token-box-btn" class="nx-postbtn" value="{{ $home->tokens->actionCreate }}"/></div>
		<template id="token-form-template"><div class="form-box"><form id="token-box-form"><div class="form-control-row"><div class="label">{{ $home->tokens->columnName }}</div><div class="field"><input type="text" name="name"></div></div><div class="form-control-row"><div class="label">{{ $home->tokens->columnPermission }}</div><div class="field">@foreach ($home->tokens->permissions as $perm)<label><input type="checkbox" name="permissions[]" value="{{ $perm['value'] }}">{{ $perm['label'] }}</label>@endforeach</div></div></form></div></template>
	</x-settings-row-small>
	</div>
</div>

<div class="nx-ucard">
	<div class="nx-ucard__title">{{ __('legacy/usercp.section_activity') }}</div>
	<div class="nx-fgrid nx-fgrid--flat">
	<x-settings-row-small layout="grid" :label="__('legacy/usercp.row_written_comments')">{{ $home->commentCount }} [<a href="/web/userhistory?action=viewcomments&id={{ $home->userId }}" title="{{ $home->commentsLinkTitle }}">{{ __('legacy/usercp.text_view') }}</a>]</x-settings-row-small>
	@if ($home->forumPosts !== null)
	<x-settings-row layout="grid" :label="__('legacy/usercp.row_forum_posts')">{{ $home->forumPosts->posts }} [<a href="/web/userhistory?action=viewposts&id={{ $home->userId }}" title="{{ $home->postsLinkTitle }}">{{ __('legacy/usercp.text_view') }}</a>] ({{ $home->forumPosts->dayPosts }}{{ __('legacy/usercp.text_posts_per_day') }}; {{ $home->forumPosts->percentages }}{{ __('legacy/usercp.text_of_total_posts') }})</x-settings-row>
	@endif
	</div>
</div>
</div>

<div class="nx-ucard">
	<div class="nx-ucard__title">{{ __('legacy/usercp.text_recently_read_topics') }}</div>
<x-data-table :headers="[__('legacy/usercp.col_topic_title'), __('legacy/usercp.col_replies').'/'.__('legacy/usercp.col_views'), __('legacy/usercp.col_topic_starter'), __('legacy/usercp.col_last_post')]">
@foreach ($home->readTopics as $topic)
<tr>
    <td><a href="/forums?action=viewtopic&amp;topicid={{ $topic->id }}"><b>{{ $topic->subject }}</b></a></td>
    <td class="text-center">{{ $topic->replies }}/{{ $topic->views }}</td>
    <td class="text-center">{{ $topic->author }}</td>
    <td class="text-center whitespace-nowrap">@if ($topic->lastPostAdded !== null)<x-time :value="$topic->lastPostAdded" /> | @endif{{ $topic->lastPostUsername }}</td>
</tr>
@endforeach
</x-data-table>
</div>
