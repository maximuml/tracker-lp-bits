<?php

namespace App\Support;

/**
 * Legacy form-emitter helpers extracted from `include/functions.php`.
 *
 * Backs `datetimepicker_input()` and similar HTML/JS input builders.
 */
final class Form
{
    /**
     * Render a jQuery datetimepicker input and queue its assets.
     *
     * Mirrors `datetimepicker_input()`.
     */
    /**
     * @param  array<string, mixed>  $options
     */
    public static function datetimepickerInput(string $name, ?string $value = '', string $label = '', array $options = []): string
    {
        $value = (string) $value;
        $lang = Locale::folderFromCookie(SupportContext::getCookieValue('c_lang_folder'), true);
        if ($lang === 'zh_CN') {
            $lang = 'zh';
        }
        $lang = str_replace('_', '-', $lang);

        $js = '';
        if (! empty($options['require_files'])) {
            \Nexus\Nexus::css('vendor/jquery-datetimepicker/jquery.datetimepicker.min.css', 'footer', true);
            \Nexus\Nexus::js('vendor/jquery-datetimepicker/jquery.datetimepicker.full.min.js', 'footer', true);
            $js = "jQuery.datetimepicker.setLocale('{$lang}');";
        }

        $id = "datetime-picker-$name";
        $input = sprintf(
            '%s<input type="text" id="%s" name="%s" value="%s" autocomplete="off" style="%s">',
            $label,
            $id,
            $name,
            $value,
            $options['style'] ?? ''
        );

        $format = $options['format'] ?? 'Y-m-d H:i';
        $js .= "jQuery(\"#{$id}\").datetimepicker({ format: '{$format}' })";
        \Nexus\Nexus::js($js, 'footer', false);

        return $input;
    }

