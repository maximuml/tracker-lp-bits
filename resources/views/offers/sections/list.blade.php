<section class="nx-idx-card">
<h2>{{ __('legacy/offers.text_offers_section')}}</h2>
<div>
<p><b><span class="text-[18px]">{{ __('legacy/offers.text_rules') }}</span></b></p>
<div><ul>
<li>{{ __('legacy/offers.text_rule_one_one') }}{{ $list->rules->uploadClassName }}{{ __('legacy/offers.text_rule_one_two') }}{{ $list->rules->addofferClassName }}{{ __('legacy/offers.text_rule_one_three') }}</li>
@if ($list->rules->skipApprovedCount !== null)
<li>{{ __('legacy/offers.text_rule_skip_offer_pre') }}<b>{{ $list->rules->skipApprovedCount }}</b>{{ __('legacy/offers.text_rule_skip_offer_post') }}</li>
@endif
<li>{{ __('legacy/offers.text_rule_two_one') }}<b>{{ $list->rules->minVotes }}</b>{{ __('legacy/offers.text_rule_two_two') }}</li>
@if ($list->rules->showVoteTimeout)
<li>{{ __('legacy/offers.text_rule_three_one') }}<b>{{ $list->rules->voteTimeoutHours }}</b>{{ __('legacy/offers.text_rule_three_two') }}</li>
@endif
@if ($list->rules->showUpTimeout)
<li>{{ __('legacy/offers.text_rule_four_one') }}<b>{{ $list->rules->upTimeoutHours }}</b>{{ __('legacy/offers.text_rule_four_two') }}</li>
@endif
</ul></div>
@if ($list->canAddOffer)
<div class="text-center"><a href="{{ request()->getPathInfo() }}?add_offer=1"><b>{{ __('legacy/offers.text_add_offer') }}</b></a></div>
@endif
<div class="text-center"><form method="get" action="{{ request()->getPathInfo() }}"><label for="specialboxg">{{ __('legacy/offers.text_search_offers') }}</label>&nbsp;&nbsp;<input type="text" id="specialboxg" name="search" />&nbsp;&nbsp;<select name="category" aria-label="{{ __('legacy/offers.select_show_all') }}"><option value="0">{{ __('legacy/offers.select_show_all') }}</option>
@foreach ($list->categories as $cat)
<option value="{{ $cat->id }}">{{ $cat->name }}</option>
@endforeach
</select>&nbsp;&nbsp;<input type="submit" class="btn" value="{{ __('legacy/offers.submit_search') }}" /></form></div>
</div>
</section>
<br /><br />
@if ($list->table !== null)
@include('offers._table', ['table' => $list->table])
@else
{{ $list->emptyState }}
@endif
