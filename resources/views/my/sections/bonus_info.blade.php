{{-- "What is karma" info block — replaces the second ob_start/echo block
     of BonusPageService::buildInfoSection. The legacy layout table here
     was a pure wrapper (one header cell + one text cell) — now a div.
     The text_bonus_formula_* / text_howto_get_karma_* lang strings carry
     their own markup (li/img/ul boundaries), so they render via
     SafeHtml::fromUntrustedHtml. --}}
<div class="mx-auto w-[97%] bg-nxm-panel-bg">
<div class="nx-colhead p-1 text-center"><span class="big">{{ __('legacy/mybonus.text_what_is_karma') }}</span></div>
<div class="text p-[3px]">
<h1>{{ __('legacy/mybonus.text_get_by_seeding') }}</h1>
<ul>
@if ($info->perseedingBonus > 0)
<li>{{ $info->perseedingBonus }}{{ __('legacy/mybonus.text_point') }}{{ \App\Support\Strings::addS($info->perseedingBonus) }}{{ __('legacy/mybonus.text_for_seeding_torrent') }}{{ $info->maxseedingBonus }}{{ __('legacy/mybonus.text_torrent') }}{{ \App\Support\Strings::addS($info->maxseedingBonus) }})</li>
@endif
<li>{{ view('my.sections._bonus-formula', ['info' => $info]) }}</li>
@if ($info->minSizeLine !== null)
<li>{{ $info->minSizeLine }}</li>
@endif
@if ($info->donortimesBonus)
<li>{{ __('legacy/mybonus.text_donors_always_get') }}{{ $info->donortimesBonus }}{{ __('legacy/mybonus.text_times_of_bonus') }}</li>
@endif
</ul>
<div class="text-center">{{ __('legacy/mybonus.text_you_are_currently_getting') }}{{ $info->currentSeedBonus }}{{ __('legacy/mybonus.text_point') }}{{ \App\Support\Strings::addS((float) $info->currentSeedBonus) }}{{ __('legacy/mybonus.text_per_hour') }} (A = {{ $info->aFactor }})</div>
<div class="mx-auto w-[400px]"><span class="block h-[15px] rounded-[3px] bg-nxm-surface-alt"><img class="{{ $info->loadbarClass }}" src="pic/trans.gif" alt="{{ $info->percentLabel }}%" /></span></div>
@if ($info->officialAdditionFactor !== null)
<h1>{{ __('legacy/mybonus.text_get_by_seeding_official') }}</h1>
<ul>
<li>{{ __('legacy/mybonus.official_calculate_method') }}</li>
<li>{{ __('legacy/mybonus.official_tag_bonus_additional_factor') }}{{ $info->officialAdditionFactor }}</li>
</ul>
@endif
@if ($info->haremAdditionFactor !== null)
<h1>{{ __('legacy/mybonus.text_get_by_harem') }}</h1>
<ul>
<li>{{ __('legacy/mybonus.harem_additional_desc') }}<a href="invite.php?id={{ $info->userId }}" class="altlink" target="_blank">{{ __('legacy/mybonus.text_here') }}</a></li>
<li>{{ __('legacy/mybonus.harem_additional_factor') }}{{ $info->haremAdditionFactor }}</li>
<li>{{ __('legacy/mybonus.harem_additional_note') }}</li>
</ul>
@endif
<h1>{{ __('legacy/mybonus.text_bonus_summary') }}</h1>
<div>{{ $info->summaryTable }}</div>
<h1>{{ __('legacy/mybonus.text_other_things_get_bonus') }}</h1>
<ul>
@if ($info->uploadtorrentBonus > 0)
<li>{{ __('legacy/mybonus.text_upload_torrent') }}{{ $info->uploadtorrentBonus }}{{ __('legacy/mybonus.text_point') }}{{ \App\Support\Strings::addS($info->uploadtorrentBonus) }}</li>
@endif
@if ($info->starttopicBonus > 0)
<li>{{ __('legacy/mybonus.text_start_topic') }}{{ $info->starttopicBonus }}{{ __('legacy/mybonus.text_point') }}{{ \App\Support\Strings::addS($info->starttopicBonus) }}</li>
@endif
@if ($info->makepostBonus > 0)
<li>{{ __('legacy/mybonus.text_make_post') }}{{ $info->makepostBonus }}{{ __('legacy/mybonus.text_point') }}{{ \App\Support\Strings::addS($info->makepostBonus) }}</li>
@endif
@if ($info->addcommentBonus > 0)
<li>{{ __('legacy/mybonus.text_add_comment') }}{{ $info->addcommentBonus }}{{ __('legacy/mybonus.text_point') }}{{ \App\Support\Strings::addS($info->addcommentBonus) }}</li>
@endif
@if ($info->pollvoteBonus > 0)
<li>{{ __('legacy/mybonus.text_poll_vote') }}{{ $info->pollvoteBonus }}{{ __('legacy/mybonus.text_point') }}{{ \App\Support\Strings::addS($info->pollvoteBonus) }}</li>
@endif
@if ($info->offervoteBonus > 0)
<li>{{ __('legacy/mybonus.text_offer_vote') }}{{ $info->offervoteBonus }}{{ __('legacy/mybonus.text_point') }}{{ \App\Support\Strings::addS($info->offervoteBonus) }}</li>
@endif
@if ($info->saythanksBonus > 0)
<li>{{ __('legacy/mybonus.text_say_thanks') }}{{ $info->saythanksBonus }}{{ __('legacy/mybonus.text_point') }}{{ \App\Support\Strings::addS($info->saythanksBonus) }}</li>
@endif
@if ($info->receivethanksBonus > 0)
<li>{{ __('legacy/mybonus.text_receive_thanks') }}{{ $info->receivethanksBonus }}{{ __('legacy/mybonus.text_point') }}{{ \App\Support\Strings::addS($info->receivethanksBonus) }}</li>
@endif
</ul>
<h1>{{ __('legacy/mybonus.text_howto_get_karma_four') }}</h1>
<ul>
@if ($info->ratiolimitBonus > 0)
<li>{{ __('legacy/mybonus.text_user_with_ratio_above') }}{{ $info->ratiolimitBonus }}{{ __('legacy/mybonus.text_and_uploaded_amount_above') }}{{ $info->dlamountlimitBonus }}{{ __('legacy/mybonus.text_cannot_exchange_uploading') }}</li>
@endif
<li>{{ __('legacy/mybonus.text_howto_get_karma_five') }}<br />{{ __('legacy/mybonus.text_howto_get_karma_five_tail') }}{{ $info->uploadtorrentBonus }}{{ __('legacy/mybonus.text_point') }}{{ \App\Support\Strings::addS($info->uploadtorrentBonus) }}.</li>
<li>{{ __('legacy/mybonus.text_howto_get_karma_six') }}</li>
<li>{{ __('legacy/mybonus.text_staff_can_give') }}</li>
</ul>
</div>
</div>
