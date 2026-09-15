@extends('layouts.legacy')

@section('title', $lang_getrss['head_rss_feeds'] ?? 'RSS Feeds')

@section('content')
<h1 align="center">{{ $lang_getrss['text_rss_feeds'] }}</h1>
<form method="post" action="getrss.php">
@csrf
<div class="nx-fgrid nx-fgrid--flat nx-w-97">
<div class="nx-fhead">{{ $lang_getrss['row_categories_to_retrieve'] }}
</div>
<div class="nx-fcell">
@safeHtml(\App\Support\Html\SafeHtml::fromTrustedHtml($categories ?? ''))
</div>
<div class="nx-fhead">{{ $lang_getrss['row_show_bookmarked'] }}
</div>
<div class="nx-fcell">
<input type="radio" name="inclbookmarked" id="inclbookmarked0" value="0" checked="checked" /><label for="inclbookmarked0">{{ $lang_getrss['text_all'] }}</label>&nbsp;<input type="radio" name="inclbookmarked" id="inclbookmarked1" value="1" /><label for="inclbookmarked1">{{ $lang_getrss['text_only_bookmarked'] }}</label><div>{{ $lang_getrss['text_show_bookmarked_note'] }}</div>
</div>
        <div class="nx-fhead">{{ $lang_getrss['row_sticky'] }}
        </div>
        <div class="nx-fcell">
            @foreach ($stickyTypes as $key => $value)
            <label><input type="checkbox" name="sticky[]" value="{{ $key }}">{{ $value }}</label>
            @endforeach
        </div>
    @if ($paidTorrentEnabled)
    <div class="nx-fhead">{{ $lang_getrss['row_paid'] }}
    </div>
    <div class="nx-fcell">
        <label><input type="radio" name="paid" value="0" checked>{{ $lang_getrss['paid_no'] }}</label>
        <label><input type="radio" name="paid" value="1">{{ $lang_getrss['paid_yes'] }}</label>
        <label><input type="radio" name="paid" value="2">{{ $lang_getrss['paid_all'] }}</label>
        <div>{{ $lang_getrss['row_paid_help'] }}</div>
    </div>
    @endif
<div class="nx-fhead">{{ $lang_getrss['row_item_title_type'] }}
</div>
<div class="nx-fcell">
<input type="checkbox" name="itemcategory" value="1" />{{ $lang_getrss['text_item_category'] }}&nbsp;<input type="checkbox" name="itemtitle" checked="checked" disabled="disabled" />{{ $lang_getrss['text_item_title'] }}&nbsp;<input type="checkbox" name="itemsize" value="1" />{{ $lang_getrss['text_item_size'] }}&nbsp;<input type="checkbox" name="itemuploader" value="1" />{{ $lang_getrss['text_item_uploader'] }}
</div>
<div class="nx-fhead">{{ $lang_getrss['row_rows_per_page'] }}</div><div class="nx-fcell"><select name="showrows">
@foreach ($allowed_showrows as $showrow)
<option value="{{ $showrow }}">{{ $showrow }}</option>
@endforeach
</select></div>
<div class="nx-ffull nx-center">
<input type="submit" value="{{ $lang_getrss['submit_generatte_rss_link'] }}" />
</div>
</div>
</form>
@endsection
