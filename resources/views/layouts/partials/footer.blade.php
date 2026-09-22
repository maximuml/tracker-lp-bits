</main>

<footer class="nxm-footer" role="contentinfo">
    <span>(c) <a href="{{ $chrome->baseUrl }}">{{ $chrome->siteName }}</a>
        {{ $chrome->footer->icpLicense !== '' ? $chrome->footer->icpLicense.' ' : '' }}{{ $chrome->footer->yearFounded != date('Y') ? $chrome->footer->yearFounded.'-' : '' }}{{ date('Y') }} {{ $chrome->footer->versionHtml }}</span>
    <div class="nxm-footer__stats">[page created in <b>{{ $chrome->footer->statsTime }}</b> sec with <b>{{ $chrome->footer->statsDbQueries }}</b> db queries, <b>{{ $chrome->footer->statsCacheReads }}</b> reads and <b>{{ $chrome->footer->statsCacheWrites }}</b> writes of Redis and <b>{{ $chrome->footer->statsRam }}</b> ram]</div>
    @if($chrome->footer->debugEnabled)
    <div id="sql_debug">SQL query list: <ul>
        @foreach($chrome->footer->debugQueries as $query)
        <li>{{ $query['query'] }} [{{ $query['time'] }}]</li>
        @endforeach
        @foreach($chrome->footer->debugLaravelQueries as $query)
        <li>{{ $query['query'] }} [{{ $query['time'] }} ms]</li>
        @endforeach
    </ul>
    Redis key read: <ul>
        @foreach($chrome->footer->debugRedisReads as $keyName => $hits)
        <li>{{ $keyName }} : {{ $hits }}</li>
        @endforeach
    </ul>
    Redis key write: <ul>
        @foreach($chrome->footer->debugRedisWrites as $keyName => $hits)
        <li>{{ $keyName }} : {{ $hits }}</li>
        @endforeach
    </ul>
    </div>
    @endif
    {{ $chrome->footer->keyShortcutHtml }}
    {{ $chrome->footer->analyticsHtml }}
</footer>

<img id="nexus-preview" alt="" role="presentation" class="nx-hidden" src="" />
@foreach($chrome->footer->footScripts as $src)
<script type="text/javascript" src="{{ $src }}"></script>
@endforeach
@foreach (\App\Support\AssetAppender::getAppendFootersSafe() as $html)
{{ $html }}
@endforeach
</body></html>
