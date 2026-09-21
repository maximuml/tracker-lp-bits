@props(['rows' => [], 'caption' => ''])
<x-topten.frame :caption="$caption">
<tr><th class="colhead" scope="col">{{ __('legacy/topten.col_rank')}}</th><th class="colhead" scope="col">{{ __('legacy/topten.col_username')}}</th><th class="colhead" scope="col">{{ __('legacy/topten.col_donated_usd')}}</th><th class="colhead" scope="col">{{ __('legacy/topten.col_donated_cny')}}</th></tr>
@foreach ($rows as $a)
<tr><td class="rowfollow nx-center">{{ $loop->iteration }}</td><td class="rowfollow"><x-topten.user-link :id="$a['id'] ?? 0" /></td><td class="rowfollow nx-align-right">{{ number_format((float) ($a['donated'] ?? 0), 2) }}</td><td class="rowfollow nx-align-right">{{ number_format((float) ($a['donated_cny'] ?? 0), 2) }}</td></tr>
@endforeach
</x-topten.frame>
