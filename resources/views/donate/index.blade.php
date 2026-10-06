@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', __('legacy/donate.head_donation'))

@section('content')
@if ($thanks)
    <x-std-message :heading="__('legacy/donate.std_success')" :htmlstrip="false">{{ __('legacy/donate.std_donation_success_note_one') }}<a href="/web/sendmessage?receiver={{ $accountantId }}"><b>{{ __('legacy/donate.std_here') }}</b></a>{{ __('legacy/donate.std_donation_success_note_two') }}</x-std-message>
@elseif (! $enabled)
    <x-std-message :heading="__('legacy/donate.std_sorry')" :text="__('legacy/donate.std_do_not_accept_donation')" />
@elseif (! $showAny)
    <x-std-message :heading="__('legacy/donate.std_error')" :text="__('legacy/donate.std_no_donation_account_available')" :htmlstrip="false" />
@else
    <section class="nx-idx-card">
    <h1>{{ __('legacy/donate.text_donate') }}</h1>
    <div>
        <div class="p-[10pt]">{{ __('legacy/donate.text_donation_note') }}</div>
        @if ($showCustom)
            <div class="p-[10pt]">{{ \App\Support\Format::formatComment($custom) }}</div>
        @endif
        @if ($showPaypal || $crypto !== [])
            <div class="flex items-start">
                @if ($showPaypal)
                    <div class="p-[10pt] grow">
                        <b>{{ __('legacy/donate.text_donate_with_paypal') }}</b><br /><br />
                        {{ __('legacy/donate.text_donate_paypal_note') }} <br />{{ __('legacy/donate.text_donate_paypal_note_two') }} <br />{{ __('legacy/donate.text_donate_paypal_note_three') }}
                        <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
                            <input type="hidden" name="cmd" value="_xclick">
                            <input type="hidden" name="business" value="{{ $paypal }}">
                            <input type="hidden" name="item_name" value="Donation to {{ $SITENAME }}">
                            <p class="text-center">
                                <br />
                                {{ __('legacy/donate.text_select_donation_amount') }}<br />
                                <select name="amount">
                                    <option value="" selected>{{ __('legacy/donate.select_choose_donation_amount') }}</option>
                                    @foreach ([0, 1, 5, 10, 15, 20, 30, 40, 50, 60, 100, 300] as $amount)
                                        @if ($amount == 0)
                                            <option value="">{{ __('legacy/donate.select_other_donation_amount') }}</option>
                                        @else
                                            <option value="{{ number_format($amount, 2) }}">{{ __('legacy/donate.text_usd_mark') }}{{ number_format($amount, 2) }}{{ __('legacy/donate.text_donation') }}</option>
                                        @endif
                                    @endforeach
                                </select>
                            </p>
                            <input type="hidden" name="image_url" value="">
                            <input type="hidden" name="shipping" value="0">
                            <input type="hidden" name="currency_code" value="USD">
                            <input type="hidden" name="return" value="{{ $baseUrl }}/web/donate?do=thanks">
                            <input type="hidden" name="cancel_return" value="{{ $baseUrl }}/web/donate">
                            <p class="text-center">
                                <input type="image" src="pic/paypalbutton.gif" name="I1" alt="Make payments with PayPal">
                                <br /><br />
                            </p>
                        </form>
                    </div>
                @endif
                @if ($crypto !== [])
                    <div class="p-[10pt] grow">
                        <b>{{ __('legacy/donate.text_donate_with_crypto') }}</b><br /><br />
                        {{ __('legacy/donate.text_donate_crypto_note') }}<br /><br />
                        @foreach ($crypto as $wallet)
                            <p>
                                <img src="{{ $wallet['qr'] }}" width="160" height="160" alt="{{ $wallet['coin'] }}"><br />
                                <b>{{ $wallet['coin'] }}:</b>&nbsp;<code>{{ $wallet['address'] }}</code>
                            </p>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
        <div class="p-[10pt]">
            {{ __('legacy/donate.text_after_donation_note_one') }}
            <a href="/web/sendmessage?receiver={{ $accountantId }}"><span class="striking"><b>{{ __('legacy/donate.text_send_us') }}</b></span></a>
            {{ __('legacy/donate.text_after_donation_note_two') }} <b>{{ __('legacy/donate.text_transaction_information') }}</b>{{ __('legacy/donate.text_after_donation_note_two_end') }}
        </div>
    </div>
</section>
@endif
@endsection
