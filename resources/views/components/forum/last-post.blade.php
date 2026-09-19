@props(['post'])
@if ($post === null)
    N/A
@else
    <a href="?action=viewtopic&amp;topicid={{ $post->topicId }}&amp;page=last#last" title="{{ $post->fullSubject }}">@if ($post->hlcolor > 0)<b class="nx-hl-{{ $post->hlcolor }}">{{ $post->subject }}</b>@else{{ $post->subject }}@endif</a><br />
    {{ $post->date }}&nbsp;|&nbsp;{{ $post->poster }}
@endif
