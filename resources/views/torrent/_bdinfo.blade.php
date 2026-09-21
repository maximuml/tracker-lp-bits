@if (! $vm->rawOnly)
    @foreach ($vm->discs as $disc)
        @if ($disc->heading !== null)
            <h4>{{ $disc->heading }}</h4>
        @endif
        <table data-nx="data" role="presentation"><tbody><tr>
            @foreach ([$disc->videos, $disc->audios, $disc->subtitles] as $column)
                @if ($column !== null)
                    <td><table data-nx="data"><caption class="nx-sr-only">{{ $disc->heading ?? 'Media info' }}</caption><tbody>
                        @foreach ($column->visibleRows as $key => $value)
                            <tr><td><b>{{ $key }}: </b>{{ $value }}</td></tr>
                        @endforeach
                        @if ($column->hiddenSpoiler !== null)
                            <tr><td>{{ $column->hiddenSpoiler }}</td></tr>
                        @endif
                    </tbody></table></td>
                @endif
            @endforeach
        </tr></tbody></table>
        @if ($disc->trailingHr)
            <hr>
        @endif
    @endforeach
@endif
<div class="nexus-media-info-raw">{{ $vm->rawSpoiler }}</div>
