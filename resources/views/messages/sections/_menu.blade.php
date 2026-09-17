<div id="pmboxnav"><ul id="pmboxmenu" class="menu">
<li{{ $selected === 1 ? ' class=selected' : '' }}><a href="{{ \App\Support\Http::protocolPrefix(\App\Support\Url::isSecure()) }}{{ $baseUrl }}/messages.php" >{{ __('legacy/messages.text_inbox') }}</a></li>
<li{{ $selected === -1 ? ' class=selected' : '' }}><a href="{{ \App\Support\Http::protocolPrefix(\App\Support\Url::isSecure()) }}{{ $baseUrl }}/messages.php?action=viewmailbox&box=-1">{{ __('legacy/messages.text_sentbox') }}</a></li>
@foreach ($mailboxes as $row)
<li{{ $selected === (int) $row->boxnumber ? ' class=selected' : '' }}><a href="{{ \App\Support\Http::protocolPrefix(\App\Support\Url::isSecure()) }}{{ $baseUrl }}/messages.php?action=viewmailbox&box={{ (int) $row->boxnumber }}">{{ $row->name }}</a></li>
@endforeach
</ul></div>
