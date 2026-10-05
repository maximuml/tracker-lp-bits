@props(['rows' => [], 'caption' => ''])
<x-topten.frame :caption="$caption">
<tr><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/topten.col_rank')}}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/topten.col_subject')}}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/topten.col_posts')}}</th></tr>
@foreach ($rows as $a)
<tr><td class="text-center">{{ $loop->iteration }}</td><td><a href="/forums?action=viewtopic&amp;forumid={{ (int) ($a['forumid'] ?? 0) }}&amp;topicid={{ (int) ($a['topicid'] ?? 0) }}">{{ $a['topicsubject'] ?? '' }}</a></td><td class="text-right">{{ number_format((int) ($a['postnum'] ?? 0)) }}</td></tr>
@endforeach
</x-topten.frame>
