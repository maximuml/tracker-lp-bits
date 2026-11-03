<h1>Administrative User Search</h1>

@if ($showHelp)
<div class="nx-panel nx-embedded"><div>
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
<p class="text-center">(<a href="{{ $requestUri }}?h=1">Instructions</a>)
&nbsp;-&nbsp;(<a href="{{ $requestUri }}">Reset</a>)</p>
@endif

<form method=get action="{{ $requestUri }}">
<div class="nx-fgrid nx-fgrid--6">
<div class="nx-grouprow">
<label class="nx-fhead" for="us-n">Name:</label>
<div class="nx-fcell {{ $form['n_hl'] ?? '' }}"><input id="us-n" name="n" type="text" value="{{ $form['n'] }}" size=35></div>
<label class="nx-fhead" for="us-rt">Ratio:</label>
<div class="nx-fcell {{ $form['r_hl'] ?? '' }}"><select id="us-rt" name="rt">
@include('components.usersearch.options', ['options' => $form['rt_options'] ?? []])
    </select>
    <input id="us-r" name="r" type="text" value="{{ $form['r'] }}" size="5" maxlength="4" aria-label="Ratio value">
    <input id="us-r2" name="r2" type="text" value="{{ $form['r2'] }}" size="5" maxlength="4" aria-label="Ratio second value"></div>
<label class="nx-fhead" for="us-st">Member status:</label>
<div class="nx-fcell {{ $form['st_hl'] ?? '' }}"><select id="us-st" name="st">
@include('components.usersearch.options', ['options' => $form['st_options'] ?? []])
    </select></div>
</div>
<div class="nx-grouprow">
<label class="nx-fhead" for="us-em">Email:</label>
<div class="nx-fcell {{ $form['em_hl'] ?? '' }}"><input id="us-em" name="em" type="text" value="{{ $form['em'] }}" size="35"></div>
<label class="nx-fhead" for="us-ip">IP:</label>
<div class="nx-fcell {{ $form['ip_hl'] ?? '' }}"><input id="us-ip" name="ip" type="text" value="{{ $form['ip'] }}" maxlength="64"></div>
<label class="nx-fhead" for="us-as">Account status:</label>
<div class="nx-fcell {{ $form['as_hl'] ?? '' }}"><select id="us-as" name="as">
@include('components.usersearch.options', ['options' => $form['as_options'] ?? []])
    </select></div>
</div>
<div class="nx-grouprow">
<label class="nx-fhead" for="us-co">Comment:</label>
<div class="nx-fcell {{ $form['co_hl'] ?? '' }}"><input id="us-co" name="co" type="text" value="{{ $form['co'] }}" size="35"></div>
<label class="nx-fhead" for="us-ma">Mask:</label>
<div class="nx-fcell {{ $form['ma_hl'] ?? '' }}"><input id="us-ma" name="ma" type="text" value="{{ $form['ma'] }}" maxlength="17"></div>
<label class="nx-fhead" for="us-c">Class:</label>
<div class="nx-fcell {{ $form['c_hl'] ?? '' }}"><select id="us-c" name="c">@include('components.usersearch.options', ['options' => $form['c_options'] ?? []])
    </select></div>
</div>
<div class="nx-grouprow">
<label class="nx-fhead" for="us-dt">Joined:</label>
<div class="nx-fcell {{ $form['d_hl'] ?? '' }}"><select id="us-dt" name="dt">
@include('components.usersearch.options', ['options' => $form['dt_options'] ?? []])
    </select>
    <input name="d" type="text" value="{{ $form['d'] }}" size="12" maxlength="10" aria-label="Joined date">
    <input name="d2" type="text" value="{{ $form['d2'] }}" size="12" maxlength="10" aria-label="Joined second date"></div>
<label class="nx-fhead" for="ult">Uploaded:</label>
<div class="nx-fcell {{ $form['ul_hl'] ?? '' }}"><select name="ult" id="ult">
@include('components.usersearch.options', ['options' => $form['ult_options'] ?? []])
    </select>
    <input name="ul" type="text" id="ul" size="8" maxlength="7" value="{{ $form['ul'] }}" aria-label="Uploaded GB">
    <input name="ul2" type="text" id="ul2" size="8" maxlength="7" value="{{ $form['ul2'] }}" aria-label="Uploaded GB second value"></div>
<label class="nx-fhead" for="us-do">Donor:</label>
<div class="nx-fcell {{ $form['do_hl'] ?? '' }}"><select id="us-do" name="do">
@include('components.usersearch.options', ['options' => $form['do_options'] ?? []])
	</select></div>
</div>
<div class="nx-grouprow">
<label class="nx-fhead" for="us-lst">Last seen:</label>
<div class="nx-fcell {{ $form['ls_hl'] ?? '' }}"><select id="us-lst" name="lst">
@include('components.usersearch.options', ['options' => $form['lst_options'] ?? []])
  </select>
  <input name="ls" type="text" value="{{ $form['ls'] }}" size="12" maxlength="10" aria-label="Last seen date">
  <input name="ls2" type="text" value="{{ $form['ls2'] }}" size="12" maxlength="10" aria-label="Last seen second date"></div>
<label class="nx-fhead" for="dlt">Downloaded:</label>
<div class="nx-fcell {{ $form['dl_hl'] ?? '' }}"><select name="dlt" id="dlt">
@include('components.usersearch.options', ['options' => $form['dlt_options'] ?? []])
    </select>
    <input name="dl" type="text" id="dl" size="8" maxlength="7" value="{{ $form['dl'] }}" aria-label="Downloaded GB">
    <input name="dl2" type="text" id="dl2" size="8" maxlength="7" value="{{ $form['dl2'] }}" aria-label="Downloaded GB second value"></div>
<label class="nx-fhead" for="us-w">Warned:</label>
<div class="nx-fcell {{ $form['w_hl'] ?? '' }}"><select id="us-w" name="w">
@include('components.usersearch.options', ['options' => $form['w_options'] ?? []])
	</select></div>
</div>
<div class="nx-grouprow">
<div class="nx-fhead"></div>
<div class="nx-fcell"></div>
<label class="nx-fhead" for="us-ac">Active only:</label>
<div class="nx-fcell {{ $form['ac_hl'] ?? '' }}"><input id="us-ac" name="ac" type="checkbox" value="1" {{ $form['ac'] ? 'checked' : '' }}></div>
<label class="nx-fhead" for="us-dip">Disabled IP: </label>
<div class="nx-fcell {{ $form['dip_hl'] ?? '' }}"><input id="us-dip" name="dip" type="checkbox" value="1" {{ $form['dip'] ? 'checked' : '' }}></div>
</div>
<div class="nx-grouprow">
<div class="nx-ffull text-center"><input name="submit" type="submit" class="nx-btn nx-btn--primary"></div>
</div>
</div>
<br /><br />
</form>

@if ($resultsError)
{{ $resultsError }}
@elseif ($hasResults && $results !== null)
@if ($results->emptyMessage !== null)
{{ $results->emptyMessage }}
@else
@include('components.usersearch.results')
@endif
@endif

<p>{{ $pagemenu }}<br />{{ $browsemenu }}</p>
