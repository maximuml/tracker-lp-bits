<h1>Administrative User Search</h1>

@if ($showHelp)
<div class="nx-panel nx-embedded"><div align=left>
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
	</div></div><br /><br />
@else
<p align=center>(<a href="{{ $requestUri }}?h=1">Instructions</a>)
&nbsp;-&nbsp;(<a href="{{ $requestUri }}">Reset</a>)</p>
@endif

<form method=get action="{{ $requestUri }}">
<div class="nx-fgrid nx-fgrid--6">
<div class="nx-grouprow">
<div class="nx-fhead">Name:</div>
<div class="nx-fcell {{ $form['n_hl'] ?? '' }}"><input name="n" type="text" value="{{ $form['n'] }}" size=35></div>
<div class="nx-fhead">Ratio:</div>
<div class="nx-fcell {{ $form['r_hl'] ?? '' }}"><select name="rt">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['rt_options'] ?? ''))
    </select>
    <input name="r" type="text" value="{{ $form['r'] }}" size="5" maxlength="4">
    <input name="r2" type="text" value="{{ $form['r2'] }}" size="5" maxlength="4"></div>
<div class="nx-fhead">Member status:</div>
<div class="nx-fcell {{ $form['st_hl'] ?? '' }}"><select name="st">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['st_options'] ?? ''))
    </select></div>
</div>
<div class="nx-grouprow">
<div class="nx-fhead">Email:</div>
<div class="nx-fcell {{ $form['em_hl'] ?? '' }}"><input name="em" type="text" value="{{ $form['em'] }}" size="35"></div>
<div class="nx-fhead">IP:</div>
<div class="nx-fcell {{ $form['ip_hl'] ?? '' }}"><input name="ip" type="text" value="{{ $form['ip'] }}" maxlength="64"></div>
<div class="nx-fhead">Account status:</div>
<div class="nx-fcell {{ $form['as_hl'] ?? '' }}"><select name="as">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['as_options'] ?? ''))
    </select></div>
</div>
<div class="nx-grouprow">
<div class="nx-fhead">Comment:</div>
<div class="nx-fcell {{ $form['co_hl'] ?? '' }}"><input name="co" type="text" value="{{ $form['co'] }}" size="35"></div>
<div class="nx-fhead">Mask:</div>
<div class="nx-fcell {{ $form['ma_hl'] ?? '' }}"><input name="ma" type="text" value="{{ $form['ma'] }}" maxlength="17"></div>
<div class="nx-fhead">Class:</div>
<div class="nx-fcell {{ $form['c_hl'] ?? '' }}"><select name="c">@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['c_options'] ?? ''))
    </select></div>
</div>
<div class="nx-grouprow">
<div class="nx-fhead">Joined:</div>
<div class="nx-fcell {{ $form['d_hl'] ?? '' }}"><select name="dt">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['dt_options'] ?? ''))
    </select>
    <input name="d" type="text" value="{{ $form['d'] }}" size="12" maxlength="10">
    <input name="d2" type="text" value="{{ $form['d2'] }}" size="12" maxlength="10"></div>
<div class="nx-fhead">Uploaded:</div>
<div class="nx-fcell {{ $form['ul_hl'] ?? '' }}"><select name="ult" id="ult">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['ult_options'] ?? ''))
    </select>
    <input name="ul" type="text" id="ul" size="8" maxlength="7" value="{{ $form['ul'] }}">
    <input name="ul2" type="text" id="ul2" size="8" maxlength="7" value="{{ $form['ul2'] }}"></div>
<div class="nx-fhead">Donor:</div>
<div class="nx-fcell {{ $form['do_hl'] ?? '' }}"><select name="do">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['do_options'] ?? ''))
	</select></div>
</div>
<div class="nx-grouprow">
<div class="nx-fhead">Last seen:</div>
<div class="nx-fcell {{ $form['ls_hl'] ?? '' }}"><select name="lst">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['lst_options'] ?? ''))
  </select>
  <input name="ls" type="text" value="{{ $form['ls'] }}" size="12" maxlength="10">
  <input name="ls2" type="text" value="{{ $form['ls2'] }}" size="12" maxlength="10"></div>
<div class="nx-fhead">Downloaded:</div>
<div class="nx-fcell {{ $form['dl_hl'] ?? '' }}"><select name="dlt" id="dlt">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['dlt_options'] ?? ''))
    </select>
    <input name="dl" type="text" id="dl" size="8" maxlength="7" value="{{ $form['dl'] }}">
    <input name="dl2" type="text" id="dl2" size="8" maxlength="7" value="{{ $form['dl2'] }}"></div>
<div class="nx-fhead">Warned:</div>
<div class="nx-fcell {{ $form['w_hl'] ?? '' }}"><select name="w">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($form['w_options'] ?? ''))
	</select></div>
</div>
<div class="nx-grouprow">
<div class="nx-fhead"></div>
<div class="nx-fcell"></div>
<div class="nx-fhead">Active only:</div>
<div class="nx-fcell {{ $form['ac_hl'] ?? '' }}"><input name="ac" type="checkbox" value="1" {{ $form['ac'] ? 'checked' : '' }}></div>
<div class="nx-fhead">Disabled IP: </div>
<div class="nx-fcell {{ $form['dip_hl'] ?? '' }}"><input name="dip" type="checkbox" value="1" {{ $form['dip'] ? 'checked' : '' }}></div>
</div>
<div class="nx-grouprow">
<div class="nx-ffull nx-center"><input name="submit" type=submit class=btn></div>
</div>
</div>
<br /><br />
</form>

@if ($resultsError)
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($resultsError))
@elseif ($hasResults)
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($resultsHtml))
@endif

<p>{{ $pagemenu }}<br />{{ $browsemenu }}</p>