    /**
     * Render the legacy BBCode editor toolbar/textarea HTML.
     *
     * Mirrors `textbbcode()` from `include/functions.php`.
     */
    public static function bbcodeEditor(string $form, string $text, string $content = '', bool $hasTitle = false, int $colNum = 130, bool $withPreview = false): string
    {
        $lang_functions = SupportContext::getLangFunctions();
        $enableattach_attachment = (string) SupportContext::getGlobal('enableattach_attachment', '');

        ob_start();

	$editTbodyId = "$form-$text-edit";
	$previewTbodyId = "$form-$text-preview";
	$btnEditId = "$form-$text-btn-edit";
    $btnPreviewId = "$form-$text-btn-preview";
?>

<script type="text/javascript">
    let textareaId = <?php echo json_encode($text, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    let editTbodyId = <?php echo json_encode($editTbodyId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    let previewTbodyId = <?php echo json_encode($previewTbodyId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    let btnEditId = <?php echo json_encode($btnEditId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
    let btnPreviewId = <?php echo json_encode($btnPreviewId, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
//<![CDATA[
var b_open = 0;
var i_open = 0;
var u_open = 0;
var color_open = 0;
var list_open = 0;
var quote_open = 0;
var html_open = 0;

var myAgent = navigator.userAgent.toLowerCase();
var myVersion = parseInt(navigator.appVersion);

var is_ie = ((myAgent.indexOf("msie") != -1) && (myAgent.indexOf("opera") == -1));
var is_nav = ((myAgent.indexOf('mozilla')!=-1) && (myAgent.indexOf('spoofer')==-1)
&& (myAgent.indexOf('compatible') == -1) && (myAgent.indexOf('opera')==-1)
&& (myAgent.indexOf('webtv') ==-1) && (myAgent.indexOf('hotjava')==-1));

var is_win = ((myAgent.indexOf("win")!=-1) || (myAgent.indexOf("16bit")!=-1));
var is_mac = (myAgent.indexOf("mac")!=-1);
var bbtags = new Array();
function cstat() {
	var c = stacksize(bbtags);
	if ( (c < 1) || (c == null) ) {c = 0;}
	if ( ! bbtags[0] ) {c = 0;}
	document.forms[<?php echo json_encode($form, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>].tagcount.value = "Close last, Open "+c;
}
function stacksize(thearray) {
	for (i = 0; i < thearray.length; i++ ) {
		if ( (thearray[i] == "") || (thearray[i] == null) || (thearray == 'undefined') ) {return i;}
	}
	return thearray.length;
}
function pushstack(thearray, newval) {
	arraysize = stacksize(thearray);
	thearray[arraysize] = newval;
}
function popstackd(thearray) {
	arraysize = stacksize(thearray);
	theval = thearray[arraysize - 1];
	return theval;
}
function popstack(thearray) {
	arraysize = stacksize(thearray);
	theval = thearray[arraysize - 1];
	delete thearray[arraysize - 1];
	return theval;
}
function closeall() {
	if (bbtags[0]) {
		while (bbtags[0]) {
			tagRemove = popstack(bbtags)
			if ( (tagRemove != 'color') ) {
				doInsert("[/"+tagRemove+"]", "", false);
				document.forms[<?php echo json_encode($form, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>][tagRemove].value = ' ' + tagRemove.toUpperCase() + ' ';
				window[tagRemove + '_open'] = 0;
			} else {
				doInsert("[/"+tagRemove+"]", "", false);
			}
			cstat();
			return;
		}
	}
	document.forms[<?php echo json_encode($form, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>].tagcount.value = "Close last, Open 0";
	bbtags = new Array();
	document.forms[<?php echo json_encode($form, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>][<?php echo json_encode($text, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>].focus();
}
function add_code(NewCode) {
	document.forms[<?php echo json_encode($form, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>][<?php echo json_encode($text, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>].value += NewCode;
	document.forms[<?php echo json_encode($form, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>][<?php echo json_encode($text, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>].focus();
}
function alterfont(theval, thetag) {
	if (theval == 0) return;
	if(doInsert("[" + thetag + "=" + theval + "]", "[/" + thetag + "]", true)) pushstack(bbtags, thetag);
	document.forms[<?php echo json_encode($form, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>].color.selectedIndex = 0;
	cstat();
}

function tag_url(PromptURL, PromptTitle, PromptError) {
	var FoundErrors = '';
	var enterURL = prompt(PromptURL, "http://");
	var enterTITLE = prompt(PromptTitle, "");
	if (!enterURL || enterURL=="") {FoundErrors += " " + PromptURL + ",";}
	if (!enterTITLE) {FoundErrors += " " + PromptTitle;}
	if (FoundErrors) {alert(PromptError+FoundErrors);return;}
	doInsert("[url="+enterURL+"]"+enterTITLE+"[/url]", "", false);
}

function tag_list(PromptEnterItem, PromptError) {
	var FoundErrors = '';
	var enterTITLE = prompt(PromptEnterItem, "");
	if (!enterTITLE) {FoundErrors += " " + PromptEnterItem;}
	if (FoundErrors) {alert(PromptError+FoundErrors);return;}
	doInsert("[*]"+enterTITLE+"", "", false);
}

function tag_image(PromptImageURL, PromptError) {
	var FoundErrors = '';
	var enterURL = prompt(PromptImageURL, "http://");
	if (!enterURL || enterURL=="http://") {
		alert(PromptError+PromptImageURL);
		return;
	}
	doInsert("[img]"+enterURL+"[/img]", "", false);
}

function tag_extimage(content) {
	doInsert(content, "", false);
}

function tag_email(PromptEmail, PromptError) {
	var emailAddress = prompt(PromptEmail, "");
	if (!emailAddress) {
		alert(PromptError+PromptEmail);
		return;
	}
	doInsert("[email]"+emailAddress+"[/email]", "", false);
}

function doInsert(ibTag, ibClsTag, isSingle)
{
	var isClose = false;
	var obj_ta = document.forms[<?php echo json_encode($form, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>][<?php echo json_encode($text, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>];
	if ( (myVersion >= 4) && is_ie && is_win)
	{
		if(obj_ta.isTextEdit)
		{
			obj_ta.focus();
			var sel = document.selection;
			var rng = sel.createRange();
			rng.colapse;
			if((sel.type == "Text" || sel.type == "None") && rng != null)
			{
				if(ibClsTag != "" && rng.text.length > 0)
				ibTag += rng.text + ibClsTag;
				else if(isSingle) isClose = true;
				rng.text = ibTag;
			}
		}
		else
		{
			if(isSingle) isClose = true;
			obj_ta.value += ibTag;
		}
	}
	else if (obj_ta.selectionStart || obj_ta.selectionStart == '0')
	{
		var startPos = obj_ta.selectionStart;
		var endPos = obj_ta.selectionEnd;
		obj_ta.value = obj_ta.value.substring(0, startPos) + ibTag + obj_ta.value.substring(endPos, obj_ta.value.length);
		obj_ta.selectionEnd = startPos + ibTag.length;
		if(isSingle) isClose = true;
	}
	else
	{
		if(isSingle) isClose = true;
		obj_ta.value += ibTag;
	}
	obj_ta.focus();
	// obj_ta.value = obj_ta.value.replace(/ /, " ");
	return isClose;
}

function clearContent()
{
    document.forms[<?php echo json_encode($form, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>][<?php echo json_encode($text, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>].value = '';
}

function winop()
{
	windop = window.open("moresmilies.php?form=<?php echo urlencode($form) ?>&text=<?php echo urlencode($text) ?>","mywin","height=500,width=500,resizable=no,scrollbars=yes");
}

function simpletag(thetag)
{
	var tagOpen = window[thetag + '_open'];
	if (tagOpen == 0) {
		if(doInsert("[" + thetag + "]", "[/" + thetag + "]", true))
		{
			window[thetag + '_open'] = 1;
			document.forms[<?php echo json_encode($form, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>][thetag].value += '*';
			pushstack(bbtags, thetag);
			cstat();
		}
	}
	else {
		lastindex = 0;
		for (i = 0; i < bbtags.length; i++ ) {
			if ( bbtags[i] == thetag ) {
				lastindex = i;
			}
		}

		while (bbtags[lastindex]) {
			tagRemove = popstack(bbtags);
			doInsert("[/" + tagRemove + "]", "", false)
			if ((tagRemove != 'COLOR') ){
				document.forms[<?php echo json_encode($form, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>][tagRemove].value = tagRemove.toUpperCase();
				window[tagRemove + '_open'] = 0;
			}
		}
		cstat();
	}
}

function textBBCodePreview() {
    let poststr = encodeURIComponent( document.getElementById(textareaId).value );
    let result=ajax.posts('preview.php','body='+poststr);
    jQuery('#' + editTbodyId).hide()
    jQuery('#' + previewTbodyId).html(result).show()
    jQuery('#' + btnPreviewId).hide()
    jQuery('#' + btnEditId).show()
}
function textBBCodeEdit() {
    jQuery('#' + editTbodyId).show()
    jQuery('#' + previewTbodyId).hide()
    jQuery('#' + btnPreviewId).show()
    jQuery('#' + btnEditId).hide()
}
//]]>
</script>
<table width="100%" cellspacing="0" cellpadding="5" border="0">
    <tbody id="<?php echo $editTbodyId?>">
<tr><td align="left" colspan="2">
<table cellspacing="1" cellpadding="2" border="0">
<tr>
<td class="embedded"><input style="font-weight: bold;font-size:11px; margin-right:3px" type="button" name="b" value="B" onclick="javascript: simpletag('b')" /></td>
<td class="embedded"><input class="codebuttons" style="font-style: italic;font-size:11px;margin-right:3px" type="button" name="i" value="I" onclick="javascript: simpletag('i')" /></td>
<td class="embedded"><input class="codebuttons" style="text-decoration: underline;font-size:11px;margin-right:3px" type="button" name="u" value="U" onclick="javascript: simpletag('u')" /></td>
<?php
print("<td class=\"embedded\"><input class=\"codebuttons\" style=\"font-size:11px;margin-right:3px\" type=\"button\" name='url' value='URL' onclick=\"javascript:tag_url('" . $lang_functions['js_prompt_enter_url'] . "','" . $lang_functions['js_prompt_enter_title'] . "','" . $lang_functions['js_prompt_error'] . "')\" /></td>");
print("<td class=\"embedded\"><input class=\"codebuttons\" style=\"font-size:11px;margin-right:3px\" type=\"button\" name=\"IMG\" value=\"IMG\" onclick=\"javascript: tag_image('" . $lang_functions['js_prompt_enter_image_url'] . "','" . $lang_functions['js_prompt_error'] . "')\" /></td>");
print("<td class=\"embedded\"><input type=\"button\" style=\"font-size:11px;margin-right:3px\" name=\"list\" value=\"List\" onclick=\"tag_list('" . addslashes($lang_functions['js_prompt_enter_item']) . "','" . $lang_functions['js_prompt_error'] . "')\" /></td>");
?>
<td class="embedded"><input class="codebuttons" style="font-size:11px;margin-right:3px" type="button" name="quote" value="QUOTE" onclick="javascript: simpletag('quote')" /></td>
<td class="embedded"><input style="font-size:11px;margin-right:3px" type="button" onclick='javascript:closeall();' name='tagcount' value="Close all tags" /></td>
<td class="embedded"><select class="med codebuttons" style="margin-right:3px" name='color' onchange="alterfont(this.options[this.selectedIndex].value, 'color')">
<option value='0'>--- <?php echo $lang_functions['select_color'] ?> ---</option>
<option style="background-color: black" value="Black">Black</option>
<option style="background-color: sienna" value="Sienna">Sienna</option>
<option style="background-color: darkolivegreen" value="DarkOliveGreen">Dark Olive Green</option>
<option style="background-color: darkgreen" value="DarkGreen">Dark Green</option>
<option style="background-color: darkslateblue" value="DarkSlateBlue">Dark Slate Blue</option>
<option style="background-color: navy" value="Navy">Navy</option>
<option style="background-color: indigo" value="Indigo">Indigo</option>
<option style="background-color: darkslategray" value="DarkSlateGray">Dark Slate Gray</option>
<option style="background-color: darkred" value="DarkRed">Dark Red</option>
<option style="background-color: darkorange" value="DarkOrange">Dark Orange</option>
<option style="background-color: olive" value="Olive">Olive</option>
<option style="background-color: green" value="Green">Green</option>
<option style="background-color: teal" value="Teal">Teal</option>
<option style="background-color: blue" value="Blue">Blue</option>
<option style="background-color: slategray" value="SlateGray">Slate Gray</option>
<option style="background-color: dimgray" value="DimGray">Dim Gray</option>
<option style="background-color: red" value="Red">Red</option>
<option style="background-color: sandybrown" value="SandyBrown">Sandy Brown</option>
<option style="background-color: yellowgreen" value="YellowGreen">Yellow Green</option>
<option style="background-color: seagreen" value="SeaGreen">Sea Green</option>
<option style="background-color: mediumturquoise" value="MediumTurquoise">Medium Turquoise</option>
<option style="background-color: royalblue" value="RoyalBlue">Royal Blue</option>
<option style="background-color: purple" value="Purple">Purple</option>
<option style="background-color: gray" value="Gray">Gray</option>
<option style="background-color: magenta" value="Magenta">Magenta</option>
<option style="background-color: orange" value="Orange">Orange</option>
<option style="background-color: yellow" value="Yellow">Yellow</option>
<option style="background-color: lime" value="Lime">Lime</option>
<option style="background-color: cyan" value="Cyan">Cyan</option>
<option style="background-color: deepskyblue" value="DeepSkyBlue">Deep Sky Blue</option>
<option style="background-color: darkorchid" value="DarkOrchid">Dark Orchid</option>
<option style="background-color: silver" value="Silver">Silver</option>
<option style="background-color: pink" value="Pink">Pink</option>
<option style="background-color: wheat" value="Wheat">Wheat</option>
<option style="background-color: lemonchiffon" value="LemonChiffon">Lemon Chiffon</option>
<option style="background-color: palegreen" value="PaleGreen">Pale Green</option>
<option style="background-color: paleturquoise" value="PaleTurquoise">Pale Turquoise</option>
<option style="background-color: lightblue" value="LightBlue">Light Blue</option>
<option style="background-color: plum" value="Plum">Plum</option>
<option style="background-color: white" value="White">White</option>
</select></td>
<td class="embedded">
<select class="med codebuttons" name='font' onchange="alterfont(this.options[this.selectedIndex].value, 'font')">
<option value="0">--- <?php echo $lang_functions['select_font'] ?> ---</option>
<option value="Arial">Arial</option>
<option value="Arial Black">Arial Black</option>
<option value="Arial Narrow">Arial Narrow</option>
<option value="Book Antiqua">Book Antiqua</option>
<option value="Century Gothic">Century Gothic</option>
<option value="Comic Sans MS">Comic Sans MS</option>
<option value="Courier New">Courier New</option>
<option value="Fixedsys">Fixedsys</option>
<option value="Garamond">Garamond</option>
<option value="Georgia">Georgia</option>
<option value="Impact">Impact</option>
<option value="Lucida Console">Lucida Console</option>
<option value="Lucida Sans Unicode">Lucida Sans Unicode</option>
<option value="Microsoft Sans Serif">Microsoft Sans Serif</option>
<option value="Palatino Linotype">Palatino Linotype</option>
<option value="System">System</option>
<option value="Tahoma">Tahoma</option>
<option value="Times New Roman">Times New Roman</option>
<option value="Trebuchet MS">Trebuchet MS</option>
<option value="Verdana">Verdana</option>
</select>
</td>
<td class="embedded">
<select class="med codebuttons" name='size' onchange="alterfont(this.options[this.selectedIndex].value, 'size')">
<option value="0">--- <?php echo $lang_functions['select_size'] ?> ---</option>
<option value="1">1</option>
<option value="2">2</option>
<option value="3">3</option>
<option value="4">4</option>
<option value="5">5</option>
<option value="6">6</option>
<option value="7">7</option>
</select></td></tr>
</table>
</td>
</tr>
<?php
if ($enableattach_attachment == 'yes'){
?>
<tr>
<td colspan="2" valign="middle">
<iframe src="<?php echo getSchemeAndHttpHost()?>/attachment.php" width="100%" height="24" frameborder="0" scrolling="no" marginheight="0" marginwidth="0"></iframe>
</td>
</tr>
<?php
}
print("<tr>");
print("<td align=\"left\"><textarea class=\"bbcode\" cols=\"100\" style=\"width: 100%;\" name=\"".$text."\" id=\"".$text."\" rows=\"20\" onkeydown=\"ctrlenter(event,'compose','qr')\">".$content."</textarea>");
?>
</td>
<td align="center" width="">
<table cellspacing="1" cellpadding="3">
<tr>
<?php
$i = 0;
$quickSmilies = array(1, 2, 3, 5, 6, 7, 8, 9, 10, 11, 13, 16, 17, 19, 20, 21, 24, 25, 26, 27, 28, 29, 30, 31, 32, 33, 34, 35, 36, 39, 40, 41);
foreach ($quickSmilies as $smily) {
	if ($i%4 == 0 && $i > 0) {
		print('</tr><tr>');
	}
	print("<td class=\"embedded\" style=\"padding: 3px;\">".getSmileIt($form, $text, $smily)."</td>");
	$i++;
}
?>
</tr></table>
<br />
<a href="javascript:winop();"><?php echo $lang_functions['text_more_smilies'] ?></a>
</td></tr></tobdy>
    <?php if($withPreview) {?>
    <tbody id="<?php echo $previewTbodyId?>"></tbody>
    <tbody>
        <tr><td colspan="2" style="text-align: center;border: none">
            <input id="<?php echo $btnPreviewId ?>" type="button" class="btn" value="<?php echo $lang_functions['submit_preview']?>" onclick="javascript:textBBCodePreview()">
            <input id="<?php echo $btnEditId ?>" type="button" class="btn" style="display: none" value="<?php echo $lang_functions['submit_edit']?>" onclick="javascript:textBBCodeEdit()">
        </td></tr>
    </tbody>
    <?php }?>
</table><?php

        return ob_get_clean();
    }

    /**
     * Render the client-side password hashing JS for a login/signup form.
     *
     * Mirrors `render_password_hash_js()`.
     */
    public static function passwordHashJs(
        string $formId,
        string $passwordOriginalClass,
        string $passwordHashedName,
        bool $passwordRequired,
        string $passwordConfirmClass = 'password_confirmation',
        string $usernameName = 'username',
    ): void {
        $tipTooShort = \nexus_trans('signup.password_too_short');
        $tipTooLong = \nexus_trans('signup.password_too_long');
        $tipEqualUsername = \nexus_trans('signup.password_equals_username');
        $tipNotMatch = \nexus_trans('signup.passwords_unmatched');
        $passwordValidateJS = '';

        if ($passwordRequired) {
            $passwordValidateJS = <<<JS
if (password.length < 6) {
    layer.alert("$tipTooShort")
    return
}
if (password.length > 40) {
    layer.alert("$tipTooLong")
    return
}
JS;
        }

        $formVar = 'jqForm' . md5($formId);
        $js = <<<JS
var $formVar = jQuery("#{$formId}");
$formVar.on("click", "input[type=button]", function() {
    let jqUsername = $formVar.find("[name={$usernameName}]")
    let jqPassword = $formVar.find(".{$passwordOriginalClass}")
    let jqPasswordConfirm = $formVar.find(".{$passwordConfirmClass}")
    let password = jqPassword.val()
    $passwordValidateJS
    if (jqUsername.length > 0 && jqUsername.val() === password) {
        layer.alert("$tipEqualUsername")
        return
    }
    if (jqPasswordConfirm.length > 0 && password !== jqPasswordConfirm.val()) {
        layer.alert("$tipNotMatch")
        return
    }
    if (password !== "") {
        const passwordHashed = CryptoJS.SHA256(password).toString()
        $formVar.find("input[name={$passwordHashedName}]").val(passwordHashed)
        const hashedMarkerName = "{$passwordHashedName}_hashed"
        let jqHashedMarker = $formVar.find("input[name=" + hashedMarkerName + "]")
        if (jqHashedMarker.length === 0) {
            jqHashedMarker = jQuery('<input type="hidden" name="{$passwordHashedName}_hashed" value="1" />')
            $formVar.append(jqHashedMarker)
        } else {
            jqHashedMarker.val("1")
        }
        $formVar.submit()
    } else {
        $formVar.submit()
    }
})
JS;
        \Nexus\Nexus::js('js/crypto-js.js', 'footer', true);
        \Nexus\Nexus::js($js, 'footer', false);
    }

    /**
     * Render the challenge-response login JS for a form.
     *
     * Mirrors `render_password_challenge_js()`.
     */
    public static function passwordChallengeJs(string $formId, string $usernameName, string $passwordOriginalClass): void
    {
        $formVar = 'jqForm' . md5($formId);
        $js = <<<JS
var $formVar = jQuery("#{$formId}");
$formVar.on("click", "input[type=button]", function() {
    let useChallengeResponseAuthentication = $formVar.find("input[name=response]").length > 0
    if (!useChallengeResponseAuthentication) {
        return $formVar.submit()
    }
    let jqUsername = $formVar.find("[name={$usernameName}]")
    let jqPassword = $formVar.find(".{$passwordOriginalClass}")
    let username = jqUsername.val()
    let password = jqPassword.val()
    login(username, password, $formVar)
})
async function login(username, password, jqForm) {
    try {
        jQuery('body').loading({stoppable: false});
        const challengeResponse = await fetch('/api/challenge', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ username: username })
        });
        jQuery('body').loading('stop');

        const challengeData = await challengeResponse.json();
        if (challengeData.ret !== 0) {
            layer.alert(challengeData.msg)
            return
        }

        const clientHashedPassword = CryptoJS.SHA256(password).toString();

        const serverSideHash = CryptoJS.SHA256(challengeData.data.secret + clientHashedPassword).toString();

        const clientResponse = CryptoJS.HmacSHA256(serverSideHash, challengeData.data.challenge).toString();
        jqForm.find("input[name=response]").val(clientResponse)
        jqForm.submit()
    } catch (error) {
        console.error(error);
        layer.alert(error.toString())
    }
}
JS;
        \Nexus\Nexus::js('vendor/jquery-loading/jquery.loading.min.js', 'footer', true);
        \Nexus\Nexus::js('js/crypto-js.js', 'footer', true);
        \Nexus\Nexus::js($js, 'footer', false);
    }
}
