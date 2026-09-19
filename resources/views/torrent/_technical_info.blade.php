@if ($vm->rawOnly)
<div class="nexus-media-info-raw"><pre>{{ $vm->rawSpoiler }}</pre></div>
@else
<div class="nti-wrap">
    <div class="nti-grid">
        <div class="nti-col">
            @if ($vm->hasGeneral)
                <h4>{{ $vm->generalTitle }}</h4>
                @include('torrent._nti_kv', ['items' => $vm->generalMain])
                @if ($vm->generalExtraSpoiler !== null)
                    {{ $vm->generalExtraSpoiler }}
                @endif
            @endif
        </div>
        <div class="nti-col">
            @if ($vm->hasVideo)
                <h4>{{ $vm->videoTitle }}</h4>
                @include('torrent._nti_kv', ['items' => $vm->videosMain])
                @if ($vm->encodingSpoiler !== null)
                    {{ $vm->encodingSpoiler }}
                @endif
            @endif
        </div>
        <div class="nti-col">
            @if ($vm->hasAudio)
                <h4>{{ $vm->audioTitle }}</h4>
                @foreach ($vm->audioTracks as $track)
                    @include('torrent._nti_track', ['track' => $track])
                @endforeach
                @if ($vm->hiddenAudioSpoiler !== null)
                    {{ $vm->hiddenAudioSpoiler }}
                @endif
            @endif
        </div>
    </div>
    <div class="nti-raw nexus-media-info-raw">{{ $vm->rawSpoiler }}</div>
</div>
@endif
