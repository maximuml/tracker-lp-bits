@switch($kind)
@case('user'){{ $userHtml }}@break
@case('torrent')<a href="details.php?id={{ $id }}"><b>{{ $name }}</b></a>@break
@case('offer')<a href="offers.php?id={{ $id }}&off_details=1"><b>{{ $name }}</b></a>@break
@case('post'){{ $id }}{{ __('legacy/report.text_of_topic') }}<b><a href="forums.php?action=viewtopic&topicid={{ $topicid }}&page=p{{ $id }}#{{ $id }}">{{ $subject }}</a></b>{{ __('legacy/report.text_by') }}{{ $userHtml }}@break
@case('comment'){{ $id }}{{ $of }}<b><a href="{{ $url }}">{{ $name }}</a></b>{{ __('legacy/report.text_by') }}{{ $userHtml }}@break
@endswitch
