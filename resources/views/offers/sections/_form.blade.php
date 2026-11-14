<div class="nx-off">
<h2 class="nx-off__h1">{{ $heading }}</h2>
<p class="nx-off__sub">{{ __('offers.text_red_star_required') }}<span class="text-nxm-danger">*</span>{{ __('offers.text_red_star_required_end') }}@if ($openToAll) {{ __('offers.text_offers_open_to_all') }}@endif</p>
<div class="nx-setcards">
    <form id="compose" method="post" name="compose" action="{{ $action }}" class="nx-setcards__form">
        @csrf
        @if ($hiddenId !== null)
        <input type="hidden" name="id" value="{{ $hiddenId }}" />
        @endif
        <section class="nx-ucard nx-setcard nx-setcard--wide" aria-labelledby="{{ $cardId }}">
            <h3 class="nx-setcard__title" id="{{ $cardId }}">{{ $heading }}</h3>
            <div class="nx-setrow">
                <label class="nx-setrow__label" for="offer-type">{{ __('offers.row_type') }}<span class="text-nxm-danger">*</span></label>
                <div class="nx-setrow__control">
                    <select id="offer-type" name="{{ $selectName }}">
                        @if ($selectPlaceholder !== null)
                        <option value="0">{{ $selectPlaceholder }}</option>
                        @endif
                        @foreach ($selectOptions as $opt)<option value="{{ $opt->id }}"{{ $opt->id === $selectSelected ? ' selected' : '' }}>{{ $opt->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="nx-setrow">
                <label class="nx-setrow__label" for="offer-name">{{ __('offers.row_title') }}<span class="text-nxm-danger">*</span></label>
                <div class="nx-setrow__control">
                    <input type="text" id="offer-name" name="name" value="{{ $nameValue }}" />
                </div>
            </div>
            <div class="nx-setrow">
                <label class="nx-setrow__label" for="offer-picture">{{ __('offers.row_post_or_photo') }}</label>
                <div class="nx-setrow__control">
                    <input type="text" id="offer-picture" name="picture" value="{{ $pictureValue }}" />
                    <p class="nx-setrow__hint">{{ __('offers.text_link_to_picture') }} <a href="/web/tags" title="What is Tag?">{{ __('offers.text_tag') }}</a> {{ __('offers.text_link_to_picture_end') }}</p>
                </div>
            </div>
            <div class="nx-setrow">
                <span class="nx-setrow__label">{{ __('offers.row_description') }}<span class="text-nxm-danger">*</span></span>
                <div class="nx-setrow__control">
                    <livewire:bbcode-editor form="compose" text="body" :content="$bodyContent" :compact="true" />
                </div>
            </div>
        </section>
        <div class="nx-savebar"><span class="nx-off__req">{{ __('offers.text_red_star_required') }}<span class="text-nxm-danger">*</span>{{ __('offers.text_red_star_required_end') }}</span><input id="qr" type="submit" class="nx-btn nx-btn--primary" value="{{ $submitLabel }}"  data-submit-once /></div>
    </form>
</div>
</div>
