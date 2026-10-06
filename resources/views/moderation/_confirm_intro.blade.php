@switch($kind)
@case('user'){{ $userHtml }}@break
@case('torrent')<a href="/web/details/{{ $id }}"><b>{{ $name }}</b></a>@break
@case('offer')<a href="/web/offers?id={{ $id }}&off_details=1"><b>{{ $name }}</b></a>@break
@case('post'){{ $id }}{{ __('legacy/report.text_of_topic') }}<b><a href="/forums?action=viewtopic&topicid={{ $topicid }}&page=p{{ $id }}#{{ $id }}">{{ $subject }}</a></b>{{ __('legacy/report.text_by') }}{{ $userHtml }}@break
@case('comment'){{ $id }}{{ $of }}<b><a href="{{ $url }}">{{ $name }}</a></b>{{ __('legacy/report.text_by') }}{{ $userHtml }}@break
@endswitch
