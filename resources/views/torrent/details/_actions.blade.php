<span class="nx-det-actions">@foreach ($details->actions as $action)
@if ($action->isPost)
<form method="post" action="{{ $action->url }}" class="nx-inline">@csrf
@foreach ($action->postFields as $name => $value)<input type="hidden" name="{{ $name }}" value="{{ $value }}">@endforeach<button type="submit" class="nx-postbtn"@if ($action->title !== '') title="{{ $action->title }}"@endif
@if ($action->spanId !== null) id="{{ $action->spanId }}"@endif>@if ($action->iconHtml !== null){{ $action->iconHtml }}&nbsp;@endif{{ $action->label }}</button></form>
@else
<a class="nx-postbtn" href="{{ $action->url }}"@if ($action->title !== '') title="{{ $action->title }}"@endif
@if ($action->spanId !== null) id="{{ $action->spanId }}"@endif
@if ($action->dataTorrentId !== null) data-torrent_id="{{ $action->dataTorrentId }}"@endif>@if ($action->iconHtml !== null){{ $action->iconHtml }}&nbsp;@endif{{ $action->label }}</a>
@endif
@endforeach</span>
