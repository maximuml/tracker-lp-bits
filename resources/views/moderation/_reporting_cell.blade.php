@switch($kind)
@case('torrent')<a href="/web/details/{{ $id }}">{{ $name }}</a>@break
@case('user'){{ $userHtml }}@break
@case('offer')<a href="/web/offers?id={{ $id }}&off_details=1">{{ $name }}</a>@break
@case('post'){{ __('legacy/reports.text_post_id') }}{{ $id }}{{ __('legacy/reports.text_of_topic') }}<b><a href="/forums?action=viewtopic&topicid={{ $topicid }}&page=p{{ $id }}#pid{{ $id }}">{{ $subject }}</a></b>{{ __('legacy/reports.text_by') }}{{ $userHtml }}@break
@case('comment'){{ __('legacy/reports.text_comment_id') }}{{ $id }}{{ $of }}<b><a href="{{ $url }}">{{ $name }}</a></b>{{ __('legacy/reports.text_by') }}{{ $userHtml }}@break
@endswitch
