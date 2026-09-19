<h2 align="left">{{ __('legacy/offers.text_offers_section')}}</h2>
<div class="nx-box">
<p align="left"><b><span class="nx-size-5">{{ __('legacy/offers.text_rules') }}</span></b></p>
<div align="left"><ul>
<li>{{ __('legacy/offers.text_rule_one_one') }}{{ $list->rules->uploadClassName }}{{ __('legacy/offers.text_rule_one_two') }}{{ $list->rules->addofferClassName }}{{ __('legacy/offers.text_rule_one_three') }}</li>
@if ($list->rules->skipApprovedText !== null)
<li>{{ $list->rules->skipApprovedText }}</li>
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
<div align="center"><a href="?add_offer=1"><b>{{ __('legacy/offers.text_add_offer') }}</b></a></div>
@endif
<div align="center"><form method="get" action="?">{{ __('legacy/offers.text_search_offers') }}&nbsp;&nbsp;<input type="text" id="specialboxg" name="search" />&nbsp;&nbsp;<select name="category"><option value="0">{{ __('legacy/offers.select_show_all') }}</option>
@foreach ($list->categories as $cat)
<option value="{{ $cat->id }}">{{ $cat->name }}</option>
@endforeach
</select>&nbsp;&nbsp;<input type="submit" class="btn" value="{{ __('legacy/offers.submit_search') }}" /></form></div>
</div>
<br /><br />
@if ($list->table !== null)
@include('offers._table', ['table' => $list->table])
@else
{{ $list->emptyState }}
@endif
