</main>

<footer class="nxm-footer" role="contentinfo">
    <span>(c) <a href="{{ $chrome->baseUrl }}">{{ $chrome->siteName }}</a>
        {{ $chrome->icpLicense !== '' ? $chrome->icpLicense.' ' : '' }}{{ $chrome->yearFounded != date('Y') ? $chrome->yearFounded.'-' : '' }}{{ date('Y') }} {{ $chrome->versionHtml }}</span>
    <div class="nxm-footer__stats">[page created in <b>{{ $chrome->statsTime }}</b> sec with <b>{{ $chrome->statsDbQueries }}</b> db queries, <b>{{ $chrome->statsCacheReads }}</b> reads and <b>{{ $chrome->statsCacheWrites }}</b> writes of Redis and <b>{{ $chrome->statsRam }}</b> ram]</div>
    @if($chrome->debugEnabled)
    <div id="sql_debug" style="text-align: left;">SQL query list: <ul>
        @foreach($chrome->debugQueries as $query)
        <li>{{ $query['query'] }} [{{ $query['time'] }}]</li>
        @endforeach
        @foreach($chrome->debugLaravelQueries as $query)
        <li>{{ $query['query'] }} [{{ $query['time'] }} ms]</li>
        @endforeach
    </ul>
    Redis key read: <ul>
        @foreach($chrome->debugRedisReads as $keyName => $hits)
        <li>{{ $keyName }} : {{ $hits }}</li>
        @endforeach
    </ul>
    Redis key write: <ul>
        @foreach($chrome->debugRedisWrites as $keyName => $hits)
        <li>{{ $keyName }} : {{ $hits }}</li>
        @endforeach
    </ul>
    </div>
    @endif
    {{ $chrome->keyShortcutHtml }}
    {{ $chrome->analyticsHtml }}
</footer>

<img id="nexus-preview" alt="" role="presentation" class="nx-hidden" style="position: absolute" src="" />
@foreach($chrome->footScripts as $src)
<script type="text/javascript" src="{{ $src }}"></script>
@endforeach
@if($chrome->cspNonce !== '')
<script type="text/javascript" nonce="{{ $chrome->cspNonce }}">
@else
<script type="text/javascript">
@endif
document.addEventListener('DOMContentLoaded', function(){
    mediumZoom('[data-zoomable]')
});
</script>
@foreach (\App\Support\AssetAppender::getAppendFootersSafe() as $html)
{{ $html }}
@endforeach
</body></html>
