@php
/** @var int $selected */
$lang_messages = (array) (\App\Support\SupportContext::getGlobal('lang_messages') ?? []);
$BASEURL = (string) (\App\Support\SupportContext::getGlobal('BASEURL', ''));
$CURUSER = (array) (\App\Support\SupportContext::getUser() ?? []);
$pmBoxes = \App\Repositories\MessageRepository::getUserMailboxes((int) ($CURUSER['id'] ?? 0));
@endphp
<div id="pmboxnav"><ul id="pmboxmenu" class="menu">
<li{{ $selected === 1 ? ' class=selected' : '' }}><a href="{{ \App\Support\Http::protocolPrefix(\App\Support\Url::isSecure()) }}{{ $BASEURL }}/messages.php" >{{ $lang_messages['text_inbox'] ?? 'Inbox' }}</a></li>
<li{{ $selected === -1 ? ' class=selected' : '' }}><a href="{{ \App\Support\Http::protocolPrefix(\App\Support\Url::isSecure()) }}{{ $BASEURL }}/messages.php?action=viewmailbox&box=-1">{{ $lang_messages['text_sentbox'] ?? 'Sentbox' }}</a></li>
@foreach ($pmBoxes as $row)
@php $rowArr = (array) $row; @endphp
<li{{ $selected === (int) $rowArr['boxnumber'] ? ' class=selected' : '' }}><a href="{{ \App\Support\Http::protocolPrefix(\App\Support\Url::isSecure()) }}{{ $BASEURL }}/messages.php?action=viewmailbox&box={{ (int) $rowArr['boxnumber'] }}">{{ htmlspecialchars((string) $rowArr['name']) }}</a></li>
@endforeach
</ul></div>
