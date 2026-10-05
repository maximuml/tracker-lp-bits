@props(['rows' => [], 'caption' => ''])
<x-topten.frame :caption="$caption">
<tr>
<th class="bg-nxm-surface-alt font-semibold" scope="col">{{ __('legacy/topten.col_rank')}}</th>
<th class="bg-nxm-surface-alt text-left font-semibold" scope="col"> {{ __('legacy/topten.col_user')}} </th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"> {{ __('legacy/topten.col_uploaded')}} </th>
<th class="bg-nxm-surface-alt text-left font-semibold" scope="col"> {{ __('legacy/topten.col_ul_speed')}} </th>
<th class="bg-nxm-surface-alt font-semibold" scope="col"> {{ __('legacy/topten.col_downloaded')}}</th>
<th class="bg-nxm-surface-alt text-left font-semibold" scope="col"> {{ __('legacy/topten.col_dl_speed')}} </th>
<th class="bg-nxm-surface-alt text-right font-semibold" scope="col"> {{ __('legacy/topten.col_ratio')}} </th>
<th class="bg-nxm-surface-alt text-left font-semibold" scope="col"> {{ __('legacy/topten.col_joined')}} </th>
</tr>
@foreach ($rows as $a)
<tr><td class="text-center">{{ $loop->iteration }}</td><td><x-topten.user-link :id="$a['userid'] ?? 0" /></td><td class="text-right">{{ \App\Support\Format::size((float) ($a['uploaded'] ?? 0)) }}</td><td class="text-right">{{ \App\Support\Format::size((float) ($a['upspeed'] ?? 0)) }}/s</td><td class="text-right">{{ \App\Support\Format::size((float) ($a['downloaded'] ?? 0)) }}</td><td class="text-right">{{ \App\Support\Format::size((float) ($a['downspeed'] ?? 0)) }}/s</td><td class="text-right"><x-topten.ratio :up="$a['uploaded'] ?? 0" :down="$a['downloaded'] ?? 0" :infinite="__('legacy/topten.text_inf')" /></td><td><x-time :value="$a['added'] ?? ''" /></td></tr>
@endforeach
</x-topten.frame>
