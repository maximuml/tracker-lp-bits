@props(['rows' => [], 'caption' => ''])
<x-topten.frame :caption="$caption">
<tr><th class="colhead" scope="col">{{ __('legacy/topten.col_rank')}}</th><th class="colhead" scope="col">{{ __('legacy/topten.col_username')}}</th><th class="colhead" scope="col">{{ __('legacy/topten.col_topics')}}</th><th class="colhead" scope="col">{{ __('legacy/topten.col_posts')}}</th></tr>
@foreach ($rows as $a)
<tr><td class="rowfollow nx-center">{{ $loop->iteration }}</td><td class="rowfollow"><x-topten.user-link :id="$a['userid'] ?? 0" /></td><td class="rowfollow nx-align-right">{{ number_format((int) ($a['usertopics'] ?? 0)) }}</td><td class="rowfollow nx-align-right">{{ number_format((int) ($a['userposts'] ?? 0)) }}</td></tr>
@endforeach
</x-topten.frame>
