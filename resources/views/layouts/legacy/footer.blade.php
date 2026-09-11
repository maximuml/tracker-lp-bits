</td></tr></table>
<div id="footer">
<div style="margin-top: 10px; margin-bottom: 30px;" align="center">
{!! $copyrightHtml !!}{!! $pageStatsLine !!}
</div>
@if($debugQuery)
{!! $debugQueryHtml !!}
@endif
{!! $keyShortcut !!}</div>
{!! $analyticsCode !!}
@foreach($appendFooters as $value)
{!! $value !!}
@endforeach
{!! $jsBlock !!}
<img id="nexus-preview" alt="" role="presentation" class="nx-hidden" style="position: absolute" src="" />
</body></html>
