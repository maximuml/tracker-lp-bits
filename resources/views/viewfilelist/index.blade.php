@isset($CURUSER)
{{-- .fileicon rules live in css/nxt.css: this fragment is AJAX-injected
     into an already-loaded page, so an inline <style> here always carries a
     nonce for the wrong request and CSP refuses it. --}}
<x-data-table class="nx-main" :caption="__('viewfilelist.col_path')" :caption-hidden="true">
    <x-slot:head>
        <thead>
            <tr>
                <th scope="col">{{ __('viewfilelist.col_path') }}</th>
                <th scope="col"><i class="size" role="img" aria-label="{{ __('viewfilelist.col_size') }}"></i></th>
            </tr>
        </thead>
    </x-slot:head>
    @forelse ($files as $file)
        <tr><td><span class="fileicon fi-{{ $file['badge']['cat'] }}" title="{{ $file['badge']['cat'] }}">{{ $file['badge']['label'] }}</span>{{ $file['filename'] }}</td><td class="text-right">{{ $file['size'] }}</td></tr>
    @empty
        <tr><td colspan="2" class="text-nxm-text-dim">{{ __('viewfilelist.text_no_files') }}</td></tr>
    @endforelse
</x-data-table>
@endisset
