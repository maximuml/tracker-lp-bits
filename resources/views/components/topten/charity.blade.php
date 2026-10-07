@props(['rows' => [], 'caption' => ''])
<x-topten.frame :caption="$caption">
<tr><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('topten.col_rank')}}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('topten.col_username')}}</th><th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('topten.col_bonus')}}</th></tr>
@foreach ($rows as $a)
<tr><td class="text-center">{{ $loop->iteration }}</td><td><x-topten.user-link :id="$a['id'] ?? 0" /></td><td class="text-right">{{ number_format((float) ($a['charity'] ?? 0)) }}</td></tr>
@endforeach
</x-topten.frame>
