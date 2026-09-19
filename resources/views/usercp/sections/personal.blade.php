@include('usercp.sections._menu', ['selected' => 'personal'])

<form method=post action=usercp.php id="{{ $personal['formId'] ?? '' }}"><input type=hidden name=action value=personal><input type=hidden name=type value=save>
<div class="nx-fgrid nx-fgrid--flat">
@if ($type === 'saved')
<div class="nx-ffull nx-center"><span class="nx-color-red"><b>{{ __('legacy/usercp.text_saved')}}</b></span></div>
@endif
{{ $personal['rowsHtml'] ?? '' }}
<div class="nx-fhead">{{ __('legacy/usercp.row_save_settings')}}</div><div class="nx-fcell"><input type=submit value="{{ __('legacy/usercp.submit_save_settings')}}"></div>
</div></form>
