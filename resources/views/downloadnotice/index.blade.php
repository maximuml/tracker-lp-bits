@extends('layouts.legacy')

@section('title', $title ?? (__('legacy/downloadnotice.head_download_notice')))

@section('content')
<h2>{{ $title }}</h2>
<div>
<div class="nx-text"><p>{{ $note }}</p></div>
<div class="nx-row">
@if (! empty($showrationotice))
<div class="nx-text nx-grow">
<h3>{{ __('legacy/downloadnotice.text_this_is_private_tracker')}}</h3>
<p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/downloadnotice.text_private_tracker_note_one')))<i>({{ __('legacy/downloadnotice.text_learn_more')}}<a class="faqlink" href="{{ NEXUSWIKIURL ?? '' }}/Private Tracker" target="_blank">{{ __('legacy/downloadnotice.text_nexuswiki')}}</a>)</i></p>
<p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/downloadnotice.text_private_tracker_note_two')))<i>({{ __('legacy/downloadnotice.text_see_ratio')}}<a class="faqlink" href="faq.php#id23" target="_blank">{{ __('legacy/downloadnotice.text_faq')}}</a>)</i></p>
<p>{{ __('legacy/downloadnotice.text_private_tracker_note_three')}}</p>
<img src="pic/ratio.png" alt="ratio" />
<p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/downloadnotice.text_private_tracker_note_four')))</p>
</div>
@endif
@if (! empty($showclientnotice))
<div class="nx-text nx-grow">
<h3>{{ __('legacy/downloadnotice.text_use_allowed_clients')}}</h3>
<p>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/downloadnotice.text_allowed_clients_note_one')))</p>
<p>{{ __('legacy/downloadnotice.text_allowed_clients_note_two')}}<a class='faqlink' href='faq.php#id29' target='_blank'>{{ __('legacy/downloadnotice.text_faq')}}</a>{{ __('legacy/downloadnotice.text_allowed_clients_note_three')}}</p>
<div class="nx-row">
<div class="nx-grow nx-center">
<a href="https://www.qbittorrent.org/download" target="_blank" title="{{ __('legacy/downloadnotice.title_download')}}qBittorrent"><img src="pic/qbittorrent.png" alt="qBittorrent"  width="128" height="128" /></a>
</div>
<div class="nx-grow nx-center">
<a href="https://transmissionbt.com/download/" target="_blank" title="{{ __('legacy/downloadnotice.title_download')}}Transmission"><img src="pic/transmission.png" alt="Transmission"  width="128" height="128" /></a>
</div>
</div>
<div class="nx-row">
<div class="nx-grow nx-center">
<div class="big"><a href="https://www.qbittorrent.org/download" target="_blank" title="{{ __('legacy/downloadnotice.title_download')}}qBittorrent"><b>qBittorrent</b></a></div>
<div>{{ __('legacy/downloadnotice.text_for')}}Windows, Linux, Mac OS</div>
</div>
<div class="nx-grow nx-center">
<div class="big"><a href="https://transmissionbt.com/download/" target="_blank" title="{{ __('legacy/downloadnotice.title_download')}}Transmission"><b>Transmission</b></a></div>
<div>{{ __('legacy/downloadnotice.text_for')}}Windows, Linux, Mac OS</div>
</div>
</div>
</div>
@endif
</div>
@if (! empty($torrentid))
<div class="nx-text">
<form action="?" method="post"><p>{{ __('legacy/downloadnotice.text_for_more_information_read')}}<a class="faqlink" href="rules.php" target="_blank">{{ __('legacy/downloadnotice.text_rules')}}</a>{{ __('legacy/downloadnotice.text_and')}}<a class="faqlink" href="faq.php" target="_blank">{{ __('legacy/downloadnotice.text_faq')}}</a><br />
<input type="hidden" name="id" value="{{ $torrentid }}" />
<input type="hidden" name="type" value="{{ (string) $type }}" />
<input type="checkbox" name="hidenotice" id="hidenotice" value="1"@if (! empty($forcecheck)) disabled="disabled"@else checked="checked"@endif /><label for="hidenotice">{{ $noticenexttime }}</label>
@if (! empty($forcecheck))
<br /><input type="checkbox" name="letmedown" id="letmedown" value="{{ (string) $type }}" /><label for="letmedown"><span class="big">{{ __('legacy/downloadnotice.text_let_me_download')}}</span></label>
@endif
</p>
<div><input type="submit" name="submit" id="continuedownload" class="dlnotice-submit" value="{{ __('legacy/downloadnotice.submit_download_the_torrent')}}"@if (! empty($forcecheck)) disabled="disabled"@endif /></div>
</form>
</div>
@endif
</div>
@endsection
