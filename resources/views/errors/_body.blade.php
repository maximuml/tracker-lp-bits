<div class="nx-box nx-error nx-center" role="alert">
    <p class="nx-error__code">{{ $status }}</p>
    <h1 class="nx-error__title">{{ $title }}</h1>
    <p class="nx-error__message">{{ $message }}</p>
    <p class="nx-error__actions">
        <a class="nx-btn nx-btn--primary" href="/web/index">{{ __('functions.text_go_home') }}</a>
        <a class="nx-btn" href="#" data-nx-error-back>{{ __('functions.text_go_back') }}</a>
    </p>
</div>
<script nonce="{{ $cspNonce ?? '' }}">document.querySelector('[data-nx-error-back]')?.addEventListener('click',function(e){e.preventDefault();if(window.history.length>1){window.history.back();}else{window.location.href='/web/index';}});</script>
