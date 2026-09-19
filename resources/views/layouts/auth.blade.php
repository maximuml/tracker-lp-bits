{{-- Standalone auth pages (login/signup/recover/confirm_resend) share the
     site chrome via the ADR 0018 partials — variant 'auth' is selected by
     SiteChromeComposer based on this view's name (ADR 0020). The .nx-auth
     card styles live in public/css/modern.css; no inline <style>, so the
     nonce-strict CSP applies unchanged. --}}
@include('layouts.partials.head-assets')
@include('layouts.partials.header')
<div class="nx-auth">
    @yield('content')
</div>
@include('layouts.partials.footer')
