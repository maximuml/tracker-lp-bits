@isset($CURUSER)
<style nonce="{{ $cspNonce ?? '' }}">
.fileicon { display:inline-block; box-sizing:border-box; min-width:38px; padding:1px 5px; margin-right:6px; border-radius:3px; font:10px/1.4 ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-weight:bold; letter-spacing:.3px; color:#fff; text-align:center; vertical-align:1px; text-transform:uppercase; }
.fileicon.fi-video    { background:#3498db; }
.fileicon.fi-audio    { background:#27ae60; }
.fileicon.fi-image    { background:#9b59b6; }
.fileicon.fi-subtitle { background:#e84393; }
.fileicon.fi-archive  { background:#e67e22; }
.fileicon.fi-iso      { background:#34495e; }
.fileicon.fi-document { background:#c0392b; }
.fileicon.fi-text     { background:#7f8c8d; }
.fileicon.fi-nfo      { background:#16a085; }
.fileicon.fi-code     { background:#2c3e50; }
.fileicon.fi-exec     { background:#d35400; }
.fileicon.fi-torrent  { background:#8e44ad; }
.fileicon.fi-other    { background:#95a5a6; }
</style>
<table data-nx="data" class="main" border="1" cellspacing=0 cellpadding="5">
<tr><td class=colhead>{{ __('legacy/viewfilelist.col_path') }}</td><td class=colhead align=center><img class="size" src="pic/trans.gif" alt="size" /></td></tr>
@foreach ($files as $file)
<tr><td class=rowfollow>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($file['badgeHtml'])){{ $file['filename'] }}</td><td class=rowfollow align="right">{{ $file['size'] }}</td></tr>
@endforeach
</table>
@endisset
