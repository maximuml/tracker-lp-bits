@extends('layouts.auth')

@section('title', (__('legacy/recover.text_recover_user')) . ' :: ' . $siteName)

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

    @if ($status === 'requested')
        <div class="success">If an account with that email exists, a reset link has been sent.</div>
    @endif

    <form method="get" action="/recover">
        <input type="hidden" name="secret" value="{{ $secret }}" />
        <div align="right">
            <label for="sitelanguage">{{ __('legacy/recover.text_select_lang')}}</label>
            <select id="sitelanguage" name="sitelanguage">
                @foreach ($languages as $row)
                    <option value="{{ $row['id'] }}" @if (($row['site_lang_folder'] ?? '') === $langFolder) selected @endif>
                        {{ $row['lang_name'] }}
                    </option>
                @endforeach
            </select>
        </div>
    </form>

    <h1>{{ __('legacy/recover.text_recover_user')}}</h1>
    <p>{{ __('legacy/recover.text_use_form_below')}}</p>
    <p>{{ __('legacy/recover.text_reply_to_confirmation_email')}}</p>
    <p><b>{{ __('legacy/recover.text_note')}}</b> {{ $maxAttempts }} {{ __('legacy/recover.text_ban_ip')}}</p>
    <p>{{ __('legacy/recover.text_you_have')}} <b>{{ $remaining }}</b> {{ __('legacy/recover.text_remaining_tries')}}</p>

    <form method="post" action="/recover">
        @csrf
        <input type="hidden" name="secret" value="{{ $secret }}" />
        <div class="nx-fgrid nx-fgrid--b">
            <div class="nx-fhead"><label for="email">{{ __('legacy/recover.row_registered_email')}}</label></div>
            <div class="nx-fcell"><input type="email" id="email" name="email" autocomplete="email" value="{{ old('email') }}" style="width: min(100%, 320px); min-width: 180px; border: 1px solid gray; box-sizing: border-box" /></div>

            @if ($captchaEnabled && $captchaMarkup !== '')
                @safeHtml(App\Support\Html\SafeHtml::fromTrustedHtml($captchaMarkup))
            @endif

            <div class="toolbox nx-ffull">
                <input type="submit" value="{{ __('legacy/recover.submit_recover_it')}}" class="btn" />
            </div>
        </div>
    </form>
@endsection
