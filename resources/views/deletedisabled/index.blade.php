@php
if (\App\Support\UserDisplay::currentClass() < UC_SYSOP) {
    \App\Support\LegacyResponse::permissionDenied();
}
\App\Support\LegacyResponse::abort("Error", "Hard deletion of users is not recommended and can cause many problems.");
@endphp
