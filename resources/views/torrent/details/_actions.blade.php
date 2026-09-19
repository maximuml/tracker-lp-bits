@foreach ($details->actions as $action)
@if (! $loop->first)&nbsp;|&nbsp;@endif
<a href="{{ $action->url }}"@if ($action->title !== '') title="{{ $action->title }}"@endif>@if ($action->iconClass !== '')<img class="{{ $action->iconClass }}" src="pic/trans.gif" alt="{{ $action->iconAlt }}" />&nbsp;@endif<b><span class="{{ $action->spanClass }}"@if ($action->spanId !== null) id="{{ $action->spanId }}"@endif
@if ($action->dataTorrentId !== null) data-torrent_id="{{ $action->dataTorrentId }}"@endif>@if ($action->iconHtml !== null){{ $action->iconHtml }}&nbsp;@endif{{ $action->label }}</span></b></a>
@endforeach
