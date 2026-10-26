<div class="nx-bshop">
    <h1 class="nx-bshop__title">
        {{ $shop->sitename }}{{ __('mybonus.text_karma_system') }}
    </h1>

    @if ((string) $shop->msg !== '')
        <x-alert type="success">{{ $shop->msg }}</x-alert>
    @endif

    <div class="nx-bshop__summary">
        <div class="nx-ucard">
            <div class="nx-ucard__title">{{ __('mybonus.text_balance') }}</div>
            <strong class="nx-bshop__summary-value">{{ $shop->bonus }}</strong>
            <small class="nx-bshop__summary-sub">{{ __('mybonus.text_karma_points_sub') }}</small>
        </div>
        @if ($info !== null)
            <div class="nx-ucard">
                <div class="nx-ucard__title">{{ __('mybonus.text_earning') }}</div>
                <strong class="nx-bshop__summary-value">+{{ $info->currentSeedBonus }}</strong>
                <small class="nx-bshop__summary-sub">{{ __('mybonus.text_per_hour_sub') }}</small>
            </div>
        @endif
        <div class="nx-ucard nx-bshop__history">
            <div class="nx-ucard__title">{{ __('mybonus.text_history') }}</div>
            <a href="/web/bonus-log">{{ __('mybonus.text_bonus_log') }}</a>
            <small class="nx-bshop__summary-sub">{{ $shop->lockText }}</small>
        </div>
    </div>

    <nav class="nx-bshop__chips" aria-label="{{ __('mybonus.text_shop_sections') }}">
        @foreach ([
            'upload' => __('mybonus.group_upload'),
            'download' => __('mybonus.group_download'),
            'invites' => __('mybonus.group_invites'),
            'profile' => __('mybonus.group_profile'),
            'gifts' => __('mybonus.group_gifts'),
        ] as $group => $label)
            @if (collect($shop->items)->where('group', $group)->isNotEmpty())
                <a class="nx-bshop__chip" href="/web/mybonus#bonus-{{ $group }}">{{ $label }}</a>
            @endif
        @endforeach
    </nav>

    @foreach ([
        'upload' => __('mybonus.group_upload'),
        'download' => __('mybonus.group_download'),
        'invites' => __('mybonus.group_invites'),
        'profile' => __('mybonus.group_profile'),
        'gifts' => __('mybonus.group_gifts'),
    ] as $group => $label)
        @if (collect($shop->items)->where('group', $group)->isNotEmpty())
            <section class="nx-bshop__group" aria-labelledby="bonus-{{ $group }}">
                <h2 id="bonus-{{ $group }}" class="nx-bshop__group-title">{{ $label }}</h2>
                <div class="nx-bshop__grid">
                    @foreach ($shop->items as $item)
                        @if ($item->group === $group)
                            <article class="nx-ucard nx-bshop__item{{ in_array($item->art, ['gift_1', 'gift_2'], true) ? ' nx-bshop__item--wide' : '' }}" aria-labelledby="bonus-item-{{ $item->index }}">
                                <h3 id="bonus-item-{{ $item->index }}">{{ $item->name }}</h3>
                                <p class="nx-bshop__desc">{{ $item->description }}</p>

                                <form id="bx{{ $item->index }}" action="/web/mybonus/exchange" method="post">
                                    @csrf
                                    <input type="hidden" name="option" value="{{ $item->index }}">

                                    @if ($item->art === 'title')
                                        <div class="nx-bshop__field">
                                            <label for="bonustitle">{{ __('mybonus.text_special_title') }}</label>
                                            <input id="bonustitle" type="text" name="title" maxlength="30">
                                        </div>
                                    @elseif ($item->art === 'gift_1')
                                        <div class="nx-bshop__fields">
                                            <div class="nx-bshop__field">
                                                <label for="giftusername">{{ __('mybonus.text_username') }}</label>
                                                <input id="giftusername" type="text" name="username" maxlength="24">
                                            </div>
                                            <div class="nx-bshop__field">
                                                <label for="giftcustom">{{ __('mybonus.text_to_be_given') }}</label>
                                                <input id="giftcustom" type="number" name="bonusgift" min="100">
                                            </div>
                                            <div class="nx-bshop__field">
                                                <label for="giftmessage">{{ __('mybonus.text_message') }}</label>
                                                <input id="giftmessage" type="text" name="message" maxlength="100">
                                            </div>
                                        </div>
                                    @elseif ($item->art === 'gift_2')
                                        <div class="nx-bshop__fields">
                                            <div class="nx-bshop__field">
                                                <label for="ratiocharity">{{ __('mybonus.text_ratio_below') }}</label>
                                                <select id="ratiocharity" name="ratiocharity">
                                                    <option value="0.1"> 0.1</option>
                                                    <option value="0.2"> 0.2</option>
                                                    <option value="0.3" selected="selected"> 0.3</option>
                                                    <option value="0.4"> 0.4</option>
                                                    <option value="0.5"> 0.5</option>
                                                    <option value="0.6"> 0.6</option>
                                                    <option value="0.7"> 0.7</option>
                                                    <option value="0.8"> 0.8</option>
                                                </select>
                                            </div>
                                            <div class="nx-bshop__field">
                                                <label for="charityselect">{{ __('mybonus.text_to_be_given') }}</label>
                                                <select id="charityselect" name="bonuscharity">
                                                    <option value="1000"> 1,000</option>
                                                    <option value="2000"> 2,000</option>
                                                    <option value="3000" selected="selected"> 3,000</option>
                                                    <option value="5000"> 5,000</option>
                                                    <option value="8000"> 8,000</option>
                                                    <option value="10000"> 10,000</option>
                                                    <option value="20000"> 20,000</option>
                                                    <option value="50000"> 50,000</option>
                                                </select>
                                            </div>
                                        </div>
                                    @elseif ($item->art === 'cancel_hr')
                                        <div class="nx-bshop__field">
                                            <label for="bonushrid">{{ __('mybonus.text_cancel_hr_label') }}</label>
                                            <input id="bonushrid" type="number" name="hr_id">
                                        </div>
                                    @endif

                                    @if ($item->giftTax !== null)
                                        <p class="nx-bshop__tax">{{ $item->giftTax['charges'] }}{{ $item->giftTax['amounts'] }}{{ $item->giftTax['rest'] }}</p>
                                    @endif

                                    @if ($item->pointsShort !== null)
                                        <progress class="nx-bshop__progress" max="{{ $item->price }}" value="{{ $shop->balance }}" aria-label="{{ $item->trade->note }}"></progress>
                                        <p class="nx-bshop__need">{{ $item->trade->note }}</p>
                                    @elseif ($item->trade->note !== null)
                                        <p class="nx-bshop__need">{{ $item->trade->note }}</p>
                                    @endif

                                    <div class="nx-bshop__buy">
                                        <span class="nx-bshop__price">
                                            {{ $item->pointsLabel }}@if ($item->pointsLabel2 !== null) {{ $item->pointsLabel2 }}@endif
                                            <small>{{ __('mybonus.text_pts') }}</small>
                                        </span>
                                        <input type="submit" name="submit" class="nx-btn{{ $item->trade->disabled ? '' : ' nx-btn--primary' }}" @disabled($item->trade->disabled) data-submit-once value="{{ $item->trade->label }}">
                                    </div>
                                </form>
                            </article>
                        @endif
                    @endforeach
                </div>
            </section>
        @endif
    @endforeach

</div>
