@props(['rows' => [], 'caption' => ''])
<x-topten.frame :caption="$caption">
<tr><td class="colhead">{{ __('legacy/topten.col_rank')}}</td><td class="colhead">{{ __('legacy/topten.col_username')}}</td><td class="colhead">{{ __('legacy/topten.col_bonus')}}</td></tr>
@foreach ($rows as $a)
<tr><td class="rowfollow" align="center">{{ $loop->iteration }}</td><td class="rowfollow" align="left"><x-topten.user-link :id="$a['id'] ?? 0" /></td><td class="rowfollow" align="right">{{ number_format((float) ($a['seedbonus'] ?? 0), 1) }}</td></tr>
@endforeach
</x-topten.frame>
