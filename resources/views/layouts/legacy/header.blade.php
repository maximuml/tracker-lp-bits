<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" lang="{{ $locale }}">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1" />
@if($metaKeywords)
<meta name="keywords" content="{{ $metaKeywords }}" />
@endif
@if($metaDescription)
<meta name="description" content="{{ $metaDescription }}" />
@endif
<meta name="generator" content="{!! $projectName !!}" />
<meta name="csrf-token" content="{!! $csrfToken !!}" />
{!! $addiCode !!}
<title>{!! $title !!}</title>
<link rel="shortcut icon" href="favicon.ico" type="image/x-icon" />
<link rel="search" type="application/opensearchdescription+xml" title="{!! $siteName !!} Torrents" href="opensearch.php" />
<link rel="stylesheet" href="{!! $fontCssUri !!}{!! $cssUpdateDate !!}" type="text/css" />
<link rel="stylesheet" href="styles/sprites.css{!! $cssUpdateDate !!}" type="text/css" />
<link rel="stylesheet" href="{!! $forumPicFolder !!}/forumsprites.css{!! $cssUpdateDate !!}" type="text/css" />
<link rel="stylesheet" href="{!! $cssUri !!}theme.css{!! $cssUpdateDate !!}" type="text/css" />
<link rel="stylesheet" href="{!! $cssUri !!}DomTT.css{!! $cssUpdateDate !!}" type="text/css" />
<link rel="stylesheet" href="styles/nexus.css{!! $cssUpdateDate !!}" type="text/css" />
@if($searchBoxIcons)
@foreach($searchBoxIcons as $cssfile)
<link rel="stylesheet" href="{{ $cssfile }}{!! $cssUpdateDate !!}" type="text/css" />
@endforeach
@endif
<link rel="alternate" type="application/rss+xml" title="Latest Torrents" href="torrentrss.php" />
<script type="text/javascript" src="js/curtain_imageresizer.js{!! $cssUpdateDate !!}"></script>
<script type="text/javascript" src="js/ajaxbasic.js{!! $cssUpdateDate !!}"></script>
<script type="text/javascript" src="js/common.js{!! $cssUpdateDate !!}"></script>
<script type="text/javascript" src="js/domLib.js{!! $cssUpdateDate !!}"></script>
<script type="text/javascript" src="js/domTT.js{!! $cssUpdateDate !!}"></script>
<script type="text/javascript" src="js/domTT_drag.js{!! $cssUpdateDate !!}"></script>
<script type="text/javascript" src="js/fadomatic.js{!! $cssUpdateDate !!}"></script>
@foreach($appendHeaders as $value)
{!! $value !!}
@endforeach
<script type="text/javascript" nonce="{!! $cspNonce !!}">
    window.nexusLayerOptions = {
        confirm: {btnAlign: 'c', title: 'Confirm', btn: ['OK', 'Cancel']},
        alert: {btnAlign: 'c', title: 'Info', btn: ['OK', 'Cancel']}
    }
</script>
<script type="text/javascript" src="vendor/layer-v3.5.1/layer/layer.js{!! $cssUpdateDate !!}"></script>
</head>
<body>
<a href="#main-content" class="skip-link" style="position:absolute;left:-9999px;top:auto;width:1px;height:1px;overflow:hidden;" onfocus="this.style.left='0';this.style.width='auto';this.style.height='auto';" onblur="this.style.left='-9999px';this.style.width='1px';this.style.height='1px';">Skip to main content</a>
<table class="head" cellspacing="0" cellpadding="0" align="center" style="width: {{ $headTableWidth }}px">
	<tr>
		<td class="clear">
@if($logoMain === '')
			<div class="logo">{{ $siteName }}</div>
			<div class="slogan">{{ $slogan }}</div>
@else
			<div class="logo_img"><img src="{!! $logoMain !!}" alt="{{ $siteName }}" title="{{ $siteName }} - {{ $slogan }}" /></div>
@endif
		</td>
		<td class="clear nowrap" align="right" valign="middle">
@if($enableDonation === 'yes')
			<a href="donate.php"><img src="{!! $forumPicFolder !!}/donate.gif" alt="Make a donation" style="margin-left: 5px; margin-top: 50px;" /></a>
@endif
		</td>
	</tr>
</table>

<table class="mainouter" width="{{ $contentWidth }}" cellspacing="0" cellpadding="5" align="center">
	<tr><td id="nav_block" class="text" align="center">
