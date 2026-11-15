<x-page :title="$title ?? 'Administrative User Search'" width="list">

@if ($vm->showHelp)
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
<p class="text-center">(<a href="{{ $vm->requestUri }}?h=1">{{ __('usersearch.instructions') }}</a>)
&nbsp;-&nbsp;(<a href="{{ $vm->requestUri }}">{{ __('label.reset') }}</a>)</p>
@endif

<form method=get action="{{ $vm->requestUri }}">
<div class="nx-fgrid nx-fgrid--6">
<div class="nx-grouprow">
<label class="nx-fhead" for="us-n">Name:</label>
<div class="nx-fcell {{ $vm->form['n_hl'] ?? '' }}"><input id="us-n" name="n" type="text" value="{{ $vm->form['n'] }}" size=35></div>
<label class="nx-fhead" for="us-rt">Ratio:</label>
<div class="nx-fcell {{ $vm->form['r_hl'] ?? '' }}"><select id="us-rt" name="rt">
@include('components.usersearch.options', ['options' => $vm->form['rt_options'] ?? []])
    </select>
    <input id="us-r" name="r" type="text" value="{{ $vm->form['r'] }}" size="5" maxlength="4" aria-label="{{ __('usersearch.ratio_value') }}">
    <input id="us-r2" name="r2" type="text" value="{{ $vm->form['r2'] }}" size="5" maxlength="4" aria-label="{{ __('usersearch.ratio_second') }}"></div>
<label class="nx-fhead" for="us-st">{{ __('usersearch.member_status') }}</label>
<div class="nx-fcell {{ $vm->form['st_hl'] ?? '' }}"><select id="us-st" name="st">
@include('components.usersearch.options', ['options' => $vm->form['st_options'] ?? []])
    </select></div>
</div>
<div class="nx-grouprow">
<label class="nx-fhead" for="us-em">Email:</label>
<div class="nx-fcell {{ $vm->form['em_hl'] ?? '' }}"><input id="us-em" name="em" type="text" value="{{ $vm->form['em'] }}" size="35"></div>
<label class="nx-fhead" for="us-ip">IP:</label>
<div class="nx-fcell {{ $vm->form['ip_hl'] ?? '' }}"><input id="us-ip" name="ip" type="text" value="{{ $vm->form['ip'] }}" maxlength="64"></div>
<label class="nx-fhead" for="us-as">{{ __('usersearch.account_status') }}</label>
<div class="nx-fcell {{ $vm->form['as_hl'] ?? '' }}"><select id="us-as" name="as">
@include('components.usersearch.options', ['options' => $vm->form['as_options'] ?? []])
    </select></div>
</div>
<div class="nx-grouprow">
<label class="nx-fhead" for="us-co">Comment:</label>
<div class="nx-fcell {{ $vm->form['co_hl'] ?? '' }}"><input id="us-co" name="co" type="text" value="{{ $vm->form['co'] }}" size="35"></div>
<label class="nx-fhead" for="us-ma">Mask:</label>
<div class="nx-fcell {{ $vm->form['ma_hl'] ?? '' }}"><input id="us-ma" name="ma" type="text" value="{{ $vm->form['ma'] }}" maxlength="17"></div>
<label class="nx-fhead" for="us-c">Class:</label>
<div class="nx-fcell {{ $vm->form['c_hl'] ?? '' }}"><select id="us-c" name="c">@include('components.usersearch.options', ['options' => $vm->form['c_options'] ?? []])
    </select></div>
</div>
<div class="nx-grouprow">
<label class="nx-fhead" for="us-dt">Joined:</label>
<div class="nx-fcell {{ $vm->form['d_hl'] ?? '' }}"><select id="us-dt" name="dt">
@include('components.usersearch.options', ['options' => $vm->form['dt_options'] ?? []])
    </select>
    <input name="d" type="text" value="{{ $vm->form['d'] }}" size="12" maxlength="10" aria-label="{{ __('usersearch.joined_date') }}">
    <input name="d2" type="text" value="{{ $vm->form['d2'] }}" size="12" maxlength="10" aria-label="{{ __('usersearch.joined_second') }}"></div>
