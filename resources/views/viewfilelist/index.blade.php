@isset($CURUSER)
{{-- .fileicon rules live in styles/nexus.css: this fragment is AJAX-injected
     into an already-loaded page, so an inline <style> here always carries a
     nonce for the wrong request and CSP refuses it. --}}
<table data-nx="data" class="main"><caption class="nx-sr-only">{{ __('legacy/viewfilelist.col_path') }}</caption>
<tr><th class="colhead" scope="col">{{ __('legacy/viewfilelist.col_path') }}</th><th class="colhead" scope="col"><img class="size" src="pic/trans.gif" alt="size" /></th></tr>
@foreach ($files as $file)
<tr><td class="rowfollow"><span class="fileicon fi-{{ $file['badge']['cat'] }}" title="{{ $file['badge']['cat'] }}">{{ $file['badge']['label'] }}</span>{{ $file['filename'] }}</td><td class="rowfollow nx-align-right">{{ $file['size'] }}</td></tr>
@endforeach
</table>
@endisset
