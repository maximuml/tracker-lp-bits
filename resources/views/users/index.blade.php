@extends('layouts.legacy')

@section('title', __('legacy/users.text_users'))

@section('content')
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml(__('legacy/users.text_users')))

<form method=get action=?>
{{ __('legacy/users.text_search')}} <input type=text style="width:100px" name=search value="{{ $search }}">
<select name=class>
<option value='-'>{{ __('legacy/users.select_any_class')}}</option>
@foreach ($classOptions as $opt)
<option value="{{ (int) $opt['value'] }}"@if ($opt['selected']) selected @endif>{{ $opt['label'] }}</option>
@endforeach
</select>
<select name=country>
@foreach ($countryOptions as $opt)
<option value="{{ (int) $opt['value'] }}"@if ($opt['selected']) selected @endif>{{ $opt['label'] }}</option>
@endforeach
</select>
<input type=submit value="{{ __('legacy/users.submit_okay')}}">
</form>

<p>
@foreach ($letterItems as $item)
@if ($item['href'] === null)
<font class=gray><b>{{ $item['label'] }}</b></font>
@else
<a href="{{ $item['href'] }}"><b>{{ $item['label'] }}</b></a>
@endif
@endforeach
</p>

@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagertop ?? ''))

<table data-nx="data" border=1 cellspacing=0 cellpadding=5>
<tr>
    <td class=colhead align=left>{{ __('legacy/users.col_user_name')}}</td>
    <td class=colhead>{{ __('legacy/users.col_registered')}}</td>
    <td class=colhead>{{ __('legacy/users.col_last_access')}}</td>
    <td class=colhead align=left>{{ __('legacy/users.col_class')}}</td>
    <td class=colhead>{{ __('legacy/users.col_country')}}</td>
</tr>
@foreach ($rows as $row)
<tr>
    <td align=left>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['username_html']))</td>
    <td>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['addedFormatted']))</td>
    <td>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['lastAccessFormatted']))</td>
    <td align=left>{{ $row['class_name'] }}</td>
    <td align=center>{{ $row['country'] }}</td>
</tr>
@endforeach
</table>

@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagerbottom ?? ''))
@endsection
