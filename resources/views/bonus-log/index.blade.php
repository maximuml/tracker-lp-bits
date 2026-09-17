@extends('layouts.legacy')

@section('title', $title)

@section('content')
<h1 align=center>{{ $title }}<a href="userdetails.php?id={{ (int) $uid }}"><b>&nbsp;{{ $username }}</b></a></h1>

<div>
    <form id="filterForm" action="{{ $requestUri }}" method="get">
        <input type="hidden" name="uid" value="{{ (int) $uid }}" />
        <span>{{ $categoryText }}:</span>
        <select name="category">
            @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($categoryOptionsHtml))
        </select>
        &nbsp;&nbsp;
        <span>{{ $businessTypeText }}:</span>
        <select name="business_type">
            <option value="0">-{{ $textSelectOnePlease }}-</option>
            @safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($businessTypeOptionsHtml))
        </select>
        &nbsp;&nbsp;
        <input type="submit" value="{{ $submitText }}">
        <input type="button" id="reset" value="{{ $resetText }}">
    </form>
</div>

<table data-nx="data" id='bonus-log-table' width='100%' cellpadding='5'>
<tr>
    <td class='colhead' align='left'>{{ $columnBusinessTypeLabel }}</td>
    <td class='colhead' align='left'>{{ $columnOldTotalLabel }}</td>
    <td class='colhead' align='left'>{{ $columnValueLabel }}</td>
    <td class='colhead' align='left'>{{ $columnNewTotalLabel }}</td>
    <td class='colhead' align='left'>{{ $columnCommentLabel }}</td>
    <td class='colhead' align='left'>{{ $columnCreatedAtLabel }}</td>
</tr>
@foreach ($rows as $row)
<tr>
    <td class='rowfollow nowrap' align='left'>{{ $row['businessTypeText'] }}</td>
    <td class='rowfollow nowrap' align='left'>{{ $row['old_formatted'] }}</td>
    <td class='rowfollow nowrap' align='left'>{{ $row['value_formatted'] }}</td>
    <td class='rowfollow nowrap' align='left'>{{ $row['new_formatted'] }}</td>
    <td class='rowfollow nowrap' align='left'>{{ $row['comment'] }}</td>
    <td class='rowfollow nowrap' align='left'>{{ $row['created_at'] }}</td>
</tr>
@endforeach
</table>
{{ $pagerbottom ?? '' }}
@endsection
