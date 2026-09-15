</div></div>
<div id="footer">
<div style="margin-top: 10px; margin-bottom: 30px;" align="center">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($copyrightHtml.$pageStatsLine))
</div>
@if($debugQuery)
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($debugQueryHtml))
@endif
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($keyShortcut))</div>
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($analyticsCode))
@foreach($appendFooters as $value)
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($value))
@endforeach
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($jsBlock))
<img id="nexus-preview" alt="" role="presentation" class="nx-hidden" style="position: absolute" src="" />
</body></html>
