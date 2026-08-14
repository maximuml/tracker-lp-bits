@php
$lang_takereseed = (array) (\App\Support\SupportContext::getGlobal('lang_takereseed') ?? []);
$message = (string) ($message ?? $lang_takereseed['std_it_worked'] ?? 'Reseed request sent.');

\App\Support\Html::stdhead($lang_takereseed['head_reseed_request'] ?? 'Reseed request');
\App\Support\Frame::mainFrameOpen();
print('<center>' . $message . '</center>');
\App\Support\Frame::mainFrameClose();
\App\Support\Html::stdfoot();
@endphp
