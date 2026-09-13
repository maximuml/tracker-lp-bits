@props(['rows' => [], 'caption' => '', 'lang' => []])
<x-topten.frame :caption="$caption">
<tr><td class="colhead">{{ $lang['col_rank'] ?? '' }}</td><td class="colhead">{{ $lang['col_username'] ?? '' }}</td><td class="colhead">{{ $lang['col_donated_usd'] ?? '' }}</td><td class="colhead">{{ $lang['col_donated_cny'] ?? '' }}</td></tr>
@foreach ($rows as $a)
<tr><td class="rowfollow" align="center">{{ $loop->iteration }}</td><td class="rowfollow" align="left"><x-topten.user-link :id="$a['id'] ?? 0" /></td><td class="rowfollow" align="right">{{ number_format((float) ($a['donated'] ?? 0), 2) }}</td><td class="rowfollow" align="right">{{ number_format((float) ($a['donated_cny'] ?? 0), 2) }}</td></tr>
@endforeach
</x-topten.frame>
