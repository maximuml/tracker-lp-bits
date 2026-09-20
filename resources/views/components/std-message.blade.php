@props(['heading' => '', 'text' => '', 'htmlstrip' => true])
@include('partials.std-message', ['heading' => $heading, 'text' => $text, 'htmlstrip' => $htmlstrip, 'body' => $slot->isNotEmpty() ? $slot : null])
