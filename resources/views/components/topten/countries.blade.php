@props(['rows' => [], 'caption' => '', 'lang' => [], 'what' => ''])
<x-topten.frame :caption="$caption">
<tr>
<td class="colhead">{{ $lang['col_rank'] ?? '' }}</td>
<td class="colhead" align="left">{{ $lang['col_country'] ?? '' }}</td>
<td class="colhead" align="right">{{ $what }}</td>
</tr>
@foreach ($rows as $a)
<tr><td class="rowfollow" align="center">{{ $loop->iteration }}</td><td class="rowfollow" align="left"><table border="0" class="main" cellspacing="0" cellpadding="0"><tr><td class="embedded"><img align="center" src="pic/flag/{{ $a['flagpic'] ?? '' }}" alt="" /></td><td class="embedded" style='padding-left: 5px'><b>{{ $a['name'] ?? '' }}</b></td></tr></table></td><td class="rowfollow" align="right">@if ($what === ($lang['col_users'] ?? 'Users')){{ number_format((float) ($a['num'] ?? 0)) }}@elseif ($what === ($lang['col_uploaded'] ?? 'Uploaded')){{ \App\Support\Format::size((float) ($a['ul'] ?? 0)) }}@elseif ($what === ($lang['col_average'] ?? 'Average')){{ \App\Support\Format::size((float) ($a['ul_avg'] ?? 0)) }}@elseif ($what === ($lang['col_ratio'] ?? 'Ratio')){{ number_format((float) ($a['r'] ?? 0), 2) }}@endif</td></tr>
@endforeach
</x-topten.frame>
