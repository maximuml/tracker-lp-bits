@if($browserNote->show)
<section class="nx-idx-card">
<div class="nx-main nx-embedded">
<div class="text-center"><br /><span class="medium">{{ __('index.text_browser_note') }} <a href="https://www.google.com/chrome/" target="_blank" rel="noopener"><img class="img-browser" src="/pic/misc/chrome-logo.svg" alt="Google Chrome" title="Get Google Chrome" /></a> {{ __('index.text_or') }} <a href="https://www.mozilla.org/firefox/new/" target="_blank" rel="noopener"><img class="img-browser" src="/pic/misc/firefox-logo.svg" alt="Firefox" title="Get Firefox" /></a>. {{ __('index.text_browser_clients') }} <a href="https://www.qbittorrent.org/download" target="_blank" rel="noopener"><img class="img-browser" src="/pic/misc/qBittorrent.ico" alt="qBittorrent" title="Get qBittorrent" /></a> {{ __('index.text_or') }} <a href="https://transmissionbt.com/download" target="_blank" rel="noopener"><img class="img-browser" src="/pic/misc/transmission.png" alt="Transmission" title="Get Transmission" /></a></span></div>
</div>
</section>
@endif