@if(!$user)
			<a href="login.php"><font class="big"><b>{!! $lang['text_login'] !!}</b></font></a> / <a href="signup.php"><font class="big"><b>{!! $lang['text_signup'] !!}</b></font></a>
@else
{!! $menuHtml !!}

<table id="info_block" cellpadding="4" cellspacing="0" border="0" width="100%"><tr>
	<td><table width="100%" cellspacing="0" cellpadding="0" border="0"><tr>
		<td class="bottom" align="left">
            <span class="medium">
                {!! $lang['text_welcome_back'] !!}, {!! $username !!}
                [<form method="post" action="logout.php" style="display:inline">@csrf<button type="submit" style="background:none;border:none;padding:0;margin:0;color:inherit;cursor:pointer;text-decoration:underline;display:inline">{!! $lang['text_logout'] !!}</button></form>]
                [<a href="usercp.php">{!! $lang['text_user_cp'] !!}</a>]
                @if($isModerator) [<a href="staffpanel.php">{!! $lang['text_staff_panel'] !!}</a>] @endif
                @if($isSysop) [<a href="settings.php">{!! $lang['text_site_settings'] !!}</a>]@endif
                [<a href="torrents.php?inclbookmarked=1&amp;allsec=1&amp;incldead=0">{!! $lang['text_bookmarks'] !!}</a>]
                <font class = 'color_bonus'>{!! $lang['text_bonus'] !!}</font>[<a href="mybonus.php">{!! $lang['text_use'] !!}</a>]: {{ $seedbonus }}
                {!! $attendanceLink !!}
                <a href="medal.php">[{!! $medalLabel !!}]</a>
                <a href="task.php">[{!! $taskLabel !!}]</a>
                <font class = 'color_invite'>{!! $lang['text_invite'] !!}</font>[<a href="invite.php?id={{ $userId }}">{!! $lang['text_send'] !!}</a>]: {{ $invites }}({{ $pendingInviteCount }})
                {!! $managementSystemLink !!}
                <br />
	            <font class="color_ratio">{!! $lang['text_ratio'] !!}</font> {{ $ratio }}
                <font class='color_uploaded'>{!! $lang['text_uploaded'] !!}</font> {!! $uploaded !!}
                <font class='color_downloaded'> {!! $lang['text_downloaded'] !!}</font> {!! $downloaded !!}
                <font class='color_active'>{!! $lang['text_active_torrents'] !!}</font> <img class="arrowup" alt="Torrents seeding" title="{!! $lang['title_torrents_seeding'] !!}" src="pic/trans.gif" />{{ $activeseed }}  <img class="arrowdown" alt="Torrents leeching" title="{!! $lang['title_torrents_leeching'] !!}" src="pic/trans.gif" />{{ $activeleech }}&nbsp;&nbsp;
                <font class='color_connectable'>{!! $lang['text_connectable'] !!}</font>{!! $connectable !!} {!! $slotsDisplay !!}
                @if($hitAndRunEnabled)<font class='color_bonus'>H&R: </font> {!! $hitAndRunStatus !!} @endif
            </span>
        </td>
                @if($globalSearchEnabled)
        <td class="bottom" align="left" style="border: none">
            <form action="search.php" method="get" target="{{ $searchFormTarget }}">
                <div style="display: flex;align-items: center">
                    <div style="display: flex;flex-direction: column">
                        <div>
                            <span><input type="text" name="search" style="width: 80px;height: 12px" value="{!! $requestSearchEscaped !!}" placeholder="{!! $searchKeywordPlaceholder !!}"/></span>
                        </div>
                        <div>
                            <span>{!! $searchBoxAreaSelect !!}</span>
                        </div>
                    </div>
                    <div><input type="submit" value="{!! $globalSearchLabel !!}" style="width: 39px;white-space: break-spaces;padding: 0" /></div>
                </div>
            </form>
        </td>
                @endif
	<td class="bottom" align="right"><span class="medium">
{!! $staffIcons !!}
	</span></td>
	</tr></table></td>
</tr></table>

</td></tr>

<tr><td id="outer" align="center" class="outer" style="padding-top: 20px; padding-bottom: 20px">
{!! $messageAlerts !!}
@if($offlineMsg)
{!! $offlineMsgHtml !!}
@endif
@endif
