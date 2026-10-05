@if (! $vm->rawOnly)
    @foreach ($vm->discs as $disc)
        @if ($disc->heading !== null)
            <h4>{{ $disc->heading }}</h4>
        @endif
        <x-data-table role="presentation"><tr>
            @foreach ([$disc->videos, $disc->audios, $disc->subtitles] as $column)
                @if ($column !== null)
                    <td><x-data-table :caption="$disc->heading ?? 'Media info'" captionHidden>
                        @foreach ($column->visibleRows as $key => $value)
                            <tr><td><b>{{ $key }}: </b>{{ $value }}</td></tr>
                        @endforeach
                        @if ($column->hiddenSpoiler !== null)
                            <tr><td>{{ $column->hiddenSpoiler }}</td></tr>
                        @endif
                    </x-data-table></td>
                @endif
            @endforeach
        </tr></x-data-table>
        @if ($disc->trailingHr)
            <hr>
        @endif
    @endforeach
@endif
<div class="nexus-media-info-raw">{{ $vm->rawSpoiler }}</div>
