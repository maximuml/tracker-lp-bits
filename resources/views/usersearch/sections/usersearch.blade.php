<h1>Administrative User Search</h1>

@if ($showHelp)
<table width=65% border=0 align=center><tr><td class=embedded bgcolor='#F5F4EA'><div align=left>
	Fields left blank will be ignored;
	Wildcards * and ? may be used in Name, Email and Comments, as well as multiple values
	separated by spaces (e.g. 'wyz Max*' in Name will list both users named
	'wyz' and those whose names start by 'Max'. Similarly  '~' can be used for
	negation, e.g. '~alfiest' in comments will restrict the search to users
	that do not have 'alfiest' in their comments).<br /><br />
    The Ratio field accepts 'Inf' and '---' besides the usual numeric values.<br /><br />
	The subnet mask may be entered either in dotted decimal or CIDR notation
	(e.g. 255.255.255.0 is the same as /24).<br /><br />
    Uploaded and Downloaded should be entered in GB.<br /><br />
	For search parameters with multiple text fields the second will be
	ignored unless relevant for the type of search chosen. <br /><br />
	'Active only' restricts the search to users currently leeching or seeding,
	'Disabled IPs' to those whose IPs also show up in disabled accounts.<br /><br />
	The 'p' columns in the results show partial stats, that is, those
	of the torrents in progress. <br /><br />
	The History column lists the number of forum posts and torrent comments,
	respectively, as well as linking to the history page.
	</div></td></tr></table><br /><br />
@else
<p align=center>(<a href="{{ $requestUri }}?h=1">Instructions</a>)
&nbsp;-&nbsp;(<a href="{{ $requestUri }}">Reset</a>)</p>
@endif

<form method=get action="{{ $requestUri }}">
<table border="1" cellspacing="0" cellpadding="5">
<tr>
  <td valign="middle" class=rowhead>Name:</td>
  <td @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['n_hl'] ?? ''))><input name="n" type="text" value="{{ $form['n'] }}" size=35></td>
  <td valign="middle" class=rowhead>Ratio:</td>
  <td @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['r'] ? ($form['r_hl'] ?? '') : ''))><select name="rt">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['rt_options'] ?? ''))
    </select>
    <input name="r" type="text" value="{{ $form['r'] }}" size="5" maxlength="4">
    <input name="r2" type="text" value="{{ $form['r2'] }}" size="5" maxlength="4"></td>
  <td valign="middle" class=rowhead>Member status:</td>
  <td @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['st'] ? ($form['st_hl'] ?? '') : ''))><select name="st">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['st_options'] ?? ''))
    </select></td></tr>
<tr><td valign="middle" class=rowhead>Email:</td>
  <td @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['em_hl'] ?? ''))><input name="em" type="text" value="{{ $form['em'] }}" size="35"></td>
  <td valign="middle" class=rowhead>IP:</td>
  <td @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['ip_hl'] ?? ''))><input name="ip" type="text" value="{{ $form['ip'] }}" maxlength="64"></td>
  <td valign="middle" class=rowhead>Account status:</td>
  <td @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['as'] ? ($form['as_hl'] ?? '') : ''))><select name="as">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['as_options'] ?? ''))
    </select></td></tr>
<tr>
  <td valign="middle" class=rowhead>Comment:</td>
  <td @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['co_hl'] ?? ''))><input name="co" type="text" value="{{ $form['co'] }}" size="35"></td>
  <td valign="middle" class=rowhead>Mask:</td>
  <td @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['ma_hl'] ?? ''))><input name="ma" type="text" value="{{ $form['ma'] }}" maxlength="17"></td>
  <td valign="middle" class=rowhead>Class:</td>
  <td @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['c_hl'] ?? ''))><select name="c">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['c_options'] ?? ''))
    </select></td></tr>
<tr>
    <td valign="middle" class=rowhead>Joined:</td>
  <td @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['d_hl'] ?? ''))><select name="dt">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['dt_options'] ?? ''))
    </select>
    <input name="d" type="text" value="{{ $form['d'] }}" size="12" maxlength="10">
    <input name="d2" type="text" value="{{ $form['d2'] }}" size="12" maxlength="10"></td>
  <td valign="middle" class=rowhead>Uploaded:</td>
  <td @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['ul_hl'] ?? ''))><select name="ult" id="ult">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['ult_options'] ?? ''))
    </select>
    <input name="ul" type="text" id="ul" size="8" maxlength="7" value="{{ $form['ul'] }}">
    <input name="ul2" type="text" id="ul2" size="8" maxlength="7" value="{{ $form['ul2'] }}"></td>
  <td valign="middle" class="rowhead">Donor:</td>
  <td @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['do_hl'] ?? ''))><select name="do">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['do_options'] ?? ''))
	</select></td></tr>
<tr>
<td valign="middle" class=rowhead>Last seen:</td>
  <td @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['ls_hl'] ?? ''))><select name="lst">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['lst_options'] ?? ''))
  </select>
  <input name="ls" type="text" value="{{ $form['ls'] }}" size="12" maxlength="10">
  <input name="ls2" type="text" value="{{ $form['ls2'] }}" size="12" maxlength="10"></td>
	  <td valign="middle" class=rowhead>Downloaded:</td>
  <td @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['dl_hl'] ?? ''))><select name="dlt" id="dlt">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['dlt_options'] ?? ''))
    </select>
    <input name="dl" type="text" id="dl" size="8" maxlength="7" value="{{ $form['dl'] }}">
    <input name="dl2" type="text" id="dl2" size="8" maxlength="7" value="{{ $form['dl2'] }}"></td>
	<td valign="middle" class=rowhead>Warned:</td>
	<td @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['w_hl'] ?? ''))><select name="w">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['w_options'] ?? ''))
	</select></td></tr>
<tr><td class="rowhead"></td><td></td>
  <td valign="middle" class=rowhead>Active only:</td>
	<td @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['ac_hl'] ?? ''))><input name="ac" type="checkbox" value="1" {{ $form['ac'] ? 'checked' : '' }}></td>
  <td valign="middle" class=rowhead>Disabled IP: </td>
  <td @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['dip_hl'] ?? ''))><input name="dip" type="checkbox" value="1" {{ $form['dip'] ? 'checked' : '' }}></td>
  </tr>
<tr><td colspan="6" align=center><input name="submit" type=submit class=btn></td></tr>
</table>
<br /><br />
</form>

@if ($resultsError)
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($resultsError))
@elseif ($hasResults)
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($resultsHtml))
@endif

<p>{{ $pagemenu }}<br />{{ $browsemenu }}</p>
