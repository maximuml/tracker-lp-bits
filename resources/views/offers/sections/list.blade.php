<div class="nx-off">
<div class="nx-off__top">
    <div>
        <h2 class="nx-off__h1">{{ __('offers.text_offers_section')}}</h2>
        <p class="nx-off__sub">{{ __('offers.sub_offers') }}</p>
    </div>
    @if ($list->canAddOffer)
    <a class="nx-btn nx-btn--primary nx-off__add" href="{{ request()->getPathInfo() }}?add_offer=1">{{ __('offers.text_add_offer') }}</a>
    @endif
</div>
<details class="nx-off__rules">
    <summary>{{ __('offers.text_rules') }}</summary>
    <ul>
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
    </ul>
</details>
<section class="nx-idx-card nx-off__search">
    <form method="get" action="{{ request()->getPathInfo() }}">
        <label for="specialboxg">{{ __('offers.text_search_offers') }}</label>
        <input type="text" id="specialboxg" name="search" value="{{ request('search') }}" />
        <select name="category" aria-label="{{ __('offers.select_show_all') }}"><option value="0">{{ __('offers.select_show_all') }}</option>
@foreach ($list->categories as $cat)
<option value="{{ $cat->id }}"@if ((string) $cat->id === (string) request('category')) selected @endif>{{ $cat->name }}</option>
@endforeach
        </select>
        <input type="submit" class="nx-btn nx-btn--primary" value="{{ __('offers.submit_search') }}" />
    </form>
</section>
@if ($list->table !== null)
@include('components.offers.table', ['table' => $list->table])
@else
{{ $list->emptyState }}
@endif
</div>
