<div class="nti-track">
    <div class="nti-track-head">{{ $track->head }}@foreach ($track->badges as $badge)<span class="nti-badge">{{ $badge }}</span>@endforeach</div>
    @include('torrent._nti_kv', ['items' => $track->rows])
</div>
