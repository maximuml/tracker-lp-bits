@php
$form = \App\Support\SupportContext::getQuery('form');
$text = \App\Support\SupportContext::getQuery('text');
@endphp
<html><head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
<title>{{ $lang_moresmilies['head_more_smilies'] }}</title>
<style type="text/css">
img {border: none;}
body {color: #000000; background-color: #ffffff}
</style>
</head>
<body>
<script type="text/javascript">
function SmileIT(smile,form,text){
   window.opener.document.forms[form].elements[text].value = window.opener.document.forms[form].elements[text].value+" "+smile+" ";
   window.opener.document.forms[form].elements[text].focus();
   window.close();
}
</script>

<table class="lista" width="100%" cellpadding="1" cellspacing="1">
@php $count = 0; @endphp
@for ($i = 1; $i < 192; $i++)
    @if ($count % 3 == 0)
        <tr>
    @endif
    <td class="lista" align="center"><a href="javascript: SmileIT('[em{{ $i }}]','{{ $form }}','{{ $text }}')"><img src="pic/smilies/{{ $i }}.gif" alt="" ></a></td>
    @php $count++; @endphp
    @if ($count % 3 == 0)
        </tr>
    @endif
@endfor
</table>
<div align="center">
 <a href="javascript: window.close()">{{ $lang_moresmilies['text_close'] }}</a>
</div>
</body>
</html>
