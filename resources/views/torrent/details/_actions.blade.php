<span class="nx-det-actions">@foreach ($details->actions as $action)
<a class="nx-postbtn" href="{{ $action->url }}"@if ($action->title !== '') title="{{ $action->title }}"@endif
@if ($action->spanId !== null) id="{{ $action->spanId }}"@endif
@if ($action->dataTorrentId !== null) data-torrent_id="{{ $action->dataTorrentId }}"@endif>@if ($action->iconHtml !== null){{ $action->iconHtml }}&nbsp;@endif{{ $action->label }}</a>
@endforeach</span>
