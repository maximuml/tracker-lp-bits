@props(['rows' => [], 'caption' => ''])
<x-topten.frame :caption="$caption">
<tr><td class="colhead">{{ __('legacy/topten.col_rank')}}</td><td class="colhead">{{ __('legacy/topten.col_subject')}}</td><td class="colhead">{{ __('legacy/topten.col_posts')}}</td></tr>
@foreach ($rows as $a)
<tr><td class="rowfollow" align="center">{{ $loop->iteration }}</td><td class="rowfollow" align="left"><a href="forums.php?action=viewtopic&amp;forumid={{ (int) ($a['forumid'] ?? 0) }}&amp;topicid={{ (int) ($a['topicid'] ?? 0) }}">{{ $a['topicsubject'] ?? '' }}</a></td><td class="rowfollow" align="right">{{ number_format((int) ($a['postnum'] ?? 0)) }}</td></tr>
@endforeach
</x-topten.frame>
