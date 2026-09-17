@props(['rows' => [], 'caption' => '', 'what' => ''])
<x-topten.frame :caption="$caption">
<tr><td class="colhead">{{ __('legacy/topten.col_rank')}}</td><td class="colhead">{{ __('legacy/topten.col_username')}}</td><td class="colhead">{{ $what }}</td></tr>
@foreach ($rows as $a)
<tr><td class="rowfollow" align="center">{{ $loop->iteration }}</td><td class="rowfollow" align="left"><x-topten.user-link :id="$a['userid'] ?? 0" /></td><td class="rowfollow" align="right">{{ number_format((int) ($a['num'] ?? 0)) }}</td></tr>
@endforeach
</x-topten.frame>
