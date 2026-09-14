@extends('layouts.legacy')

@section('title', $lang_getrss['head_rss_feeds'] ?? 'RSS Feeds')

@section('content')
<h1 align="center">{{ $lang_getrss['text_rss_feeds'] }}</h1>
<form method="post" action="getrss.php">
@csrf
<table cellspacing="1" cellpadding="5" width="97%">
<tr>
<td class="rowhead">{{ $lang_getrss['row_categories_to_retrieve'] }}
</td>
<td class="rowfollow" align="left">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($categories ?? ''))
</td>
</tr>
<tr>
<td class="rowhead">{{ $lang_getrss['row_show_bookmarked'] }}
</td>
<td class="rowfollow" align="left">
<input type="radio" name="inclbookmarked" id="inclbookmarked0" value="0" checked="checked" /><label for="inclbookmarked0">{{ $lang_getrss['text_all'] }}</label>&nbsp;<input type="radio" name="inclbookmarked" id="inclbookmarked1" value="1" /><label for="inclbookmarked1">{{ $lang_getrss['text_only_bookmarked'] }}</label><div>{{ $lang_getrss['text_show_bookmarked_note'] }}</div>
</td>
</tr>
    <tr>
        <td class="rowhead">{{ $lang_getrss['row_sticky'] }}
        </td>
        <td class="rowfollow" align="left">
            @foreach ($stickyTypes as $key => $value)
            <label><input type="checkbox" name="sticky[]" value="{{ $key }}">{{ $value }}</label>
            @endforeach
        </td>
    </tr>
<tr>
    @if ($paidTorrentEnabled)
<tr>
    <td class="rowhead">{{ $lang_getrss['row_paid'] }}
    </td>
    <td class="rowfollow" align="left">
        <label><input type="radio" name="paid" value="0" checked>{{ $lang_getrss['paid_no'] }}</label>
        <label><input type="radio" name="paid" value="1">{{ $lang_getrss['paid_yes'] }}</label>
        <label><input type="radio" name="paid" value="2">{{ $lang_getrss['paid_all'] }}</label>
        <div>{{ $lang_getrss['row_paid_help'] }}</div>
    </td>
</tr>
    @endif
<td class="rowhead">{{ $lang_getrss['row_item_title_type'] }}
</td>
<td class="rowfollow" align="left">
<input type="checkbox" name="itemcategory" value="1" />{{ $lang_getrss['text_item_category'] }}&nbsp;<input type="checkbox" name="itemtitle" checked="checked" disabled="disabled" />{{ $lang_getrss['text_item_title'] }}&nbsp;<input type="checkbox" name="itemsize" value="1" />{{ $lang_getrss['text_item_size'] }}&nbsp;<input type="checkbox" name="itemuploader" value="1" />{{ $lang_getrss['text_item_uploader'] }}
</td>
</tr>
<tr><td class="rowhead">{{ $lang_getrss['row_rows_per_page'] }}</td><td class="rowfollow" align="left"><select name="showrows">
@foreach ($allowed_showrows as $showrow)
<option value="{{ $showrow }}">{{ $showrow }}</option>
@endforeach
</select></td></tr>
<tr>
<td colspan="2" align="center">
<input type="submit" value="{{ $lang_getrss['submit_generatte_rss_link'] }}" />
</td>
</tr>
</table>
</form>
@endsection
