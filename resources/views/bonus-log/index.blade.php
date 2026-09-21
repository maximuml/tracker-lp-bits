@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', $title)

@section('content')
<h1 class="nx-center">{{ $title }}<a href="userdetails.php?id={{ (int) $uid }}"><b>&nbsp;{{ $username }}</b></a></h1>

<div>
    <form id="filterForm" action="{{ $requestUri }}" method="get">
        <input type="hidden" name="uid" value="{{ (int) $uid }}" />
        <span>{{ $categoryText }}:</span>
        <select name="category">
            @foreach ($categoryOptionList as $o)
                <option value="{{ $o['value'] }}" @if ($o['selected']) selected @endif>{{ $o['label'] }}</option>
            @endforeach
        </select>
        &nbsp;&nbsp;
        <span>{{ $businessTypeText }}:</span>
        <select name="business_type">
            <option value="0">-{{ $textSelectOnePlease }}-</option>
            @foreach ($businessTypeOptionList as $o)
                <option value="{{ $o['value'] }}" @if ($o['selected']) selected @endif>{{ $o['label'] }}</option>
            @endforeach
        </select>
        &nbsp;&nbsp;
        <input type="submit" value="{{ $submitText }}">
        <input type="button" id="reset" value="{{ $resetText }}">
    </form>
</div>

<table data-nx="data" id='bonus-log-table'><caption class="nx-sr-only">{{ $title }}</caption>
<tr>
    <th class="colhead nx-align-left" scope="col">{{ $columnBusinessTypeLabel }}</th>
    <th class="colhead nx-align-left" scope="col">{{ $columnOldTotalLabel }}</th>
    <th class="colhead nx-align-left" scope="col">{{ $columnValueLabel }}</th>
    <th class="colhead nx-align-left" scope="col">{{ $columnNewTotalLabel }}</th>
    <th class="colhead nx-align-left" scope="col">{{ $columnCommentLabel }}</th>
    <th class="colhead nx-align-left" scope="col">{{ $columnCreatedAtLabel }}</th>
</tr>
@foreach ($rows as $row)
<tr>
    <td class='rowfollow nowrap'>{{ $row['businessTypeText'] }}</td>
    <td class='rowfollow nowrap'>{{ $row['old_formatted'] }}</td>
    <td class='rowfollow nowrap'>{{ $row['value_formatted'] }}</td>
    <td class='rowfollow nowrap'>{{ $row['new_formatted'] }}</td>
    <td class='rowfollow nowrap'>{{ $row['comment'] }}</td>
    <td class='rowfollow nowrap'>{{ $row['created_at'] }}</td>
</tr>
@endforeach
</table>
{{ $pagerbottom ?? '' }}
@endsection
