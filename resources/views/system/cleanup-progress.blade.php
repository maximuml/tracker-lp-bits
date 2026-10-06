<html><head><title>Do Clean-up</title></head><body>
<p>clean-up in progress...please wait<br />
@if (! $forceAll)
<form method="post" action="/docleanup">@csrf<input type="hidden" name="forceall" value="1" />If you need to force a complete cleaning, click <button type="submit">here</button></form><br />
@endif
</p>
{{ $progress }}
<p>Time consumed：{{ $elapsed }} sec<br /></p>
Done<br />
</body></html>
