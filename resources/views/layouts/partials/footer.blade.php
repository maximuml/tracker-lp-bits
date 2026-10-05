</main>

<footer class="nxm-footer" role="contentinfo">
    <nav class="nxm-footer__links" aria-label="{{ __('legacy/functions.text_footer_nav') }}">
        <a href="rules.php">{{ __('legacy/functions.text_rules') }}</a>
        <a href="faq.php">{{ __('legacy/faq.head_faq') }}</a>
        <a href="staff.php">{{ __('legacy/functions.text_staff') }}</a>
        <a href="donate.php">{{ 'Donate' }}</a>
    </nav>
    <span class="nxm-footer__copy">(c) <a href="{{ $chrome->baseUrl }}">{{ $chrome->siteName }}</a>
        {{ $chrome->footer->icpLicense !== '' ? $chrome->footer->icpLicense.' ' : '' }}{{ $chrome->footer->yearFounded != date('Y') ? $chrome->footer->yearFounded.'-' : '' }}{{ date('Y') }} {{ $chrome->footer->versionHtml }}</span>
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
{{-- livewire.js carries the bundled Alpine: x-data works on any element
     once it boots; auto-injection stays off on pages with no components. --}}
@livewireScripts
</body></html>
