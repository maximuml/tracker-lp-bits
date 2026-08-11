@extends('layouts.legacy')

@section('title', 'Stats')

@section('content')
@php
$__server_PHP_SELF = \App\Support\SupportContext::getServerValue('PHP_SELF');
if (\App\Support\UserDisplay::currentClass() < UC_MODERATOR) {
    \App\Support\LegacyResponse::abort('Error', 'Permission denied.');
}
$n_tor = \Nexus\Database\NexusDB::table('torrents')->count();
$n_peers = \Nexus\Database\NexusDB::table('peers')->count();
$uporder = \App\Support\SupportContext::getQuery('uporder') ?? '';
$catorder = \App\Support\SupportContext::getQuery('catorder') ?? '';

if ($uporder == 'lastul') {
    $orderby = 'last DESC, name';
} elseif ($uporder == 'torrents') {
    $orderby = 'n_t DESC, name';
} elseif ($uporder == 'peers') {
    $orderby = 'n_p DESC, name';
} else {
    $orderby = 'name';
}

$uploaderQueryBase = \Nexus\Database\NexusDB::table('users as u')
    ->selectRaw('u.id, u.username AS name, MAX(t.added) AS last, COUNT(DISTINCT t.id) AS n_t, COUNT(p.id) AS n_p')
    ->leftJoin('torrents as t', 'u.id', '=', 't.owner')
    ->leftJoin('peers as p', 't.id', '=', 'p.torrent');
$first = clone $uploaderQueryBase;
$first->where('u.class', 3)->groupBy('u.id');
$second = clone $uploaderQueryBase;
$second->where('u.class', '>', 3)->groupBy('u.id');
$upers = $first->union($second)->orderByRaw($orderby)->get();
@endphp

<STYLE TYPE="text/css" MEDIA=screen>
  a.colheadlink:link, a.colheadlink:visited{
    font-weight: bold;
    color: #FFFFFF;
    text-decoration: none;
}

a.colheadlink:hover {
    text-decoration: underline;
}
</STYLE>

@if ($upers->isEmpty())
    @php \App\Support\Html::stdMessage('Sorry...', 'No uploaders.'); @endphp
@else
    @php \App\Support\Html::beginFrame('Uploader Activity', true); @endphp
    @php \App\Support\Html::beginTable(); @endphp
    <tr>
        <td class="colhead"><a href="{{ $__server_PHP_SELF }}?uporder=uploader&catorder={{ $catorder }}" class="colheadlink">Uploader</a></td>
        <td class="colhead"><a href="{{ $__server_PHP_SELF }}?uporder=lastul&catorder={{ $catorder }}" class="colheadlink">Last Upload</a></td>
        <td class="colhead"><a href="{{ $__server_PHP_SELF }}?uporder=torrents&catorder={{ $catorder }}" class="colheadlink">Torrents</a></td>
        <td class="colhead">Perc.</td>
        <td class="colhead"><a href="{{ $__server_PHP_SELF }}?uporder=peers&catorder={{ $catorder }}" class="colheadlink">Peers</a></td>
        <td class="colhead">Perc.</td>
    </tr>
    @foreach ($upers as $uper)
        @php
        $uper = (array) $uper;
        $lastCell = $uper['last'] ? $uper['last'] . ' (' . \App\Support\Format::getElapsedTime(strtotime($uper['last'])) . ' ago)' : '---';
        $nT = $uper['n_t'];
        $nP = $uper['n_p'];
        $percT = $n_tor > 0 ? number_format(100 * $nT / $n_tor, 1) . '%' : '---';
        $percP = $n_peers > 0 ? number_format(100 * $nP / $n_peers, 1) . '%' : '---';
        @endphp
        <tr>
            <td>{{ \App\Support\UserDisplay::username($uper['id']) }}</td>
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

@php
if ($n_tor == 0) {
    $showCategories = false;
} else {
    $showCategories = true;
    if ($catorder == 'lastul') {
        $orderby = 'last DESC, c.name';
    } elseif ($catorder == 'torrents') {
        $orderby = 'n_t DESC, c.name';
    } elseif ($catorder == 'peers') {
        $orderby = 'n_p DESC, name';
    } else {
        $orderby = 'c.name';
    }
    $cats = \Nexus\Database\NexusDB::table('categories as c')
        ->selectRaw('c.name, MAX(t.added) AS last, COUNT(DISTINCT t.id) AS n_t, COUNT(p.id) AS n_p')
        ->leftJoin('torrents as t', 't.category', '=', 'c.id')
        ->leftJoin('peers as p', 't.id', '=', 'p.torrent')
        ->groupBy('c.id')
        ->orderByRaw($orderby)
        ->get();
}
@endphp

@if (! $showCategories)
    @php \App\Support\Html::stdMessage('Sorry...', 'No categories defined!'); @endphp
@else
    @php \App\Support\Html::beginFrame('Category Activity', true); @endphp
    @php \App\Support\Html::beginTable(); @endphp
    <tr>
        <td class="colhead"><a href="{{ $__server_PHP_SELF }}?uporder={{ $uporder }}&catorder=category" class="colheadlink">Category</a></td>
        <td class="colhead"><a href="{{ $__server_PHP_SELF }}?uporder={{ $uporder }}&catorder=lastul" class="colheadlink">Last Upload</a></td>
        <td class="colhead"><a href="{{ $__server_PHP_SELF }}?uporder={{ $uporder }}&catorder=torrents" class="colheadlink">Torrents</a></td>
        <td class="colhead">Perc.</td>
        <td class="colhead"><a href="{{ $__server_PHP_SELF }}?uporder={{ $uporder }}&catorder=peers" class="colheadlink">Peers</a></td>
        <td class="colhead">Perc.</td>
    </tr>
    @foreach ($cats as $cat)
        @php
        $cat = (array) $cat;
        $lastCell = $cat['last'] ? $cat['last'] . ' (' . \App\Support\Format::getElapsedTime(strtotime($cat['last'])) . ' ago)' : '---';
        $nT = $cat['n_t'];
        $nP = $cat['n_p'];
        $percT = $n_tor > 0 ? number_format(100 * $nT / $n_tor, 1) . '%' : '---';
        $percP = $n_peers > 0 ? number_format(100 * $nP / $n_peers, 1) . '%' : '---';
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
