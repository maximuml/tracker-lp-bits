@php
\App\Support\Html::stdhead();
\App\Support\Html::stdMessage($lang_takecontact['std_succeeded'] ?? 'Succeeded', $lang_takecontact['std_message_succesfully_sent'] ?? 'Message successfully sent.');
\App\Support\Html::stdfoot();
@endphp
