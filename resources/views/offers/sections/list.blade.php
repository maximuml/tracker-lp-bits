<section class="nx-idx-card">
<h2>{{ __('offers.text_offers_section')}}</h2>
<div>
<p><b><span class="text-[18px]">{{ __('offers.text_rules') }}</span></b></p>
<div><ul>
<li>{{ __('offers.text_rule_one_one') }}{{ $list->rules->uploadClassName }}{{ __('offers.text_rule_one_two') }}{{ $list->rules->addofferClassName }}{{ __('offers.text_rule_one_three') }}</li>
@if ($list->rules->skipApprovedCount !== null)
<li>{{ __('offers.text_rule_skip_offer_pre') }}<b>{{ $list->rules->skipApprovedCount }}</b>{{ __('offers.text_rule_skip_offer_post') }}</li>
@endif
<li>{{ __('offers.text_rule_two_one') }}<b>{{ $list->rules->minVotes }}</b>{{ __('offers.text_rule_two_two') }}</li>
@if ($list->rules->showVoteTimeout)
<li>{{ __('offers.text_rule_three_one') }}<b>{{ $list->rules->voteTimeoutHours }}</b>{{ __('offers.text_rule_three_two') }}</li>
@endif
@if ($list->rules->showUpTimeout)
<li>{{ __('offers.text_rule_four_one') }}<b>{{ $list->rules->upTimeoutHours }}</b>{{ __('offers.text_rule_four_two') }}</li>
@endif
</ul></div>
@if ($list->canAddOffer)
<div class="text-center"><a href="{{ request()->getPathInfo() }}?add_offer=1"><b>{{ __('offers.text_add_offer') }}</b></a></div>
@endif
<div class="text-center"><form method="get" action="{{ request()->getPathInfo() }}"><label for="specialboxg">{{ __('offers.text_search_offers') }}</label>&nbsp;&nbsp;<input type="text" id="specialboxg" name="search" />&nbsp;&nbsp;<select name="category" aria-label="{{ __('offers.select_show_all') }}"><option value="0">{{ __('offers.select_show_all') }}</option>
@foreach ($list->categories as $cat)
<option value="{{ $cat->id }}">{{ $cat->name }}</option>
@endforeach
</select>&nbsp;&nbsp;<input type="submit" class="btn" value="{{ __('offers.submit_search') }}" /></form></div>
</div>
</section>
<br /><br />
@if ($list->table !== null)
@include('offers._table', ['table' => $list->table])
@else
{{ $list->emptyState }}
@endif
