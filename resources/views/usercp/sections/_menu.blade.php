@php
/** @var string $selected */
$lang_usercp = (array) (\App\Support\SupportContext::getGlobal('lang_usercp') ?? []);
@endphp
<div id="usercpnav"><ul id="usercpmenu" class="menu">
<li{{ $selected === 'home' ? ' class=selected' : '' }}><a href="usercp.php">{{ $lang_usercp['text_user_cp_home'] ?? 'Home' }}</a></li>
<li{{ $selected === 'personal' ? ' class=selected' : '' }}><a href="?action=personal">{{ $lang_usercp['text_personal_settings'] ?? 'Personal' }}</a></li>
<li{{ $selected === 'tracker' ? ' class=selected' : '' }}><a href="?action=tracker">{{ $lang_usercp['text_tracker_settings'] ?? 'Tracker' }}</a></li>
<li{{ $selected === 'forum' ? ' class=selected' : '' }}><a href="?action=forum">{{ $lang_usercp['text_forum_settings'] ?? 'Forum' }}</a></li>
<li{{ $selected === 'security' ? ' class=selected' : '' }}><a href="?action=security">{{ $lang_usercp['text_security_settings'] ?? 'Security' }}</a></li>
</ul></div>
