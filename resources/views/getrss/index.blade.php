@extends('layouts.app')

@section('title', __('getrss.head_rss_feeds'))

@section('content')
<x-page :title="__('getrss.text_rss_feeds')" width="form">
<form method="post" action="/web/rss/generate">
@csrf
<div class="nx-fgrid nx-fgrid--flat w-[97%]">
<div class="nx-fhead">{{ __('getrss.row_categories_to_retrieve') }}
</div>
<div class="nx-fcell">
<x-search-category-table :vm="$categoriesTable" />
</div>
<div class="nx-fhead">{{ __('getrss.row_show_bookmarked') }}
</div>
<div class="nx-fcell">
<input type="radio" name="inclbookmarked" id="inclbookmarked0" value="0" checked="checked" /><label for="inclbookmarked0">{{ __('getrss.text_all') }}</label>&nbsp;<input type="radio" name="inclbookmarked" id="inclbookmarked1" value="1" /><label for="inclbookmarked1">{{ __('getrss.text_only_bookmarked') }}</label><div>{{ __('getrss.text_show_bookmarked_note') }}</div>
</div>
        <div class="nx-fhead">{{ __('getrss.row_sticky') }}
        </div>
        <div class="nx-fcell">
            @foreach ($stickyTypes as $key => $value)
            <label><input type="checkbox" name="sticky[]" value="{{ $key }}">{{ $value }}</label>
            @endforeach
        </div>
    @if ($paidTorrentEnabled)
    <div class="nx-fhead">{{ __('getrss.row_paid') }}
    </div>
    <div class="nx-fcell">
        <label><input type="radio" name="paid" value="0" checked>{{ __('getrss.paid_no') }}</label>
        <label><input type="radio" name="paid" value="1">{{ __('getrss.paid_yes') }}</label>
        <label><input type="radio" name="paid" value="2">{{ __('getrss.paid_all') }}</label>
        <div>{{ __('getrss.row_paid_help') }}</div>
    </div>
    @endif
<div class="nx-fhead">{{ __('getrss.row_item_title_type') }}
</div>
<div class="nx-fcell">
<label><input type="checkbox" name="itemcategory" value="1" />{{ __('getrss.text_item_category') }}</label>&nbsp;<label><input type="checkbox" name="itemtitle" checked="checked" disabled="disabled" />{{ __('getrss.text_item_title') }}</label>&nbsp;<label><input type="checkbox" name="itemsize" value="1" />{{ __('getrss.text_item_size') }}</label>&nbsp;<label><input type="checkbox" name="itemuploader" value="1" />{{ __('getrss.text_item_uploader') }}</label>
</div>
<label class="nx-fhead" for="showrows">{{ __('getrss.row_rows_per_page') }}</label><div class="nx-fcell"><select name="showrows" id="showrows">
@foreach ($allowed_showrows as $showrow)
<option value="{{ $showrow }}">{{ $showrow }}</option>
@endforeach
</select></div>
<div class="nx-ffull text-center">
<input type="submit" class="nx-btn nx-btn--primary" value="{{ __('getrss.submit_generatte_rss_link') }}" />
</div>
</div>
</form>
</x-page>
@endsection
