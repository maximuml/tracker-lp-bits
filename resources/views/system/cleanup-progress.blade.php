<html><head><title>Do Clean-up</title></head><body>
<p>clean-up in progress...please wait<br />
@if (! $forceAll)
If you need to force a complete cleaning, click <a href="docleanup.php?forceall=1">here</a><br />
@endif
</p>
{{ $progress }}
<p>Time consumed：{{ $elapsed }} sec<br /></p>
Done<br />
</body></html>