<label class="nx-fhead" for="ult">Uploaded:</label>
<div class="nx-fcell {{ $vm->form['ul_hl'] ?? '' }}"><select name="ult" id="ult">
@include('components.usersearch.options', ['options' => $vm->form['ult_options'] ?? []])
    </select>
    <input name="ul" type="text" id="ul" size="8" maxlength="7" value="{{ $vm->form['ul'] }}" aria-label="{{ __('usersearch.uploaded_gb') }}">
    <input name="ul2" type="text" id="ul2" size="8" maxlength="7" value="{{ $vm->form['ul2'] }}" aria-label="{{ __('usersearch.uploaded_gb_second') }}"></div>
<label class="nx-fhead" for="us-do">Donor:</label>
<div class="nx-fcell {{ $vm->form['do_hl'] ?? '' }}"><select id="us-do" name="do">
@include('components.usersearch.options', ['options' => $vm->form['do_options'] ?? []])
	</select></div>
</div>
<div class="nx-grouprow">
<label class="nx-fhead" for="us-lst">{{ __('usersearch.last_seen') }}</label>
<div class="nx-fcell {{ $vm->form['ls_hl'] ?? '' }}"><select id="us-lst" name="lst">
@include('components.usersearch.options', ['options' => $vm->form['lst_options'] ?? []])
  </select>
  <input name="ls" type="text" value="{{ $vm->form['ls'] }}" size="12" maxlength="10" aria-label="{{ __('usersearch.last_seen_date') }}">
  <input name="ls2" type="text" value="{{ $vm->form['ls2'] }}" size="12" maxlength="10" aria-label="{{ __('usersearch.last_seen_second') }}"></div>
<label class="nx-fhead" for="dlt">Downloaded:</label>
<div class="nx-fcell {{ $vm->form['dl_hl'] ?? '' }}"><select name="dlt" id="dlt">
@include('components.usersearch.options', ['options' => $vm->form['dlt_options'] ?? []])
    </select>
    <input name="dl" type="text" id="dl" size="8" maxlength="7" value="{{ $vm->form['dl'] }}" aria-label="{{ __('usersearch.downloaded_gb') }}">
    <input name="dl2" type="text" id="dl2" size="8" maxlength="7" value="{{ $vm->form['dl2'] }}" aria-label="{{ __('usersearch.downloaded_gb_second') }}"></div>
<label class="nx-fhead" for="us-w">Warned:</label>
<div class="nx-fcell {{ $vm->form['w_hl'] ?? '' }}"><select id="us-w" name="w">
@include('components.usersearch.options', ['options' => $vm->form['w_options'] ?? []])
	</select></div>
</div>
<div class="nx-grouprow">
<div class="nx-fhead"></div>
<div class="nx-fcell"></div>
<label class="nx-fhead" for="us-ac">{{ __('usersearch.active_only') }}</label>
<div class="nx-fcell {{ $vm->form['ac_hl'] ?? '' }}"><input id="us-ac" name="ac" type="checkbox" value="1" {{ $vm->form['ac'] ? 'checked' : '' }}></div>
<label class="nx-fhead" for="us-dip">{{ __('usersearch.disabled_ip') }} </label>
<div class="nx-fcell {{ $vm->form['dip_hl'] ?? '' }}"><input id="us-dip" name="dip" type="checkbox" value="1" {{ $vm->form['dip'] ? 'checked' : '' }}></div>
</div>
<div class="nx-grouprow">
<div class="nx-ffull text-center"><input name="submit" type="submit" class="nx-btn nx-btn--primary"></div>
</div>
</div>
<br /><br />
</form>

@if ($vm->resultsError)
{{ $vm->resultsError }}
@elseif ($vm->hasResults && $vm->results !== null)
@if ($vm->results->emptyMessage !== null)
{{ $vm->results->emptyMessage }}
@else
@include('components.usersearch.results', ['results' => $vm->results])
@endif
@endif

<p>{{ $vm->pagemenu }}<br />{{ $vm->browsemenu }}</p>
</x-page>
