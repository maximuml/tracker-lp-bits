<body data-chrome="{{ $chrome->variant }}">
<a href="#main-content" class="skip-link">{{ 'Skip to main content' }}</a>

<header class="nxm-header" role="banner">
    <div class="nxm-header__bar">
        <div class="nxm-header__brand">
            @if($chrome->logoMain === '')
                <a class="nxm-logo" href="index.php">{{ $chrome->siteName }}</a>
                @if($chrome->slogan !== '')<span class="nxm-slogan">{{ $chrome->slogan }}</span>@endif
            @else
                <a class="nxm-logo" href="index.php"><img src="{{ $chrome->logoMain }}" alt="{{ $chrome->siteName }}" /></a>
            @endif
        </div>
        @if($chrome->user)
        <div class="nxm-collapse" id="nxm-collapse">
            <nav class="nxm-nav" aria-label="{{ 'Main navigation' }}">
                <ul class="nxm-nav__list">
                    @foreach(array_slice($chrome->navItems, 0, 6) as $item)
                    <li><a href="{{ $item['href'] }}" @if($item['selected']) aria-current="page" class="nxm-nav__link--active" @else class="nxm-nav__link" @endif>{{ $item['label'] }}</a></li>
                    @endforeach
                    @if(count($chrome->navItems) > 6)
                    <li class="nxm-nav__more">
                        <details class="nxm-more">
                            <summary class="nxm-nav__link{{ collect(array_slice($chrome->navItems, 6))->contains('selected', true) ? ' nxm-nav__link--active' : '' }}">{{ 'More' }}<span class="nxm-more__caret" aria-hidden="true"></span></summary>
                            <ul class="nxm-nav__sub">
                                @foreach(array_slice($chrome->navItems, 6) as $item)
                                <li><a href="{{ $item['href'] }}" @if($item['selected']) aria-current="page" class="nxm-nav__link--active" @else class="nxm-nav__link" @endif>{{ $item['label'] }}</a></li>
                                @endforeach
                            </ul>
                        </details>
                    </li>
                    @endif
                </ul>
            </nav>
            <div class="nxm-userbar" role="group" aria-label="{{ 'Account' }}">
                <div class="nxm-userbar__row nxm-userbar__row--tools">
                    @if($chrome->search->globalSearchEnabled)
                    <form class="nxm-search" action="search.php" method="get" target="{{ $chrome->search->searchFormTarget }}">
                        <input type="text" name="search" value="{{ $chrome->search->requestSearch }}" placeholder="{{ $chrome->search->searchKeywordPlaceholder }}" />
                        <select name="search_area" aria-label="{{ __('search.search_area') }}">
                            @foreach($chrome->search->searchAreas as $area)
                            <option value="{{ $area['value'] }}"@if($area['selected']) selected @endif>{{ $area['label'] }}</option>
                            @endforeach
                        </select>
                        <input type="submit" value="{{ $chrome->search->globalSearchLabel }}" />
                    </form>
                    @endif
                    <div class="nxm-userbar__icons">
                        @if($chrome->userBar->canStaffmem)
                        <a href="cheaterbox.php"><img class="cheaterbox" alt="cheaterbox" title="{{ __('legacy/functions.title_cheaterbox') }}" src="pic/trans.gif" /></a>{{ $chrome->userBar->cheaterCount }}
                        <a href="reports.php"><img class="reportbox" alt="reportbox" title="{{ __('legacy/functions.title_reportbox') }}" src="pic/trans.gif" /></a>{{ $chrome->userBar->reportCount }}
                        @endif
                        <a href="friends.php"><img class="buddylist" alt="Buddylist" title="{{ __('legacy/functions.title_buddylist') }}" src="pic/trans.gif" /></a>
                        <a href="getrss.php"><img class="rss" alt="RSS" title="{{ __('legacy/functions.title_get_rss') }}" src="pic/trans.gif" /></a>
                        @if($chrome->userBar->staffMessageTotal > 0)
                        <a href="staffbox.php"><img class="staffbox" alt="staffbox" title="{{ __('legacy/functions.title_staffbox') }}" src="pic/trans.gif" /></a>{{ $chrome->userBar->staffMessageTotal }}
                        @endif
                        <a href="messages.php"><img class="{{ $chrome->userBar->unreadCount > 0 ? 'inboxnew' : 'inbox' }}" alt="inbox" title="{{ $chrome->userBar->unreadCount > 0 ? __('legacy/functions.title_inbox_new_messages') : __('legacy/functions.title_inbox_no_new_messages') }}" src="pic/trans.gif" /></a>{{ $chrome->userBar->inboxCount }}@if($chrome->userBar->unreadCount > 0) ({{ $chrome->userBar->unreadCount }}{{ __('legacy/functions.text_message_new') }})@endif
                        <a href="messages.php?action=viewmailbox&amp;box=-1"><img class="sentbox" alt="sentbox" title="{{ __('legacy/functions.title_sentbox') }}" src="pic/trans.gif" /></a>{{ $chrome->userBar->outboxCount }}
                        <span class="nx-notif">
                            <a href="#" id="nx-notif-bell" class="nx-notif-bell" role="button" aria-label="{{ __('legacy/notifications.title_bell') }}" aria-haspopup="true" aria-expanded="false" title="{{ __('legacy/notifications.title_bell') }}"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 1a4.5 4.5 0 0 0-4.5 4.5v2.4c0 .4-.12.78-.34 1.11L2 10.5c-.4.64.05 1.5.83 1.5h10.34c.78 0 1.23-.86.83-1.5l-1.16-1.49a2.1 2.1 0 0 1-.34-1.11V5.5A4.5 4.5 0 0 0 8 1Zm0 13.5a2 2 0 0 0 1.86-1.25H6.14A2 2 0 0 0 8 14.5Z"/></svg><span id="nx-notif-badge" class="nx-notif-badge nx-hidden">0</span></a>
                            <div id="nx-notif-panel" class="nx-notif-panel nx-hidden" role="region" aria-label="{{ __('legacy/notifications.title_bell') }}"></div>
                        </span>
                        <button type="button" class="nxm-iconbtn nxm-theme-toggle" data-persist-url="/web/usercp/theme" title="{{ 'Theme' }}: {{ ucfirst($chrome->head->theme) }}">{{ 'Theme' }}: {{ ucfirst($chrome->head->theme) }}</button>
                        <form method="post" action="logout.php" class="nx-inline">@csrf<button type="submit" class="nxm-iconbtn" title="{{ __('legacy/functions.text_logout') }}" aria-label="{{ __('legacy/functions.text_logout') }}"><svg width="15" height="15" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M6 1v3h1V2h6v12H7v-2H6v3h8V1H6z"/><path d="M3.3 5.3.6 8l2.7 2.7.7-.7L2.4 8.4h7.6V7.6H2.4L4 6l-.7-.7z"/></svg></button></form>
                        @if($chrome->enableDonation)
                        <a class="nxm-donate" href="donate.php"><img src="{{ $chrome->head->picFolder }}/donate.gif" alt="{{ 'Make a donation' }}" /></a>
                        @endif
                    </div>
                </div>
            </div>
        </div>
        <button type="button" class="nxm-burger" aria-expanded="false" aria-controls="nxm-collapse" aria-label="{{ 'Toggle navigation' }}">&#x2630;</button>
        @else
        <nav class="nxm-nav" aria-label="{{ 'Main navigation' }}">
            <ul class="nxm-nav__list">
                <li><a class="nxm-nav__link" href="login.php">{{ __('legacy/functions.text_login') }}</a></li>
                <li><a class="nxm-nav__link" href="signup.php">{{ __('legacy/functions.text_signup') }}</a></li>
                <li><button type="button" class="nxm-linkbtn nxm-theme-toggle" title="{{ 'Theme' }}">[{{ 'Theme' }}]</button></li>
                @if($chrome->enableDonation)
                <li><a class="nxm-donate" href="donate.php"><img src="{{ $chrome->head->picFolder }}/donate.gif" alt="{{ 'Make a donation' }}" /></a></li>
                @endif
            </ul>
        </nav>
        @endif
    </div>
    @if($chrome->user)
    <div class="nxm-chips" role="group" aria-label="{{ 'Account stats' }}">
        <div class="nxm-chips__row">
            <details class="nxm-usermenu">
                <summary class="nxm-usermenu__toggle">
                    <span class="nxm-avatar" aria-hidden="true">{{ strtoupper(substr((string) ($chrome->user['username'] ?? 'U'), 0, 1)) }}</span>
                    <span class="nxm-usermenu__name">{{ $chrome->user['username'] ?? '' }}</span>
                    <span class="nxm-usermenu__caret" aria-hidden="true"></span>
                </summary>
                <div class="nxm-usermenu__panel">
                    <div class="nxm-usermenu__hello">{{ __('legacy/functions.text_welcome_back') }}, {{ $chrome->userBar->usernameHtml }}</div>
                    <div class="nxm-usermenu__links">
                        <a href="usercp.php">{{ __('legacy/functions.text_user_cp') }}</a>
                        @if($chrome->userBar->isModerator)<a href="staffpanel.php">{{ __('legacy/functions.text_staff_panel') }}</a>@endif
                        @if($chrome->userBar->isSysop)<a href="settings.php">{{ __('legacy/functions.text_site_settings') }}</a>@endif
                        <a href="torrents.php?inclbookmarked=1&amp;allsec=1&amp;incldead=0">{{ __('legacy/functions.text_bookmarks') }}</a>
                        <a href="mybonus.php">{{ __('legacy/functions.text_bonus') }}: {{ $chrome->userBar->seedbonus }}</a>
                        @if($chrome->userBar->attendanceDone)
                        <a href="attendance.php">{{ sprintf((string) __('legacy/functions.text_attended'), $chrome->userBar->attendancePoints, $chrome->userBar->attendanceCard) }}</a>
                        @else
                        <a href="attendance.php" class="faqlink">{{ __('legacy/functions.text_attendance') }}</a>
                        @endif
                        <a href="medal.php">{{ $chrome->userBar->medalLabel }}</a>
                        <a href="task.php">{{ $chrome->userBar->taskLabel }}</a>
                        <a href="invite.php?id={{ (int) $chrome->user['id'] }}">{{ __('legacy/functions.text_invite') }}: {{ $chrome->userBar->invites }}@if($chrome->userBar->pendingInvites > 0) ({{ $chrome->userBar->pendingInvites }})@endif</a>
                        @if($chrome->userBar->managementHref !== '')<a href="{{ $chrome->userBar->managementHref }}" target="_blank" rel="noopener">{{ __('legacy/functions.text_management_system') }}</a>@endif
                    </div>
                    <div class="nxm-usermenu__footer">
                        <form method="post" action="logout.php" class="nxm-usermenu__form">@csrf<button type="submit" class="nxm-usermenu__item nxm-usermenu__item--danger">{{ __('legacy/functions.text_logout') }}</button></form>
                    </div>
                </div>
            </details>
            <span class="nxm-userbar__stats">
                <span class="nxm-stat"><span class="nxm-stat__label">{{ __('legacy/functions.text_ratio') }}</span> <span class="nxm-stat__value">{{ $chrome->userBar->ratio }}</span></span>
                <span class="nxm-stat"><span class="nxm-stat__label">{{ __('legacy/functions.text_uploaded') }}</span> <span class="nxm-stat__value">{{ $chrome->userBar->uploaded }}</span></span>
                <span class="nxm-stat"><span class="nxm-stat__label">{{ __('legacy/functions.text_downloaded') }}</span> <span class="nxm-stat__value">{{ $chrome->userBar->downloaded }}</span></span>
                <span class="nxm-stat"><span class="nxm-stat__label">{{ __('legacy/functions.text_active_torrents') }}</span>
                    <span class="nxm-stat__value">
                        <span title="{{ __('legacy/functions.title_torrents_seeding') }}">&#x25B2;{{ $chrome->userBar->activeSeed }}</span>
                        <span title="{{ __('legacy/functions.title_torrents_leeching') }}">&#x25BC;{{ $chrome->userBar->activeLeech }}</span>
                    </span>
                </span>
                <span class="nxm-stat"><span class="nxm-stat__label">{{ __('legacy/functions.text_connectable') }}</span>
                    <span class="nxm-stat__value">
                        @if($chrome->userBar->connectable === true)<b class="nxm-ok">{{ __('legacy/functions.text_yes') }}</b>
                        @elseif($chrome->userBar->connectable === false)<a href="faq.php#id21"><b class="nxm-bad">{{ __('legacy/functions.text_no') }}</b></a>
                        @else{{ __('legacy/functions.text_unknown') }}@endif
                    </span>
                </span>
                <span class="nxm-stat"><span class="nxm-stat__label">{{ __('legacy/functions.text_slots') }}</span>
                    <span class="nxm-stat__value">
                        @if($chrome->userBar->maxSlots > 0)<a href="faq.php#id215">{{ $chrome->userBar->maxSlots }}</a>
                        @else{{ __('legacy/functions.text_unlimited') }}@endif
                    </span>
                </span>
                @if($chrome->userBar->hitAndRunEnabled)
                <span class="nxm-stat"><span class="nxm-stat__label">H&amp;R</span> <span class="nxm-stat__value">[<a href="myhr.php">{{ $chrome->userBar->hitAndRunStatsHtml }}</a>]</span></span>
                @endif
            </span>
        </div>
    </div>
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
