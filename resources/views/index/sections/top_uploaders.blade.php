@if($topUploaders->show)
<section class="nx-idx-card">
<h2>{{ $topUploaders->title }}</h2>
@if($topUploaders->recentRows === [] && $topUploaders->allRows === [])
<x-empty-state :title="__('index.text_no_uploaders')" />
@else
<div class="tr-top-uploader-tab flex" title='{{ $topUploaders->toggleHint }}'><div class="nx-colhead grow text-center" data-table='top-uploader-recently'>{{ $topUploaders->recentlyLabel }}</div><div class="grow text-center" data-table='top-uploader-all'>{{ $topUploaders->allLabel }}</div></div>

<x-data-table :headers="[$topUploaders->colAuthor, $topUploaders->colCounts, $topUploaders->colRanking]" :caption="$topUploaders->title . ' — ' . $topUploaders->allLabel" captionHidden class='top-uploader top-uploader-all nx-hidden'>
@foreach($topUploaders->allRows as $row)
<tr><td>{{ $row->username }}</td><td class="text-center">{{ $row->count }}</td><td class="text-center">{{ $row->rank }}</td></tr>
@endforeach
</x-data-table>

<x-data-table :headers="[$topUploaders->colAuthor, $topUploaders->colCounts, $topUploaders->colRanking]" :caption="$topUploaders->title . ' — ' . $topUploaders->recentlyLabel" captionHidden class='top-uploader top-uploader-recently'>
@foreach($topUploaders->recentRows as $row)
<tr><td>{{ $row->username }}</td><td class="text-center">{{ $row->count }}</td><td class="text-center">{{ $row->rank }}</td></tr>
@endforeach
</x-data-table>
@endif
</section>
@endif
