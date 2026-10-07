<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<meta name="color-scheme" content="light dark">
<title>{{ __('moresmilies.head_more_smilies') }}</title>
<base href="{{ url('/') }}/" />
<style type="text/css" nonce="{{ $cspNonce ?? '' }}">
img {border: none;}
body {color: CanvasText; background-color: Canvas}
.smilegrid {display: grid; grid-template-columns: repeat(3, 1fr); gap: 1px}
.smilegrid > div {text-align: center; padding: 1px}
</style>
</head>
<body>
<script type="text/javascript" nonce="{{ $cspNonce ?? '' }}">
function SmileIT(smile,form,text){
   window.opener.document.forms[form].elements[text].value = window.opener.document.forms[form].elements[text].value+" "+smile+" ";
   window.opener.document.forms[form].elements[text].focus();
   window.close();
}
document.addEventListener('click', function (e) {
    var t = e.target && e.target.closest ? e.target : null;
    if (!t) { return; }
    var s = t.closest('[data-smile]');
    if (s) {
        SmileIT(s.getAttribute('data-smile'), s.getAttribute('data-smile-form'), s.getAttribute('data-smile-text'));
        e.preventDefault();
        return;
    }
    if (t.closest('[data-window-close]')) {
        window.close();
        e.preventDefault();
    }
});
</script>

<div class="smilegrid">
@for ($i = 1; $i < 192; $i++)
    <div><a href="#" data-smile="[em{{ $i }}]" data-smile-form="{{ $form ?? '' }}" data-smile-text="{{ $text ?? '' }}"><img src="pic/smilies/{{ $i }}.gif" alt="[em{{ $i }}]" ></a></div>
@endfor
</div>
<div class="text-center">
 <a href="#" data-window-close>{{ __('moresmilies.text_close') }}</a>
</div>
</body>
</html>
