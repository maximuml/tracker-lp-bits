<body data-chrome="{{ $chrome->variant }}">
<a href="#main-content" class="skip-link">{{ 'Skip to main content' }}</a>

<header class="nxm-header" role="banner">
    <div class="nxm-header__brand">
        @if($chrome->logoMain === '')
            <a class="nxm-logo" href="index.php">{{ $chrome->siteName }}</a>
            @if($chrome->slogan !== '')<span class="nxm-slogan">{{ $chrome->slogan }}</span>@endif
        @else
            <a class="nxm-logo" href="index.php"><img src="{{ $chrome->logoMain }}" alt="{{ $chrome->siteName }}" /></a>
        @endif
        @if($chrome->enableDonation)
            <a class="nxm-donate" href="donate.php"><img src="{{ $chrome->picFolder }}/donate.gif" alt="{{ 'Make a donation' }}" /></a>
        @endif
    </div>

    @if($chrome->user)
    <nav class="nxm-nav" aria-label="{{ 'Main navigation' }}">
        <ul class="nxm-nav__list">
            @foreach($chrome->navItems as $item)
            <li><a href="{{ $item['href'] }}" @if($item['selected']) aria-current="page" class="nxm-nav__link--active" @else class="nxm-nav__link" @endif>{{ $item['label'] }}</a></li>
            @endforeach
        </ul>
    </nav>

    <div class="nxm-userbar" role="group" aria-label="{{ 'Account' }}">
        <div class="nxm-userbar__row">
            <span class="nxm-userbar__greeting">{{ __('legacy/functions.text_welcome_back') }},</span>
            {{ $chrome->usernameHtml }}
            <span class="nxm-userbar__links">
                <form method="post" action="logout.php" class="nx-inline">@csrf<button type="submit" class="nxm-linkbtn">[{{ __('legacy/functions.text_logout') }}]</button></form>
                <a href="usercp.php">[{{ __('legacy/functions.text_user_cp') }}]</a>
                @if($chrome->isModerator)<a href="staffpanel.php">[{{ __('legacy/functions.text_staff_panel') }}]</a>@endif
                @if($chrome->isSysop)<a href="settings.php">[{{ __('legacy/functions.text_site_settings') }}]</a>@endif
                <a href="torrents.php?inclbookmarked=1&amp;allsec=1&amp;incldead=0">[{{ __('legacy/functions.text_bookmarks') }}]</a>
                <a href="mybonus.php">{{ __('legacy/functions.text_bonus') }}: {{ $chrome->seedbonus }}</a>
                @if($chrome->attendanceDone)
                <a href="attendance.php">{{ sprintf((string) __('legacy/functions.text_attended'), $chrome->attendancePoints, $chrome->attendanceCard) }}</a>
                @else
                <a href="attendance.php" class="faqlink">{{ __('legacy/functions.text_attendance') }}</a>
                @endif
                <a href="medal.php">[{{ $chrome->medalLabel }}]</a>
                <a href="task.php">[{{ $chrome->taskLabel }}]</a>
                <a href="invite.php?id={{ (int) $chrome->user['id'] }}">{{ __('legacy/functions.text_invite') }}: {{ $chrome->invites }}@if($chrome->pendingInvites > 0) ({{ $chrome->pendingInvites }})@endif</a>
                @if($chrome->managementHref !== '')<a href="{{ $chrome->managementHref }}" target="_blank" rel="noopener">[{{ __('legacy/functions.text_management_system') }}]</a>@endif
                <button type="button" class="nxm-linkbtn nxm-theme-toggle" data-persist-url="{{ url('/web/usercp/theme') }}" title="{{ 'Theme' }}: {{ ucfirst($chrome->theme) }}">[{{ 'Theme' }}: {{ ucfirst($chrome->theme) }}]</button>
            </span>
        </div>
        <div class="nxm-userbar__row">
            <span class="nxm-userbar__stats">
                <a href="messages.php" class="nxm-stat" aria-label="{{ __('legacy/functions.title_inbox') }}">
                    <span class="nxm-stat__label">{{ 'Messages' }}</span>
                    <span class="nxm-stat__value">{{ $chrome->inboxCount }}@if($chrome->unreadCount > 0) <b>({{ $chrome->unreadCount }} {{ __('legacy/functions.text_message_new') }})</b>@endif</span>
                </a>
                <span class="nxm-stat"><span class="nxm-stat__label">{{ __('legacy/functions.text_ratio') }}</span> <span class="nxm-stat__value">{{ $chrome->ratio }}</span></span>
                <span class="nxm-stat"><span class="nxm-stat__label">{{ __('legacy/functions.text_uploaded') }}</span> <span class="nxm-stat__value">{{ $chrome->uploaded }}</span></span>
                <span class="nxm-stat"><span class="nxm-stat__label">{{ __('legacy/functions.text_downloaded') }}</span> <span class="nxm-stat__value">{{ $chrome->downloaded }}</span></span>
                <span class="nxm-stat"><span class="nxm-stat__label">{{ __('legacy/functions.text_active_torrents') }}</span>
                    <span class="nxm-stat__value">
                        <span title="{{ __('legacy/functions.title_torrents_seeding') }}">&#x25B2;{{ $chrome->activeSeed }}</span>
                        <span title="{{ __('legacy/functions.title_torrents_leeching') }}">&#x25BC;{{ $chrome->activeLeech }}</span>
                    </span>
                </span>
                <span class="nxm-stat"><span class="nxm-stat__label">{{ __('legacy/functions.text_connectable') }}</span>
                    <span class="nxm-stat__value">
                        @if($chrome->connectable === true)<b class="nxm-ok">{{ __('legacy/functions.text_yes') }}</b>
                        @elseif($chrome->connectable === false)<a href="faq.php#id21"><b class="nxm-bad">{{ __('legacy/functions.text_no') }}</b></a>
                        @else{{ __('legacy/functions.text_unknown') }}@endif
                    </span>
                </span>
                <span class="nxm-stat"><span class="nxm-stat__label">{{ __('legacy/functions.text_slots') }}</span>
                    <span class="nxm-stat__value">
                        @if($chrome->maxSlots > 0)<a href="faq.php#id215">{{ $chrome->maxSlots }}</a>
                        @else{{ __('legacy/functions.text_unlimited') }}@endif
                    </span>
                </span>
                @if($chrome->hitAndRunEnabled)
                <span class="nxm-stat"><span class="nxm-stat__label">H&amp;R</span> <span class="nxm-stat__value">[<a href="myhr.php">{{ $chrome->hitAndRunStatsHtml }}</a>]</span></span>
                @endif
            </span>
        </div>
        <div class="nxm-userbar__row nxm-userbar__row--tools">
            @if($chrome->globalSearchEnabled)
            <form class="nxm-search" action="search.php" method="get" target="{{ $chrome->searchFormTarget }}">
                <input type="text" name="search" value="{{ $chrome->requestSearch }}" placeholder="{{ $chrome->searchKeywordPlaceholder }}" />
                <select name="search_area" aria-label="{{ __('search.search_area') }}">
                    @foreach($chrome->searchAreas as $area)
                    <option value="{{ $area['value'] }}"@if($area['selected']) selected @endif>{{ $area['label'] }}</option>
                    @endforeach
                </select>
                <input type="submit" value="{{ $chrome->globalSearchLabel }}" />
            </form>
            @endif
            <div class="nxm-userbar__icons">
                @if($chrome->canStaffmem)
                <a href="cheaterbox.php"><img class="cheaterbox" alt="cheaterbox" title="{{ __('legacy/functions.title_cheaterbox') }}" src="pic/trans.gif" /></a>{{ $chrome->cheaterCount }}
                <a href="reports.php"><img class="reportbox" alt="reportbox" title="{{ __('legacy/functions.title_reportbox') }}" src="pic/trans.gif" /></a>{{ $chrome->reportCount }}
                @endif
                <a href="friends.php"><img class="buddylist" alt="Buddylist" title="{{ __('legacy/functions.title_buddylist') }}" src="pic/trans.gif" /></a>
                <a href="getrss.php"><img class="rss" alt="RSS" title="{{ __('legacy/functions.title_get_rss') }}" src="pic/trans.gif" /></a>
                @if($chrome->staffMessageTotal > 0)
                <a href="staffbox.php"><img class="staffbox" alt="staffbox" title="{{ __('legacy/functions.title_staffbox') }}" src="pic/trans.gif" /></a>{{ $chrome->staffMessageTotal }}
                @endif
                <a href="messages.php"><img class="{{ $chrome->unreadCount > 0 ? 'inboxnew' : 'inbox' }}" alt="inbox" title="{{ $chrome->unreadCount > 0 ? __('legacy/functions.title_inbox_new_messages') : __('legacy/functions.title_inbox_no_new_messages') }}" src="pic/trans.gif" /></a>{{ $chrome->inboxCount }}@if($chrome->unreadCount > 0) ({{ $chrome->unreadCount }}{{ __('legacy/functions.text_message_new') }})@endif
                <a href="messages.php?action=viewmailbox&amp;box=-1"><img class="sentbox" alt="sentbox" title="{{ __('legacy/functions.title_sentbox') }}" src="pic/trans.gif" /></a>{{ $chrome->outboxCount }}
            </div>
        </div>
    </div>
    @else
    <nav class="nxm-nav" aria-label="{{ 'Main navigation' }}">
        <ul class="nxm-nav__list">
            <li><a class="nxm-nav__link" href="login.php">{{ __('legacy/functions.text_login') }}</a></li>
            <li><a class="nxm-nav__link" href="signup.php">{{ __('legacy/functions.text_signup') }}</a></li>
            <li><button type="button" class="nxm-linkbtn nxm-theme-toggle" title="{{ 'Theme' }}">[{{ 'Theme' }}]</button></li>
        </ul>
    </nav>
    @endif
</header>

<main id="main-content" class="nxm-main" tabindex="-1" role="main">
@foreach($chrome->alerts as $alert)
<div class="nxm-alert nxm-alert--{{ $alert['color'] }}" role="alert">
    @if($alert['url'] !== '')<a href="{{ $alert['url'] }}" target="_blank" rel="noopener"><b>{{ $alert['text'] }}</b></a>
    @else<b>{{ $alert['text'] }}</b>@endif
</div>
@endforeach
@if($chrome->offlineMsg)
<div class="nxm-alert-offline">{{ $chrome->offlineMsgHtml }}</div>
@endif
