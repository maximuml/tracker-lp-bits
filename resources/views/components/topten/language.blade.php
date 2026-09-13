@props(['rows' => [], 'caption' => '', 'lang' => []])
<x-topten.frame :caption="$caption">
<tr><td class="colhead">{{ $lang['col_rank'] ?? '' }}</td><td class="colhead">{{ $lang['col_name'] ?? '' }}</td><td class="colhead">{{ $lang['col_number'] ?? '' }}</td></tr>
@foreach ($rows as $a)
<tr><td class="rowfollow" align="center">{{ $loop->iteration }}</td><td class="rowfollow" align="left">{{ $a['lang_name'] ?? '' }}</td><td class="rowfollow" align="right">{{ number_format((int) ($a['lang_num'] ?? 0)) }}</td></tr>
@endforeach
</x-topten.frame>
