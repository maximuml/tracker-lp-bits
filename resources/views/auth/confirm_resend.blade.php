@extends('layouts.auth')

@section('title', (__('legacy/confirm_resend.resend_confirmation_email_failed')) . ' :: ' . $siteName)

@section('content')
    @if ($error)
        <div class="error">{{ $error }}</div>
    @endif

    @if ($errors->any())
        <div class="error">
            @foreach ($errors->all() as $message)
                <div>{{ $message }}</div>
            @endforeach
        </div>
    @endif

    <form method="get" action="/confirm_resend">
        <input type="hidden" name="secret" value="{{ $secret }}" />
        <div align="right">
            {{ __('legacy/confirm_resend.text_select_lang')}}
            <select name="sitelanguage">
                @foreach ($languages as $row)
                    <option value="{{ $row['id'] }}" @if (($row['site_lang_folder'] ?? '') === $langFolder) selected @endif>
                        {{ $row['lang_name'] }}
                    </option>
                @endforeach
            </select>
        </div>
    </form>

    <h1>{{ __('legacy/confirm_resend.text_resend_confirmation_mail_note') }} </h1><p>{{ __('legacy/confirm_resend.text_resend_confirmation_mail_note_two') }}</p><p>{{ __('legacy/confirm_resend.text_resend_confirmation_mail_note_three') }}<br />{{ __('legacy/confirm_resend.text_resend_confirmation_mail_note_four') }}</p><p><b>{{ __('legacy/confirm_resend.text_note') }}</b> {{ sprintf(__('legacy/confirm_resend.text_resend_confirmation_mail_attempts'), $maxAttempts) }}</p>

    <p>{{ __('legacy/confirm_resend.text_you_have')}} <b>{{ $remaining }}</b> {{ __('legacy/confirm_resend.text_remaining_tries')}}</p>

    <form method="post" action="/confirm_resend">
        @csrf
        <input type="hidden" name="secret" value="{{ $secret }}" />
        <div class="nx-fgrid nx-fgrid--b">
            <div class="nx-fhead">{{ __('legacy/confirm_resend.row_registered_email')}}</div>
            <div class="nx-fcell"><input type="email" name="email" autocomplete="email" value="{{ old('email') }}" style="width: min(100%, 320px); min-width: 180px; border: 1px solid gray; box-sizing: border-box" /></div>
            <div class="nx-fhead">{{ __('legacy/confirm_resend.row_new_password')}}</div>
            <div class="nx-fcell">
                <input type="password" name="wantpassword" autocomplete="new-password" style="width: min(100%, 320px); min-width: 180px; border: 1px solid gray; box-sizing: border-box" /><br />
                <font class="small">{{ __('legacy/confirm_resend.text_password_note')}}</font>
            </div>
            <div class="nx-fhead">{{ __('legacy/confirm_resend.row_enter_password_again')}}</div>
            <div class="nx-fcell"><input type="password" name="passagain" autocomplete="new-password" style="width: min(100%, 320px); min-width: 180px; border: 1px solid gray; box-sizing: border-box" /></div>

            @if ($captchaEnabled && $captchaMarkup !== '')
                {{ $captchaMarkup }}
            @endif

            <div class="toolbox nx-ffull">
                <input type="submit" class="btn" value="{{ __('legacy/confirm_resend.submit_send_it')}}" />
            </div>
        </div>
    </form>
@endsection
