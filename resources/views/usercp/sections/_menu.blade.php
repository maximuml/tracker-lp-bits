<div id="usercpnav"><ul id="usercpmenu" class="menu">
<li{{ $selected === 'home' ? ' class=selected' : '' }}><a href="usercp.php">{{ __('legacy/usercp.text_user_cp_home')}}</a></li>
<li{{ $selected === 'personal' ? ' class=selected' : '' }}><a href="?action=personal">{{ __('legacy/usercp.text_personal_settings')}}</a></li>
<li{{ $selected === 'tracker' ? ' class=selected' : '' }}><a href="?action=tracker">{{ __('legacy/usercp.text_tracker_settings')}}</a></li>
<li{{ $selected === 'forum' ? ' class=selected' : '' }}><a href="?action=forum">{{ __('legacy/usercp.text_forum_settings')}}</a></li>
<li{{ $selected === 'security' ? ' class=selected' : '' }}><a href="?action=security">{{ __('legacy/usercp.text_security_settings')}}</a></li>
</ul></div>
