@props(['rows' => [], 'caption' => ''])
<x-topten.frame :caption="$caption">
<tr><th class="colhead" scope="col">{{ __('legacy/topten.col_rank')}}</th><th class="colhead" scope="col">{{ __('legacy/topten.col_name')}}</th><th class="colhead" scope="col">{{ __('legacy/topten.col_number')}}</th></tr>
@foreach ($rows as $a)
<tr><td class="rowfollow nx-center">{{ $loop->iteration }}</td><td class="rowfollow">{{ $a['client_name'] ?? '' }}</td><td class="rowfollow nx-align-right">{{ number_format((int) ($a['client_num'] ?? 0)) }}</td></tr>
@endforeach
</x-topten.frame>
