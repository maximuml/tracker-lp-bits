@extends('layouts.legacy')

@section('title', 'Stats')

@section('content')
<style type="text/css" media="screen" nonce="{{ $cspNonce ?? '' }}">
  a.colheadlink:link, a.colheadlink:visited{
    font-weight: bold;
    color: #FFFFFF;
    text-decoration: none;
}

a.colheadlink:hover {
    text-decoration: underline;
}
</style>

@php
$nTor = (int) $n_tor;
$nPeers = (int) $n_peers;
@endphp

@if ($upers->isEmpty())
    @php \App\Support\Html::stdMessage('Sorry...', 'No uploaders.'); @endphp
@else
    @php \App\Support\Html::beginFrame('Uploader Activity', true); @endphp
    @php \App\Support\Html::beginTable(); @endphp
    <tr>
        <td class="colhead"><a href="{{ $php_self }}?uporder=uploader&catorder={{ $catorder }}" class="colheadlink">Uploader</a></td>
        <td class="colhead"><a href="{{ $php_self }}?uporder=lastul&catorder={{ $catorder }}" class="colheadlink">Last Upload</a></td>
        <td class="colhead"><a href="{{ $php_self }}?uporder=torrents&catorder={{ $catorder }}" class="colheadlink">Torrents</a></td>
        <td class="colhead">Perc.</td>
        <td class="colhead"><a href="{{ $php_self }}?uporder=peers&catorder={{ $catorder }}" class="colheadlink">Peers</a></td>
        <td class="colhead">Perc.</td>
    </tr>
    @foreach ($upers as $uper)
        @php
        $uper = (array) $uper;
        $lastCell = $uper['last'] ? $uper['last'] . ' (' . \App\Support\Format::getElapsedTime((int) strtotime($uper['last'])) . ' ago)' : '---';
        $nT = (int) $uper['n_t'];
        $nP = (int) $uper['n_p'];
        $percT = $nTor > 0 ? number_format(100 * $nT / $nTor, 1) . '%' : '---';
        $percP = $nPeers > 0 ? number_format(100 * $nP / $nPeers, 1) . '%' : '---';
        @endphp
        <tr>
            <td>{!! \App\Support\UserDisplay::username($uper['id']) !!}</td>
            <td>{{ $lastCell }}</td>
            <td align="right">{{ $nT }}</td>
            <td align="right">{{ $percT }}</td>
            <td align="right">{{ $nP }}</td>
            <td align="right">{{ $percP }}</td>
        </tr>
    @endforeach
    @php \App\Support\Html::endTable(); @endphp
    @php \App\Support\Html::endFrame(); @endphp
@endif

@if ($cats->isEmpty())
    @php \App\Support\Html::stdMessage('Sorry...', 'No categories defined!'); @endphp
@else
    @php \App\Support\Html::beginFrame('Category Activity', true); @endphp
    @php \App\Support\Html::beginTable(); @endphp
    <tr>
        <td class="colhead"><a href="{{ $php_self }}?uporder={{ $uporder }}&catorder=category" class="colheadlink">Category</a></td>
        <td class="colhead"><a href="{{ $php_self }}?uporder={{ $uporder }}&catorder=lastul" class="colheadlink">Last Upload</a></td>
        <td class="colhead"><a href="{{ $php_self }}?uporder={{ $uporder }}&catorder=torrents" class="colheadlink">Torrents</a></td>
        <td class="colhead">Perc.</td>
        <td class="colhead"><a href="{{ $php_self }}?uporder={{ $uporder }}&catorder=peers" class="colheadlink">Peers</a></td>
        <td class="colhead">Perc.</td>
    </tr>
    @foreach ($cats as $cat)
        @php
        $cat = (array) $cat;
        $lastCell = $cat['last'] ? $cat['last'] . ' (' . \App\Support\Format::getElapsedTime((int) strtotime($cat['last'])) . ' ago)' : '---';
        $nT = (int) $cat['n_t'];
        $nP = (int) $cat['n_p'];
        $percT = $nTor > 0 ? number_format(100 * $nT / $nTor, 1) . '%' : '---';
        $percP = $nPeers > 0 ? number_format(100 * $nP / $nPeers, 1) . '%' : '---';
        @endphp
        <tr>
            <td class="rowhead">{{ $cat['name'] }}</td>
            <td>{{ $lastCell }}</td>
            <td align="right">{{ $nT }}</td>
            <td align="right">{{ $percT }}</td>
            <td align="right">{{ $nP }}</td>
            <td align="right">{{ $percP }}</td>
        </tr>
    @endforeach
    @php \App\Support\Html::endTable(); @endphp
    @php \App\Support\Html::endFrame(); @endphp
@endif
@endsection
