@props(['rows' => [], 'caption' => ''])
<x-topten.frame :caption="$caption">
<tr>
<td class="colhead">{{ __('legacy/topten.col_rank')}}</td>
<td class="colhead" align="left"> {{ __('legacy/topten.col_user')}} </td>
<td class="colhead"> {{ __('legacy/topten.col_uploaded')}} </td>
<td class="colhead" align="left"> {{ __('legacy/topten.col_ul_speed')}} </td>
<td class="colhead"> {{ __('legacy/topten.col_downloaded')}}</td>
<td class="colhead" align="left"> {{ __('legacy/topten.col_dl_speed')}} </td>
<td class="colhead" align="right"> {{ __('legacy/topten.col_ratio')}} </td>
<td class="colhead" align="left"> {{ __('legacy/topten.col_joined')}} </td>
</tr>
@foreach ($rows as $a)
<tr><td class="rowfollow" align="center">{{ $loop->iteration }}</td><td class="rowfollow" align="left"><x-topten.user-link :id="$a['userid'] ?? 0" /></td><td class="rowfollow" align="right">{{ \App\Support\Format::size((float) ($a['uploaded'] ?? 0)) }}</td><td class="rowfollow" align="right">{{ \App\Support\Format::size((float) ($a['upspeed'] ?? 0)) }}/s</td><td class="rowfollow" align="right">{{ \App\Support\Format::size((float) ($a['downloaded'] ?? 0)) }}</td><td class="rowfollow" align="right">{{ \App\Support\Format::size((float) ($a['downspeed'] ?? 0)) }}/s</td><td class="rowfollow" align="right"><x-topten.ratio :up="$a['uploaded'] ?? 0" :down="$a['downloaded'] ?? 0" :infinite="__('legacy/topten.text_inf')" /></td><td class="rowfollow" align="left">{{ \App\Support\Time::format($a['added'] ?? '', true, false) }}</td></tr>
@endforeach
</x-topten.frame>
