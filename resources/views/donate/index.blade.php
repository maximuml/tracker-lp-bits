@extends('layouts.legacy')

@section('title', $lang_donate['head_donation'] ?? 'Donation')

@section('content')
@php
$baseUrl = \App\Support\Url::schemeAndHost(false);
$successMessage = ($lang_donate['std_donation_success_note_one'] ?? '')
    . '<a href="sendmessage.php?receiver=' . $accountantId . '"><b>' . ($lang_donate['std_here'] ?? 'here') . '</b></a>'
    . ($lang_donate['std_donation_success_note_two'] ?? '');
@endphp

@if ($thanks)
    {!! \App\Support\Frame::stdMessage($lang_donate['std_success'] ?? 'Success', $successMessage, false) !!}
@elseif (! $enabled)
    {!! \App\Support\Frame::stdMessage($lang_donate['std_sorry'] ?? 'Sorry', $lang_donate['std_do_not_accept_donation'] ?? 'We do not accept donations.', true) !!}
@elseif (! $showAny)
    {!! \App\Support\Frame::stdMessage($lang_donate['std_error'] ?? 'Error', $lang_donate['std_no_donation_account_available'] ?? 'No donation account available.', false) !!}
@else
    <h2>{{ $lang_donate['text_donate'] }}</h2>
    <table width="100%">
        <tr><td class="text" colspan="2" align="left">{{ $lang_donate['text_donation_note'] }}</td></tr>
        @if ($showCustom)
            <tr><td class="text" align="left" colspan="2">{!! \App\Support\Format::formatComment($custom) !!}</td></tr>
        @endif
        @if ($showPaypal || $showAlipay)
            <tr>
                @if ($showPaypal)
                    <td class="text" align="left" valign="top" {!! $tdAttr !!}>
                        <b>{{ $lang_donate['text_donate_with_paypal'] }}</b><br /><br />
                        {!! $lang_donate['text_donate_paypal_note'] !!}
                        <form action="https://www.paypal.com/cgi-bin/webscr" method="post">
                            <input type="hidden" name="cmd" value="_xclick">
                            <input type="hidden" name="business" value="{{ $paypal }}">
                            <input type="hidden" name="item_name" value="Donation to {{ $SITENAME }}">
                            <p align="center">
                                <br />
                                {!! $lang_donate['text_select_donation_amount'] !!}<br />
                                <select name="amount">
                                    <option value="" selected>{{ $lang_donate['select_choose_donation_amount'] }}</option>
                                    @foreach ([0, 1, 5, 10, 15, 20, 30, 40, 50, 60, 100, 300] as $amount)
                                        @if ($amount == 0)
                                            <option value="">{{ $lang_donate['select_other_donation_amount'] }}</option>
                                        @else
                                            <option value="{{ number_format($amount, 2) }}">{{ $lang_donate['text_usd_mark'] }}{{ number_format($amount, 2) }}{{ $lang_donate['text_donation'] }}</option>
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
                    </td>
                @endif
                @if ($showAlipay)
                    <td class="text" align="left" valign="top" {!! $tdAttr !!}>
                        <b>{{ $lang_donate['text_donate_with_alipay'] }}</b><br /><br />
                        <form action="https://www.alipay.com/trade/fast_pay.htm" method="get">
                            {!! $lang_donate['text_donate_alipay_note_one'] !!}<b>{{ $alipay }}</b>{!! $lang_donate['text_donate_alipay_note_two'] !!}
                            <br /><br /><br /><br /><br />
                            <p align="center">
                                <input type="image" src="pic/alipaybutton.gif" border="0" name="I2" alt="Make payments with Alipay" />
                                <br /><br />
                            </p>
                        </form>
                    </td>
                @endif
            </tr>
        @endif
        <tr><td class="text" colspan="2" align="left">
            {!! $lang_donate['text_after_donation_note_one'] !!}
            <a href="sendmessage.php?receiver={{ $accountantId }}"><font class="striking"><b>{{ $lang_donate['text_send_us'] }}</b></font></a>
            {!! $lang_donate['text_after_donation_note_two'] !!}
        </td></tr>
    </table>
@endif
@endsection
