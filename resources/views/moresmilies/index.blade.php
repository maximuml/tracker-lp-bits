<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{{ $lang_moresmilies['head_more_smilies'] }}</title>
<style type="text/css" nonce="{{ $cspNonce ?? '' }}">
img {border: none;}
body {color: #000000; background-color: #ffffff}
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

<table class="lista" width="100%" cellpadding="1" cellspacing="1">
@for ($i = 1; $i < 192; $i++)
    @if (($i - 1) % 3 == 0)
        <tr>
    @endif
    <td class="lista" align="center"><a href="#" data-smile="[em{{ $i }}]" data-smile-form="{{ $form ?? '' }}" data-smile-text="{{ $text ?? '' }}"><img src="pic/smilies/{{ $i }}.gif" alt="" ></a></td>
    @if ($i % 3 == 0)
        </tr>
    @endif
@endfor
</table>
<div align="center">
 <a href="#" data-window-close>{{ $lang_moresmilies['text_close'] }}</a>
</div>
</body>
</html>
