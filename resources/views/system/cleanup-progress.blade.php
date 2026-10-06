<html><head><title>Do Clean-up</title></head><body>
<p>clean-up in progress...please wait<br />
@if (! $forceAll)
If you need to force a complete cleaning, click <form method="post" action="/web/system/cleanup?forceall=1" class="inline">@csrf<button type="submit" class="nxm-linkbtn">here</button></form><br />
@endif
</p>
{{ $progress }}
<p>Time consumed：{{ $elapsed }} sec<br /></p>
Done<br />
</body></html>
