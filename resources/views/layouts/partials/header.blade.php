<body data-chrome="{{ $chrome->variant }}">
<a href="#main-content" class="skip-link">{{ 'Skip to main content' }}</a>

<header class="nxm-header" role="banner">
    <div class="nxm-header__bar">
        <div class="nxm-header__brand">
            @if($chrome->logoMain === '')
                <a class="nxm-logo" href="/web/index"><span class="nxm-logo__mark" aria-hidden="true">{{ strtoupper(substr($chrome->siteName, 0, 1)) }}</span><span class="nxm-logo__name">{{ $chrome->siteName }}</span></a>
                @if($chrome->slogan !== '')<span class="nxm-slogan">{{ $chrome->slogan }}</span>@endif
            @else
                <a class="nxm-logo" href="/web/index"><img src="{{ $chrome->logoMain }}" alt="{{ $chrome->siteName }}" /></a>
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
            @if($chrome->search->globalSearchEnabled)
            <form class="nxm-search" id="nxm-search" action="/web/search" method="get" target="{{ $chrome->search->searchFormTarget }}">
                <svg class="nxm-search__icon" width="14" height="14" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><circle cx="7" cy="7" r="5"/><path d="M11 11l4 4"/></svg>
                <input type="text" name="search" value="{{ $chrome->search->requestSearch }}" placeholder="{{ $chrome->search->searchKeywordPlaceholder }}" />
                <select name="search_area" aria-label="{{ __('search.search_area') }}">
                    @foreach($chrome->search->searchAreas as $area)
                    <option value="{{ $area['value'] }}"@if($area['selected']) selected @endif>{{ $area['label'] }}</option>
                    @endforeach
                </select>
                <kbd class="nxm-search__kbd" aria-hidden="true">&#x2318;K</kbd>
                <button type="submit" class="nxm-search__go" aria-label="{{ $chrome->search->globalSearchLabel }}" title="{{ $chrome->search->globalSearchLabel }}"><svg width="13" height="13" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M2 8h11M9 3.5 13.5 8 9 12.5"/></svg></button>
            </form>
            @endif
        </div>
        <div class="nxm-header__actions">
            @if($chrome->search->globalSearchEnabled)
            <button type="button" class="nxm-iconbtn nxm-searchbtn" aria-expanded="false" aria-controls="nxm-search" title="{{ 'Search' }}" aria-label="{{ 'Search' }}"><svg width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="7" cy="7" r="4.5"/><path d="m10.5 10.5 4 4"/></svg></button>
            @endif
            <div class="nxm-userbar__icons">
                <a href="/web/messages" class="nxm-iconbtn nxm-inboxbtn" title="{{ $chrome->userBar->unreadCount > 0 ? __('legacy/functions.title_inbox_new_messages') : __('legacy/functions.title_inbox_no_new_messages') }}" aria-label="{{ $chrome->userBar->unreadCount > 0 ? __('legacy/functions.title_inbox_new_messages') : __('legacy/functions.title_inbox_no_new_messages') }}"><svg width="16" height="16" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><rect x="1.5" y="3.5" width="13" height="9" rx="1.5"/><path d="m2.5 5 5.5 4 5.5-4"/></svg>@if($chrome->userBar->unreadCount > 0)<span class="nxm-badge">{{ $chrome->userBar->unreadCount }}</span>@endif</a>
                <span class="nx-notif">
                    <a href="#" id="nx-notif-bell" class="nx-notif-bell" role="button" aria-label="{{ __('legacy/notifications.title_bell') }}" aria-haspopup="true" aria-expanded="false" title="{{ __('legacy/notifications.title_bell') }}"><svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 1a4.5 4.5 0 0 0-4.5 4.5v2.4c0 .4-.12.78-.34 1.11L2 10.5c-.4.64.05 1.5.83 1.5h10.34c.78 0 1.23-.86.83-1.5l-1.16-1.49a2.1 2.1 0 0 1-.34-1.11V5.5A4.5 4.5 0 0 0 8 1Zm0 13.5a2 2 0 0 0 1.86-1.25H6.14A2 2 0 0 0 8 14.5Z"/></svg><span id="nx-notif-badge" class="nx-notif-badge nx-hidden">0</span></a>
                    <div id="nx-notif-panel" class="nx-notif-panel nx-hidden" role="region" aria-label="{{ __('legacy/notifications.title_bell') }}"></div>
                </span>
                <button type="button" class="nxm-iconbtn nxm-theme-toggle" data-persist-url="/web/usercp/theme" data-theme-state="{{ $chrome->head->theme }}" title="{{ 'Theme' }}: {{ ucfirst($chrome->head->theme) }}" aria-label="{{ 'Theme' }}"><svg class="nxm-theme-ic nxm-theme-ic--auto" width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="8" cy="8" r="6.2"/><path d="M8 1.8A6.2 6.2 0 0 1 8 14.2Z" fill="currentColor" stroke="none"/></svg><svg class="nxm-theme-ic nxm-theme-ic--light" width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="8" cy="8" r="3.4"/><path d="M8 1.2v1.8M8 13v1.8M1.2 8h1.8M13 8h1.8M3.2 3.2l1.3 1.3M11.5 11.5l1.3 1.3M12.8 3.2l-1.3 1.3M4.5 11.5l-1.3 1.3"/></svg><svg class="nxm-theme-ic nxm-theme-ic--dark" width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M13.5 9.6A6 6 0 0 1 6.4 2.5a6 6 0 1 0 7.1 7.1Z"/></svg></button>
            </div>
            <details class="nxm-usermenu">
                <summary class="nxm-usermenu__toggle">
                    <span class="nxm-avatar" aria-hidden="true"><span class="nxm-avatar__letter">{{ strtoupper(substr((string) ($chrome->user['username'] ?? 'U'), 0, 1)) }}</span><img class="nxm-avatar__img" src="{{ \App\Support\Avatar::forUser((int) ($chrome->user['id'] ?? 0), (string) ($chrome->user['avatar'] ?? '')) }}" alt="" /></span>
                    <span class="nxm-usermenu__name">{{ $chrome->user['username'] ?? '' }}</span>
                    <span class="nxm-usermenu__caret" aria-hidden="true"></span>
                </summary>
                <div class="nxm-usermenu__panel">
                    <div class="nxm-usermenu__head">
                        <span class="nxm-avatar nxm-avatar--lg" aria-hidden="true"><span class="nxm-avatar__letter">{{ strtoupper(substr((string) ($chrome->user['username'] ?? 'U'), 0, 1)) }}</span><img class="nxm-avatar__img" src="{{ \App\Support\Avatar::forUser((int) ($chrome->user['id'] ?? 0), (string) ($chrome->user['avatar'] ?? '')) }}" alt="" /></span>
                        <span class="nxm-usermenu__who">
                            <b>{{ $chrome->user['username'] ?? '' }}</b>
                            <span>{{ __('legacy/functions.text_welcome_back') }}</span>
                        </span>
                    </div>
                    <div class="nxm-usermenu__stats">
                        <span class="nxm-stat2"><small>{{ __('legacy/functions.text_ratio') }}</small><b class="{{ str_starts_with(trim((string) $chrome->userBar->ratio, '.'), '0') ? '' : 'nxm-ok' }}">{{ $chrome->userBar->ratio }}</b></span>
                        <span class="nxm-stat2"><small>{{ __('legacy/functions.text_active_torrents') }}</small><b><span title="{{ __('legacy/functions.title_torrents_seeding') }}">&#x25B2;{{ $chrome->userBar->activeSeed }}</span> <span title="{{ __('legacy/functions.title_torrents_leeching') }}">&#x25BC;{{ $chrome->userBar->activeLeech }}</span></b></span>
                        <span class="nxm-stat2"><small>{{ __('legacy/functions.text_uploaded') }}</small><b>{{ $chrome->userBar->uploaded }}</b></span>
                        <span class="nxm-stat2"><small>{{ __('legacy/functions.text_downloaded') }}</small><b>{{ $chrome->userBar->downloaded }}</b></span>
                        <span class="nxm-stat2"><small>{{ __('legacy/functions.text_connectable') }}</small><b>
                            @if($chrome->userBar->connectable === true)<span class="nxm-ok">{{ __('legacy/functions.text_yes') }}</span>
                            @elseif($chrome->userBar->connectable === false)<a href="/web/faq#id21"><span class="nxm-bad">{{ __('legacy/functions.text_no') }}</span></a>
                            @else{{ __('legacy/functions.text_unknown') }}@endif
                        </b></span>
                        <span class="nxm-stat2"><small>{{ __('legacy/functions.text_slots') }}</small><b>
                            @if($chrome->userBar->maxSlots > 0)<a href="/web/faq#id215">{{ $chrome->userBar->maxSlots }}</a>
                            @else{{ __('legacy/functions.text_unlimited') }}@endif
                        </b></span>
                        @if($chrome->userBar->hitAndRunEnabled)
                        <span class="nxm-stat2 nxm-stat2--wide"><small>H&amp;R</small><b><a href="/web/myhr">{{ $chrome->userBar->hitAndRunStatsHtml }}</a></b></span>
                        @endif
                    </div>
                    <div class="nxm-usermenu__links">
                        <a href="/usercp">{{ __('legacy/functions.text_user_cp') }}</a>
                        @if($chrome->userBar->isModerator)<a href="/web/staffpanel">{{ __('legacy/functions.text_staff_panel') }}</a>@endif
                        @if($chrome->userBar->isSysop)<a href="/web/settings">{{ __('legacy/functions.text_site_settings') }}</a>@endif
                        <a href="/web/torrents?inclbookmarked=1&amp;allsec=1&amp;incldead=0">{{ __('legacy/functions.text_bookmarks') }}</a>
                        <a href="/web/mybonus">{{ __('legacy/functions.text_bonus') }}<span class="nxm-usermenu__count">{{ $chrome->userBar->seedbonus }}</span></a>
                        @if($chrome->userBar->attendanceDone)
                        <a href="/web/attendance">{{ sprintf((string) __('legacy/functions.text_attended'), $chrome->userBar->attendancePoints, $chrome->userBar->attendanceCard) }}</a>
                        @else
                        <a href="/web/attendance" class="faqlink">{{ __('legacy/functions.text_attendance') }}</a>
                        @endif
                        <a href="/web/task">{{ $chrome->userBar->taskLabel }}</a>
                        <a href="/web/invite?id={{ (int) $chrome->user['id'] }}">{{ __('legacy/functions.text_invite') }}<span class="nxm-usermenu__count">{{ $chrome->userBar->invites }}@if($chrome->userBar->pendingInvites > 0) ({{ $chrome->userBar->pendingInvites }})@endif</span></a>
                        @if($chrome->userBar->managementHref !== '')<a href="{{ $chrome->userBar->managementHref }}" target="_blank" rel="noopener">{{ __('legacy/functions.text_management_system') }}</a>@endif
                    </div>
                    <div class="nxm-usermenu__sep" role="separator"></div>
                    <div class="nxm-usermenu__links">
                        <a href="/web/messages">{{ 'Inbox' }}<span class="nxm-usermenu__count">{{ $chrome->userBar->inboxCount }}@if($chrome->userBar->unreadCount > 0) ({{ $chrome->userBar->unreadCount }})@endif</span></a>
                        <a href="/web/friends">{{ 'Friends' }}</a>
                        <a href="/web/getrss">{{ 'RSS' }}</a>
                        <a href="/web/messages?action=viewmailbox&amp;box=-1">{{ 'Sent box' }}<span class="nxm-usermenu__count">{{ $chrome->userBar->outboxCount }}</span></a>
                        <a href="/staffbox">{{ 'Staff box' }}<span class="nxm-usermenu__count">{{ $chrome->userBar->staffMessageTotal }}</span></a>
                        @if($chrome->enableDonation)
                        <a href="/web/donate">{{ 'Donate' }}</a>
                        @endif
                        @if($chrome->userBar->canStaffmem)
                        <a href="/cheaterbox">{{ 'Cheater box' }}<span class="nxm-usermenu__count">{{ $chrome->userBar->cheaterCount }}</span></a>
                        <a href="/web/reports">{{ 'Reports' }}<span class="nxm-usermenu__count">{{ $chrome->userBar->reportCount }}</span></a>
                        @endif
                    </div>
                    <div class="nxm-usermenu__footer">
                        <form method="post" action="/logout" class="nxm-usermenu__form">@csrf<button type="submit" class="nxm-usermenu__item nxm-usermenu__item--danger">{{ __('legacy/functions.text_logout') }}</button></form>
                    </div>
                </div>
            </details>
            <button type="button" class="nxm-burger" aria-expanded="false" aria-controls="nxm-collapse" aria-label="{{ 'Toggle navigation' }}">&#x2630;</button>
        </div>
        @else
        <nav class="nxm-nav" aria-label="{{ 'Main navigation' }}">
            <ul class="nxm-nav__list">
                <li><a class="nxm-nav__link" href="/login">{{ __('legacy/functions.text_login') }}</a></li>
                <li><a class="nxm-nav__link" href="/signup">{{ __('legacy/functions.text_signup') }}</a></li>
                <li><button type="button" class="nxm-iconbtn nxm-theme-toggle" data-theme-state="{{ $chrome->head->theme }}" title="{{ 'Theme' }}: {{ ucfirst($chrome->head->theme) }}" aria-label="{{ 'Theme' }}"><svg class="nxm-theme-ic nxm-theme-ic--auto" width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="8" cy="8" r="6.2"/><path d="M8 1.8A6.2 6.2 0 0 1 8 14.2Z" fill="currentColor" stroke="none"/></svg><svg class="nxm-theme-ic nxm-theme-ic--light" width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true"><circle cx="8" cy="8" r="3.4"/><path d="M8 1.2v1.8M8 13v1.8M1.2 8h1.8M13 8h1.8M3.2 3.2l1.3 1.3M11.5 11.5l1.3 1.3M12.8 3.2l-1.3 1.3M4.5 11.5l-1.3 1.3"/></svg><svg class="nxm-theme-ic nxm-theme-ic--dark" width="15" height="15" viewBox="0 0 16 16" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M13.5 9.6A6 6 0 0 1 6.4 2.5a6 6 0 1 0 7.1 7.1Z"/></svg></button></li>
                @if($chrome->enableDonation)
                <li><a class="nxm-donate" href="/web/donate">{{ 'Donate' }}</a></li>
                @endif
            </ul>
        </nav>
        @endif
    </div>
</header>

<main id="main-content" class="nxm-main" tabindex="-1" role="main">
@foreach($chrome->alerts as $alert)
<div class="nxm-alert nxm-alert--ribbon nxm-alert--{{ $alert['color'] }}" role="alert" data-alert-key="{{ md5($alert['url'].'|'.$alert['text']->toHtml()) }}">
    <span class="nxm-alert__dot" aria-hidden="true"></span>
    <span class="nxm-alert__text">{{ $alert['text'] }}</span>
    @if($alert['url'] !== '')<a class="nxm-alert__action" href="{{ $alert['url'] }}" target="_blank" rel="noopener">{{ __('legacy/functions.text_view') }} &rsaquo;</a>@endif
    <button type="button" class="nxm-alert__dismiss" data-alert-dismiss aria-label="{{ __('legacy/functions.text_dismiss') }}">&times;</button>
</div>
@endforeach
@if($chrome->offlineMsg)
<div class="nxm-alert-offline">{{ $chrome->offlineMsgHtml }}</div>
@endif
