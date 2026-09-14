@extends('layouts.legacy')

@section('title', $lang_users['text_users'] ?? 'Users')

@section('content')
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($lang_users['text_users'] ?? 'Users'))

<form method=get action=?>
{{ $lang_users['text_search'] ?? 'Search:' }} <input type=text style="width:100px" name=search value="{{ $search }}">
<select name=class>
<option value='-'>{{ $lang_users['select_any_class'] ?? 'Any class' }}</option>
@foreach ($classOptions as $opt)
<option value="{{ (int) $opt['value'] }}"@if ($opt['selected']) selected @endif>{{ $opt['label'] }}</option>
@endforeach
</select>
<select name=country>
@foreach ($countryOptions as $opt)
<option value="{{ (int) $opt['value'] }}"@if ($opt['selected']) selected @endif>{{ $opt['label'] }}</option>
@endforeach
</select>
<input type=submit value="{{ $lang_users['submit_okay'] ?? 'OK' }}">
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

<table border=1 cellspacing=0 cellpadding=5>
<tr>
    <td class=colhead align=left>{{ $lang_users['col_user_name'] ?? 'User name' }}</td>
    <td class=colhead>{{ $lang_users['col_registered'] ?? 'Registered' }}</td>
    <td class=colhead>{{ $lang_users['col_last_access'] ?? 'Last access' }}</td>
    <td class=colhead align=left>{{ $lang_users['col_class'] ?? 'Class' }}</td>
    <td class=colhead>{{ $lang_users['col_country'] ?? 'Country' }}</td>
</tr>
@foreach ($rows as $row)
<tr>
    <td align=left>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['username_html']))</td>
    <td>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['addedFormatted']))</td>
    <td>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($row['lastAccessFormatted']))</td>
    <td align=left>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml((string) $row['class_name']))</td>
    <td align=center>@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml((string) $row['country']))</td>
</tr>
@endforeach
</table>

@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($pagerbottom ?? ''))
@endsection
