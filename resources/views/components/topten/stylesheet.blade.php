@props(['rows' => [], 'caption' => ''])
<x-topten.frame :caption="$caption">
<tr><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/topten.col_rank')}}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/topten.col_name')}}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/topten.col_number')}}</th></tr>
@foreach ($rows as $a)
<tr><td class="text-center">{{ $loop->iteration }}</td><td>{{ $a['stylesheet_name'] ?? '' }}</td><td class="text-right">{{ number_format((int) ($a['stylesheet_num'] ?? 0)) }}</td></tr>
@endforeach
</x-topten.frame>
