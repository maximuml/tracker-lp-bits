<html><head><title>{{ __('cleanup.do_cleanup') }}</title></head><body>
<p>{{ __('cleanup.in_progress') }}<br />
@if (! $forceAll)
If you need to force a complete cleaning, click <form method="post" action="/web/system/cleanup?forceall=1" class="nx-inline">@csrf<button type="submit" class="nxm-linkbtn">here</button></form><br />
@endif
</p>
{{ $progress }}
<p>Time consumed：{{ $elapsed }} sec<br /></p>
Done<br />
</body></html>
