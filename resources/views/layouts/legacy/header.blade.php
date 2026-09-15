<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ $locale }}">
<head>
<base href="{{ url('/') }}/" />
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
@if($metaKeywords)
<meta name="keywords" content="{{ $metaKeywords }}" />
@endif
@if($metaDescription)
<meta name="description" content="{{ $metaDescription }}" />
@endif
<meta name="generator" content="{{ $projectName }}" />
<meta name="csrf-token" content="{{ $csrfToken }}" />
@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($addiCode))
<title>@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($title))</title>
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
<link rel="search" type="application/opensearchdescription+xml" title="@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($siteName)) Torrents" href="opensearch.php" />
<link rel="stylesheet" href="@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($fontCssUri)){{ $cssUpdateDate }}" type="text/css" />
<link rel="stylesheet" href="styles/sprites.css{{ $cssUpdateDate }}" type="text/css" />
<link rel="stylesheet" href="@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($forumPicFolder))/forumsprites.css{{ $cssUpdateDate }}" type="text/css" />
<link rel="stylesheet" href="@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($cssUri))theme.css{{ $cssUpdateDate }}" type="text/css" />
<link rel="stylesheet" href="@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($cssUri))DomTT.css{{ $cssUpdateDate }}" type="text/css" />
<link rel="stylesheet" href="styles/nexus.css{{ $cssUpdateDate }}" type="text/css" />
@if($searchBoxIcons)
@foreach($searchBoxIcons as $cssfile)
<link rel="stylesheet" href="{{ $cssfile }}{{ $cssUpdateDate }}" type="text/css" />
@endforeach
@endif
<link rel="alternate" type="application/rss+xml" title="Latest Torrents" href="torrentrss.php" />
<script type="text/javascript" src="js/curtain_imageresizer.js{{ $cssUpdateDate }}"></script>
<script type="text/javascript" src="js/ajaxbasic.js{{ $cssUpdateDate }}"></script>
<script type="text/javascript" src="js/common.js{{ $cssUpdateDate }}"></script>
<script type="text/javascript" src="js/domLib.js{{ $cssUpdateDate }}"></script>
<script type="text/javascript" src="js/domTT.js{{ $cssUpdateDate }}"></script>
<script type="text/javascript" src="js/domTT_drag.js{{ $cssUpdateDate }}"></script>
<script type="text/javascript" src="js/fadomatic.js{{ $cssUpdateDate }}"></script>
@foreach($appendHeaders as $value)
@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($value))
@endforeach
<script type="text/javascript" nonce="@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($cspNonce))">
    window.nexusLayerOptions = {
        confirm: {btnAlign: 'c', title: 'Confirm', btn: ['OK', 'Cancel']},
        alert: {btnAlign: 'c', title: 'Info', btn: ['OK', 'Cancel']}
    }
</script>
<script type="text/javascript" src="vendor/jquery-3.7.1.min.js{{ $cssUpdateDate }}"></script>
<script type="text/javascript" src="vendor/layer-v3.5.1/layer/layer.js{{ $cssUpdateDate }}"></script>
</head>
<body>
<a href="#main-content" class="skip-link">Skip to main content</a>
<div class="nx-head" style="width: {{ $headTableWidth }}px">
		<div>
@if($logoMain === '')
			<div class="logo">{{ $siteName }}</div>
			<div class="slogan">{{ $slogan }}</div>
@else
			<div class="logo_img"><img src="@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($logoMain))" alt="{{ $siteName }}" title="{{ $siteName }} - {{ $slogan }}" /></div>
@endif
		</div>
		<div class="nx-nowrap">
@if($enableDonation === 'yes')
			<a href="donate.php"><img src="@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($forumPicFolder))/donate.gif" alt="Make a donation" style="margin-left: 5px; margin-top: 50px;" /></a>
@endif
		</div>
</div>

<div class="nx-mainouter" style="width: {{ $contentWidth }}px">
	<div id="nav_block" class="nx-text nx-center">
@if(!$user)
			<a href="login.php"><font class="big"><b>@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_login'] ?? ''))</b></font></a> / <a href="signup.php"><font class="big"><b>@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_signup'] ?? ''))</b></font></a>
@else
@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($menuHtml))

<div id="info_block">
	<div class="nx-row">
		<div class="nx-grow">
            <span class="medium">
                @safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_welcome_back'] ?? '')), @safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($username))
                [<form method="post" action="logout.php" class="nx-inline">@csrf<button type="submit" class="nx-btn-link">@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_logout'] ?? ''))</button></form>]
                [<a href="usercp.php">@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_user_cp'] ?? ''))</a>]
                @if($isModerator) [<a href="staffpanel.php">@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_staff_panel'] ?? ''))</a>] @endif
                @if($isSysop) [<a href="settings.php">@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_site_settings'] ?? ''))</a>]@endif
                [<a href="torrents.php?inclbookmarked=1&amp;allsec=1&amp;incldead=0">@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_bookmarks'] ?? ''))</a>]
                <font class = 'color_bonus'>@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_bonus'] ?? ''))</font>[<a href="mybonus.php">@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_use'] ?? ''))</a>]: {{ $seedbonus }}
                @safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($attendanceLink))
                <a href="medal.php">[@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($medalLabel))]</a>
                <a href="task.php">[@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($taskLabel))]</a>
                <font class = 'color_invite'>@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_invite'] ?? ''))</font>[<a href="invite.php?id={{ $userId }}">@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_send'] ?? ''))</a>]: {{ $invites }}({{ $pendingInviteCount }})
                @safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($managementSystemLink))
                <br />
	            <font class="color_ratio">@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_ratio'] ?? ''))</font> {{ $ratio }}
                <font class='color_uploaded'>@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_uploaded'] ?? ''))</font> @safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($uploaded))
                <font class='color_downloaded'> @safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_downloaded'] ?? ''))</font> @safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($downloaded))
                <font class='color_active'>@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_active_torrents'] ?? ''))</font> <img class="arrowup" alt="Torrents seeding" title="@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['title_torrents_seeding'] ?? ''))" src="pic/trans.gif" />{{ $activeseed }}  <img class="arrowdown" alt="Torrents leeching" title="@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['title_torrents_leeching'] ?? ''))" src="pic/trans.gif" />{{ $activeleech }}&nbsp;&nbsp;
                <font class='color_connectable'>@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($lang['text_connectable'] ?? ''))</font>@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($connectable)) @safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($slotsDisplay))
                @if($hitAndRunEnabled)<font class='color_bonus'>H&R: </font> @safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($hitAndRunStatus)) @endif
            </span>
        </div>
                @if($globalSearchEnabled)
        <div>
            <form action="search.php" method="get" target="{{ $searchFormTarget }}">
                <div class="nx-flex" style="align-items: center">
                    <div class="nx-flex-col">
                        <div>
                            <span><input type="text" name="search" style="width: 80px;height: 12px" value="@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($requestSearchEscaped))" placeholder="@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($searchKeywordPlaceholder))"/></span>
                        </div>
                        <div>
                            <span>@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($searchBoxAreaSelect))</span>
                        </div>
                    </div>
                    <div><input type="submit" value="@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($globalSearchLabel))" style="width: 39px;white-space: break-spaces;padding: 0" /></div>
                </div>
            </form>
        </div>
                @endif
	<div><span class="medium">
@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($staffIcons))
	</span></div>
	</div>
</div>

</div>

<div id="outer" class="outer nx-center" style="padding-top: 20px; padding-bottom: 20px">
@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($messageAlerts))
@if($offlineMsg)
@safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($offlineMsgHtml))
@endif
@endif
