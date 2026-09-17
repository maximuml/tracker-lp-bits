<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
<meta name="csrf-token" content="{{ csrf_token() }}" />
<meta name="generator" content="{{ PROJECTNAME }}" />
<title>{{ $chrome->title }}</title>
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
<link rel="search" type="application/opensearchdescription+xml" title="{{ $chrome->siteName }} Torrents" href="opensearch.php" />
<link rel="alternate" type="application/rss+xml" title="Latest Torrents" href="torrentrss.php" />
<link rel="stylesheet" href="styles/sprites.css" type="text/css" />
<link rel="stylesheet" href="styles/nexus.css" type="text/css" />
<link rel="stylesheet" href="css/modern.css" type="text/css" />
@foreach (\App\Support\AssetAppender::getAppendHeadersSafe() as $html)
{{ $html }}
@endforeach
<script type="text/javascript" src="vendor/jquery-3.7.1.min.js"></script>
<script type="text/javascript" src="vendor/layer-v3.5.1/layer/layer.js"></script>
<script type="text/javascript" src="js/csrf.js"></script>
</head>
<body data-chrome="modern">
<a href="#main-content" class="skip-link">{{ 'Skip to main content' }}</a>

<header class="nxm-header" role="banner">
    <div class="nxm-header__brand">
        @if($chrome->logoMain === '')
            <a class="nxm-logo" href="index.php">{{ $chrome->siteName }}</a>
            @if($chrome->slogan !== '')<span class="nxm-slogan">{{ $chrome->slogan }}</span>@endif
        @else
            <a class="nxm-logo" href="index.php"><img src="{{ $chrome->logoMain }}" alt="{{ $chrome->siteName }}" /></a>
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
        <span class="nxm-userbar__greeting">{{ __('legacy/functions.text_welcome_back') }},</span>
        {{ $chrome->usernameHtml }}
        <span class="nxm-userbar__links">
            <form method="post" action="logout.php" class="nx-inline">@csrf<button type="submit" class="nxm-linkbtn">[{{ __('legacy/functions.text_logout') }}]</button></form>
            <a href="usercp.php">[{{ __('legacy/functions.text_user_cp') }}]</a>
            @if($chrome->isModerator)<a href="staffpanel.php">[{{ __('legacy/functions.text_staff_panel') }}]</a>@endif
            @if($chrome->isSysop)<a href="settings.php">[{{ __('legacy/functions.text_site_settings') }}]</a>@endif
            <a href="torrents.php?inclbookmarked=1&amp;allsec=1&amp;incldead=0">[{{ __('legacy/functions.text_bookmarks') }}]</a>
            <a href="mybonus.php">{{ __('legacy/functions.text_bonus') }}: {{ $chrome->seedbonus }}</a>
            <a href="invite.php?id={{ (int) $chrome->user['id'] }}">{{ __('legacy/functions.text_invite') }}: {{ $chrome->invites }}@if($chrome->pendingInvites > 0) ({{ $chrome->pendingInvites }})@endif</a>
        </span>
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
        </span>
    </div>
    @else
    <nav class="nxm-nav" aria-label="{{ 'Main navigation' }}">
        <ul class="nxm-nav__list">
            <li><a class="nxm-nav__link" href="login.php">{{ __('legacy/functions.text_login') }}</a></li>
            <li><a class="nxm-nav__link" href="signup.php">{{ __('legacy/functions.text_signup') }}</a></li>
        </ul>
    </nav>
    @endif
</header>

<main id="main-content" class="nxm-main" tabindex="-1" role="main">
@yield('content')
</main>

<footer class="nxm-footer" role="contentinfo">
    <span>(c) <a href="{{ $chrome->baseUrl }}">{{ $chrome->siteName }}</a>
        {{ $chrome->yearFounded != date('Y') ? $chrome->yearFounded.'-' : '' }}{{ date('Y') }}</span>
    <span class="nxm-footer__sep">&middot;</span>
    <span>Powered by {{ PROJECTNAME }}</span>
</footer>

<script type="text/javascript" src="js/ajax.js"></script>
<script type="text/javascript" src="js/nexus.js"></script>
<script type="text/javascript" src="js/common.js"></script>
<script type="text/javascript" src="js/domLib.js"></script>
<script type="text/javascript" src="js/domTT.js"></script>
<script type="text/javascript" src="js/domTT_drag.js"></script>
<script type="text/javascript" src="js/fadomatic.js"></script>
<script type="text/javascript" src="js/medium-zoom.min.js"></script>
<script type="text/javascript" src="js/goup.js"></script>
@foreach (\App\Support\AssetAppender::getAppendFootersSafe() as $html)
{{ $html }}
@endforeach
</body>
</html>
