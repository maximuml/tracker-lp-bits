@props(['rows' => [], 'caption' => '', 'lang' => []])
<x-topten.frame :caption="$caption">
<tr>
<td class="colhead" align="center">{{ $lang['col_rank'] ?? '' }}</td>
<td class="colhead" align="left">{{ $lang['col_name'] ?? '' }}</td>
<td class="colhead" align="right"><img class="snatched" src="pic/trans.gif" alt="snatched" title="{{ $lang['title_sna'] ?? '' }}" /></td>
<td class="colhead" align="right">{{ $lang['col_data'] ?? '' }}</td>
<td class="colhead" align="right"><img class="seeders" src="pic/trans.gif" alt="seeders" title="{{ $lang['title_se'] ?? '' }}" /></td>
<td class="colhead" align="right"><img class="leechers" src="pic/trans.gif" alt="leechers" title="{{ $lang['col_le'] ?? '' }}" /></td>
<td class="colhead" align="right">{{ $lang['col_to'] ?? '' }}</td>
<td class="colhead" align="right">{{ $lang['col_ratio'] ?? '' }}</td>
</tr>
@foreach ($rows as $a)
<tr><td class="rowfollow" align="center">{{ $loop->iteration }}</td><td class="rowfollow" align="left"><a href="details.php?id={{ (int) ($a['id'] ?? 0) }}&amp;hit=1"><b>{{ $a['name'] ?? '' }}</b></a></td><td class="rowfollow" align="right">{{ number_format((int) ($a['times_completed'] ?? 0)) }}</td><td class="rowfollow" align="right">{{ \App\Support\Format::size((float) ($a['data'] ?? 0)) }}</td><td class="rowfollow" align="right">{{ number_format((int) ($a['seeders'] ?? 0)) }}</td><td class="rowfollow" align="right">{{ number_format((int) ($a['leechers'] ?? 0)) }}</td><td class="rowfollow" align="right">{{ (int) ($a['leechers'] ?? 0) + (int) ($a['seeders'] ?? 0) }}</td><td class="rowfollow" align="right"><x-topten.ratio :up="$a['seeders'] ?? 0" :down="$a['leechers'] ?? 0" :infinite="$lang['text_inf'] ?? 'Inf.'" :alwaysWrap="true" /></td>
@endforeach
</x-topten.frame>
