@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', $title ?? (__('downloadnotice.head_download_notice')))

@section('content')
<section class="nx-idx-card">
<h2>{{ $title }}</h2>
<div>
<div class="p-[10pt]"><p>{{ $note }}</p></div>
<div class="flex items-start">
@if (! empty($showrationotice))
<div class="p-[10pt] grow">
<h3>{{ __('downloadnotice.text_this_is_private_tracker')}}</h3>
<p>{{ __('downloadnotice.text_private_tracker_note_one') }} <b>{{ __('downloadnotice.text_exclusive') }}</b> {{ __('downloadnotice.text_private_tracker_note_one_end') }}<i>({{ __('downloadnotice.text_learn_more')}}<a class="faqlink" href="{{ NEXUSWIKIURL ?? '' }}/Private Tracker" target="_blank">{{ __('downloadnotice.text_nexuswiki')}}</a>)</i></p>
<p>{{ __('downloadnotice.text_private_tracker_note_two') }} <b>{{ __('downloadnotice.text_must') }}</b> {{ __('downloadnotice.text_private_tracker_note_two_two') }} <b>{{ __('downloadnotice.text_ratio') }}</b>{{ __('downloadnotice.text_private_tracker_note_two_three') }} <span class='striking'>{{ __('downloadnotice.text_lose_membership') }}</span> {{ __('downloadnotice.text_private_tracker_note_two_end') }}<i>({{ __('downloadnotice.text_see_ratio')}}<a class="faqlink" href="/web/faq#id23" target="_blank">{{ __('downloadnotice.text_faq')}}</a>)</i></p>
<p>{{ __('downloadnotice.text_private_tracker_note_three')}}</p>
<img src="pic/ratio.png" alt="ratio" />
<p><b>{{ __('downloadnotice.text_private_tracker_note_four') }}</b><br /> {{ __('downloadnotice.text_private_tracker_note_four_two') }} <b>{{ __('downloadnotice.text_keep_seeding') }}</b> {{ __('downloadnotice.text_private_tracker_note_four_end') }}</p>
</div>
@endif
@if (! empty($showclientnotice))
<div class="p-[10pt] grow">
<h3>{{ __('downloadnotice.text_use_allowed_clients')}}</h3>
<p>{{ __('downloadnotice.text_allowed_clients_note_one') }} <b>{{ __('downloadnotice.text_only') }}</b> {{ __('downloadnotice.text_allowed_clients_note_one_two') }} <span class='striking'>{{ __('downloadnotice.text_banned') }}</span> {{ __('downloadnotice.text_allowed_clients_note_one_end') }}</p>
<p>{{ __('downloadnotice.text_allowed_clients_note_two')}}<a class='faqlink' href='/web/faq#id29' target='_blank'>{{ __('downloadnotice.text_faq')}}</a>{{ __('downloadnotice.text_allowed_clients_note_three')}}</p>
<div class="flex items-start">
<div class="grow text-center">
<a href="https://www.qbittorrent.org/download" target="_blank" title="{{ __('downloadnotice.title_download')}}qBittorrent"><img src="pic/qbittorrent.png" alt="qBittorrent"  width="128" height="128" /></a>
</div>
<div class="grow text-center">
<a href="https://transmissionbt.com/download/" target="_blank" title="{{ __('downloadnotice.title_download')}}Transmission"><img src="pic/transmission.png" alt="Transmission"  width="128" height="128" /></a>
</div>
</div>
<div class="flex items-start">
<div class="grow text-center">
<div class="big"><a href="https://www.qbittorrent.org/download" target="_blank" title="{{ __('downloadnotice.title_download')}}qBittorrent"><b>qBittorrent</b></a></div>
<div>{{ __('downloadnotice.text_for')}}Windows, Linux, Mac OS</div>
</div>
<div class="grow text-center">
<div class="big"><a href="https://transmissionbt.com/download/" target="_blank" title="{{ __('downloadnotice.title_download')}}Transmission"><b>Transmission</b></a></div>
<div>{{ __('downloadnotice.text_for')}}Windows, Linux, Mac OS</div>
</div>
</div>
</div>
@endif
</div>
@if (! empty($torrentid))
<div class="p-[10pt]">
<form action="/web/torrents/download-notice" method="post">@csrf<p>{{ __('downloadnotice.text_for_more_information_read')}}<a class="faqlink" href="/web/rules" target="_blank">{{ __('downloadnotice.text_rules')}}</a>{{ __('downloadnotice.text_and')}}<a class="faqlink" href="/web/faq" target="_blank">{{ __('downloadnotice.text_faq')}}</a><br />
<input type="hidden" name="id" value="{{ $torrentid }}" />
<input type="hidden" name="type" value="{{ (string) $type }}" />
<input type="checkbox" name="hidenotice" id="hidenotice" value="1"@if (! empty($forcecheck)) disabled="disabled"@else checked="checked"@endif /><label for="hidenotice">{{ $noticenexttime }}</label>
@if (! empty($forcecheck))
<br /><input type="checkbox" name="letmedown" id="letmedown" value="{{ (string) $type }}" /><label for="letmedown"><span class="big">{{ __('downloadnotice.text_let_me_download')}}</span></label>
@endif
</p>
<div><input type="submit" name="submit" id="continuedownload" class="dlnotice-submit" value="{{ __('downloadnotice.submit_download_the_torrent')}}"@if (! empty($forcecheck)) disabled="disabled"@endif /></div>
</form>
</div>
@endif
</div>
</section>
@endsection
