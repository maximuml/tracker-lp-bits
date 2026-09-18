@extends('layouts.legacy')

@section('title', __('legacy/donate.head_donation'))

@section('content')
@if ($thanks)
    {{ \App\Support\Frame::stdMessage(__('legacy/donate.std_success'), $successMessage, false) }}
@elseif (! $enabled)
    {{ \App\Support\Frame::stdMessage(__('legacy/donate.std_sorry'), __('legacy/donate.std_do_not_accept_donation'), true) }}
@elseif (! $showAny)
    {{ \App\Support\Frame::stdMessage(__('legacy/donate.std_error'), __('legacy/donate.std_no_donation_account_available'), false) }}
@else
    <h2>{{ __('legacy/donate.text_donate') }}</h2>
    <div>
        <div class="nx-text">{{ __('legacy/donate.text_donation_note') }}</div>
        @if ($showCustom)
            <div class="nx-text">{{ \App\Support\Format::formatComment($custom) }}</div>
        @endif
        @if ($showPaypal || $showAlipay)
            <div class="nx-row">
                @if ($showPaypal)
                    <div class="nx-text nx-grow">
                        <b>{{ __('legacy/donate.text_donate_with_paypal') }}</b><br /><br />
                        {{ __('legacy/donate.text_donate_paypal_note') }} <br />{{ __('legacy/donate.text_donate_paypal_note_two') }} <br />{{ __('legacy/donate.text_donate_paypal_note_three') }}
                        <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
                            <input type="hidden" name="cmd" value="_xclick">
                            <input type="hidden" name="business" value="{{ $paypal }}">
                            <input type="hidden" name="item_name" value="Donation to {{ $SITENAME }}">
                            <p align="center">
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
                            <input type="hidden" name="return" value="{{ $baseUrl }}/donate.php?do=thanks">
                            <input type="hidden" name="cancel_return" value="{{ $baseUrl }}/donate.php">
                            <p align="center">
                                <input type="image" src="pic/paypalbutton.gif" border="0" name="I1" alt="Make payments with PayPal">
                                <br /><br />
                            </p>
                        </form>
                    </div>
                @endif
                @if ($showAlipay)
                    <div class="nx-text nx-grow">
                        <b>{{ __('legacy/donate.text_donate_with_alipay') }}</b><br /><br />
                        <form action="https://www.alipay.com/trade/fast_pay.htm" method="get">
                            {{ __('legacy/donate.text_donate_alipay_note_one') }} <br />{{ __('legacy/donate.text_donate_alipay_note_one_two') }} <br />{{ __('legacy/donate.text_donate_alipay_note_one_three') }}<b>{{ $alipay }}</b>{{ __('legacy/donate.text_donate_alipay_note_two') }} <br />{{ __('legacy/donate.text_donate_alipay_note_two_two') }}
                            <br /><br /><br /><br /><br />
                            <p align="center">
                                <input type="image" src="pic/alipaybutton.gif" border="0" name="I2" alt="Make payments with Alipay" />
                                <br /><br />
                            </p>
                        </form>
                    </div>
                @endif
            </div>
        @endif
        <div class="nx-text">
            {{ __('legacy/donate.text_after_donation_note_one') }}
            <a href="sendmessage.php?receiver={{ $accountantId }}"><font class="striking"><b>{{ __('legacy/donate.text_send_us') }}</b></font></a>
            {{ __('legacy/donate.text_after_donation_note_two') }} <b>{{ __('legacy/donate.text_transaction_information') }}</b>{{ __('legacy/donate.text_after_donation_note_two_end') }}
        </div>
    </div>
@endif
@endsection
