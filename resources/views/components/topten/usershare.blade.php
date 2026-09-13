@props(['rows' => [], 'caption' => '', 'lang' => []])
<x-topten.frame :caption="$caption">
<tr>
<td class="colhead">{{ $lang['col_rank'] ?? '' }}</td>
<td class="colhead" align="left"> {{ $lang['col_user'] ?? '' }} </td>
<td class="colhead"> {{ $lang['col_uploaded'] ?? '' }} </td>
<td class="colhead" align="left"> {{ $lang['col_ul_speed'] ?? '' }} </td>
<td class="colhead"> {{ $lang['col_downloaded'] ?? '' }}</td>
<td class="colhead" align="left"> {{ $lang['col_dl_speed'] ?? '' }} </td>
<td class="colhead" align="right"> {{ $lang['col_ratio'] ?? '' }} </td>
<td class="colhead" align="left"> {{ $lang['col_joined'] ?? '' }} </td>
</tr>
@foreach ($rows as $a)
<tr><td class="rowfollow" align="center">{{ $loop->iteration }}</td><td class="rowfollow" align="left"><x-topten.user-link :id="$a['userid'] ?? 0" /></td><td class="rowfollow" align="right">{{ \App\Support\Format::size((float) ($a['uploaded'] ?? 0)) }}</td><td class="rowfollow" align="right">{{ \App\Support\Format::size((float) ($a['upspeed'] ?? 0)) }}/s</td><td class="rowfollow" align="right">{{ \App\Support\Format::size((float) ($a['downloaded'] ?? 0)) }}</td><td class="rowfollow" align="right">{{ \App\Support\Format::size((float) ($a['downspeed'] ?? 0)) }}/s</td><td class="rowfollow" align="right"><x-topten.ratio :up="$a['uploaded'] ?? 0" :down="$a['downloaded'] ?? 0" :infinite="$lang['text_inf'] ?? 'Inf.'" /></td><td class="rowfollow" align="left">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(\App\Support\Time::format($a['added'] ?? '', true, false)))</td></tr>
@endforeach
</x-topten.frame>
