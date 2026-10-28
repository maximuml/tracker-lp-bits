@props(['nonce', 'url'])
<script type="text/javascript"@if($nonce !== '') nonce="{{ $nonce }}"@endif>window.location.href = '{{ $url }}';</script>
