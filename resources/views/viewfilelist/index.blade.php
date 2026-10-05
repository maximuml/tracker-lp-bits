@isset($CURUSER)
{{-- .fileicon rules live in styles/nexus.css: this fragment is AJAX-injected
     into an already-loaded page, so an inline <style> here always carries a
     nonce for the wrong request and CSP refuses it. --}}
<x-data-table class="main" :caption="__('legacy/viewfilelist.col_path')" :caption-hidden="true">
    <x-slot:head>
        <thead>
            <tr>
                <th scope="col">{{ __('legacy/viewfilelist.col_path') }}</th>
                <th scope="col"><img class="size" src="pic/trans.gif" alt="size" /></th>
            </tr>
        </thead>
    </x-slot:head>
    @foreach ($files as $file)
        <tr><td><span class="fileicon fi-{{ $file['badge']['cat'] }}" title="{{ $file['badge']['cat'] }}">{{ $file['badge']['label'] }}</span>{{ $file['filename'] }}</td><td class="text-right">{{ $file['size'] }}</td></tr>
    @endforeach
</x-data-table>
@endisset
