@if($topUploaders->show)
<section class="nx-idx-card">
<h2>{{ $topUploaders->title }}</h2>
@if($topUploaders->recentRows === [] && $topUploaders->allRows === [])
<x-empty-state :title="__('legacy/index.text_no_uploaders')" />
@else
<div class='nx-row tr-top-uploader-tab' title='{{ $topUploaders->toggleHint }}'><div class='nx-colhead nx-center nx-grow' data-table='top-uploader-recently'>{{ $topUploaders->recentlyLabel }}</div><div class='nx-center nx-grow' data-table='top-uploader-all'>{{ $topUploaders->allLabel }}</div></div>

<table data-nx="data" class='top-uploader top-uploader-all nx-hidden'><caption class="nx-sr-only">{{ $topUploaders->title }} — {{ $topUploaders->allLabel }}</caption><tr><th class="colhead" scope="col">{{ $topUploaders->colAuthor }}</th><th class="colhead" scope="col">{{ $topUploaders->colCounts }}</th><th class="colhead" scope="col">{{ $topUploaders->colRanking }}</th></tr>
@foreach($topUploaders->allRows as $row)
<tr><td>{{ $row->username }}</td><td class="nx-center">{{ $row->count }}</td><td class="nx-center">{{ $row->rank }}</td></tr>
@endforeach
</table>

<table data-nx="data" class='top-uploader top-uploader-recently'><caption class="nx-sr-only">{{ $topUploaders->title }} — {{ $topUploaders->recentlyLabel }}</caption><tr><th class="colhead" scope="col">{{ $topUploaders->colAuthor }}</th><th class="colhead" scope="col">{{ $topUploaders->colCounts }}</th><th class="colhead" scope="col">{{ $topUploaders->colRanking }}</th></tr>
@foreach($topUploaders->recentRows as $row)
<tr><td>{{ $row->username }}</td><td class="nx-center">{{ $row->count }}</td><td class="nx-center">{{ $row->rank }}</td></tr>
@endforeach
</table>
@endif
</section>
@endif
