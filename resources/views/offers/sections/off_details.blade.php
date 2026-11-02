@if ($off_details)
<div class="nx-off" id="top">
<p class="nx-off__back"><a href="{{ request()->getPathInfo() }}">&larr; {{ __('offers.text_back_to_offers') }}</a></p>
<section class="nx-idx-card nx-off__detail">
    <div class="nx-off__dhead">
        <h2 class="nx-off__h1">{{ $off_details->name }}</h2>
        <span class="nx-off__chip nx-off__chip--{{ $off_details->status->variant }}">{{ $off_details->status->label }}</span>
    </div>
    <p class="nx-off__meta">{{ __('offers.text_offered_by')}}{{ $off_details->offeredBy }}{{ $off_details->offerTime }}</p>
    @if ($off_details->showAllowRow)
    <div class="nx-off__actions"><form method="post" action="/web/offers/allow">@csrf<input type="hidden" value="{{ $off_details->id }}" name="offerid" /><input class="nx-btn nx-btn--primary" type="submit" value="{{ __('offers.submit_allow') }}" /></form><form method="post" action="/web/offers/finish">@csrf<input type="hidden" value="{{ $off_details->id }}" name="finish" /><input class="nx-btn" type="submit" value="{{ __('offers.submit_let_votes_decide') }}" /></form></div>
    @endif
    @if ($off_details->isPending)
    <table class="nx-off__votetable" role="presentation"><livewire:offer-vote :offer-id="$off_details->id" :can-against="$off_details->canAgainst" :yeah="$off_details->yeah" :against="$off_details->against" /></table>
    @endif
    @if ($off_details->allowedNote !== '')
    <div class="nx-off__note">{{ $off_details->allowedNote }}</div>
    @endif
    <div class="nx-off__actions">
        @if ($off_details->showEditDelete)
        <a class="nx-btn" href="{{ request()->getPathInfo() }}?id={{ $off_details->id }}&amp;edit_offer=1">{{ __('offers.text_edit_offer') }}</a>
        <a class="nx-btn" href="{{ request()->getPathInfo() }}?id={{ $off_details->id }}&amp;del_offer=1&amp;sure=0">{{ __('offers.text_delete_offer') }}</a>
        @endif
        <a class="nx-btn" href="/web/report?reportofferid={{ $off_details->id }}">{{ __('offers.report_offer') }}</a>
    </div>
</section>
@if ((string) $off_details->description !== '')
<section class="nx-idx-card">
    <h3 class="nx-off__ct">{{ __('offers.row_description') }}</h3>
    <div>{{ $off_details->description }}</div>
</section>
@endif
<section class="nx-idx-card" id="startcomments">
    <h3 class="nx-off__ct">{{ __('offers.text_comments') }} · {{ $off_details->commentCount }}</h3>
    @if (! $off_details->commentCount)
    <p class="nx-off__meta">{{ __('offers.text_no_comments') }}</p>
    @else
    {{ $off_details->pagerTop }}
    @endif
    <livewire:comment-section :parent-id="$off_details->id" type="offer" />
    @if ($off_details->commentCount)
    {{ $off_details->pagerBottom }}
    @endif
    <p class="nx-off__meta"><a href="/comment/add?pid={{ $off_details->id }}&amp;type=offer">{{ __('offers.text_add_comment') }}</a></p>
</section>
</div>
@endif
