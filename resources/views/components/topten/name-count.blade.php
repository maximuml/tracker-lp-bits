@props(['rows' => [], 'caption' => '', 'nameKey' => '', 'numKey' => ''])
<x-topten.frame :caption="$caption">
<tr><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('topten.col_rank')}}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('topten.col_name')}}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('topten.col_number')}}</th></tr>
@foreach ($rows as $a)
<tr><td class="text-center">{{ $loop->iteration }}</td><td>{{ $a[$nameKey] ?? '' }}</td><td class="text-right">{{ number_format((int) ($a[$numKey] ?? 0)) }}</td></tr>
@endforeach
</x-topten.frame>
