@props(['caption' => '', 'center' => true, 'captionAlign' => 'left'])
@if ((string) $caption !== '')<h2 align="{{ $captionAlign }}">{{ $caption }}</h2>@endif
<table width="100%" border="1" cellspacing="0" cellpadding="10"><tr><td class="text" @if ($center) align="center"@endif>
{{ $slot }}</td></tr></table>
