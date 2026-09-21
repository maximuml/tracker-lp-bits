@props(['rows' => [], 'caption' => '', 'what' => ''])
<x-topten.frame :caption="$caption">
<tr><th class="colhead" scope="col">{{ __('legacy/topten.col_rank')}}</th><th class="colhead" scope="col">{{ __('legacy/topten.col_username')}}</th><th class="colhead" scope="col">{{ $what }}</th></tr>
@foreach ($rows as $a)
<tr><td class="rowfollow nx-center">{{ $loop->iteration }}</td><td class="rowfollow"><x-topten.user-link :id="$a['userid'] ?? 0" /></td><td class="rowfollow nx-align-right">{{ number_format((int) ($a['num'] ?? 0)) }}</td></tr>
@endforeach
</x-topten.frame>
