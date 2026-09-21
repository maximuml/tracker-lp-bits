@props(['rows' => [], 'caption' => ''])
<x-topten.frame :caption="$caption">
<tr><th class="colhead" scope="col">{{ __('legacy/topten.col_rank')}}</th><th class="colhead" scope="col">{{ __('legacy/topten.col_subject')}}</th><th class="colhead" scope="col">{{ __('legacy/topten.col_posts')}}</th></tr>
@foreach ($rows as $a)
<tr><td class="rowfollow nx-center">{{ $loop->iteration }}</td><td class="rowfollow"><a href="forums.php?action=viewtopic&amp;forumid={{ (int) ($a['forumid'] ?? 0) }}&amp;topicid={{ (int) ($a['topicid'] ?? 0) }}">{{ $a['topicsubject'] ?? '' }}</a></td><td class="rowfollow nx-align-right">{{ number_format((int) ($a['postnum'] ?? 0)) }}</td></tr>
@endforeach
</x-topten.frame>
