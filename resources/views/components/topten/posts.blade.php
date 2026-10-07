@props(['rows' => [], 'caption' => ''])
<x-topten.frame :caption="$caption">
<tr><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('topten.col_rank')}}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('topten.col_username')}}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('topten.col_topics')}}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('topten.col_posts')}}</th></tr>
@foreach ($rows as $a)
<tr><td class="text-center">{{ $loop->iteration }}</td><td><x-topten.user-link :id="$a['userid'] ?? 0" /></td><td class="text-right">{{ number_format((int) ($a['usertopics'] ?? 0)) }}</td><td class="text-right">{{ number_format((int) ($a['userposts'] ?? 0)) }}</td></tr>
@endforeach
</x-topten.frame>
