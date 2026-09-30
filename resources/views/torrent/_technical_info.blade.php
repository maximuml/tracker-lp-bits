@if ($vm->rawOnly)
<div class="nexus-media-info-raw"><pre>{{ $vm->rawSpoiler }}</pre></div>
@else
<div class="nti-wrap">
    <div class="nti-grid">
        <div class="nti-col">
            @if ($vm->hasGeneral)
                <p class="nti-col__title">{{ $vm->generalTitle }}</p>
                @include('torrent._nti_kv', ['items' => $vm->generalMain])
                @if ($vm->generalExtraSpoiler !== null)
                    {{ $vm->generalExtraSpoiler }}
                @endif
            @endif
        </div>
        <div class="nti-col">
            @if ($vm->hasVideo)
                <p class="nti-col__title">{{ $vm->videoTitle }}</p>
                @include('torrent._nti_kv', ['items' => $vm->videosMain])
                @if ($vm->encodingSpoiler !== null)
                    {{ $vm->encodingSpoiler }}
                @endif
            @endif
        </div>
        <div class="nti-col">
            @if ($vm->hasAudio)
                <p class="nti-col__title">{{ $vm->audioTitle }}</p>
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
