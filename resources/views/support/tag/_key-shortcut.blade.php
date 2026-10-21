@props(['nonce', 'maxpage', 'currentpage'])
<script type="text/javascript"@if($nonce !== '') nonce="{{ $nonce }}"@endif>
//<![CDATA[
{{ $maxpage }}
{{ $currentpage }}
//]]>
</script>
