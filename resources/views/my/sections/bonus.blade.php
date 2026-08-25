@php
/** @var array<string, mixed> $lang */
/** @var array<string, mixed> $curUser */
/** @var string $shopHtml */
/** @var string $infoHtml */
/** @var string $action */
$lang_mybonus = $lang;
@endphp
@if ($action !== 'exchange' && $action !== '')
    {{-- No action: show shop + info --}}
    {!! $shopHtml !!}
    {!! $infoHtml !!}
@else
    {!! $shopHtml !!}
    {!! $infoHtml !!}
@endif
