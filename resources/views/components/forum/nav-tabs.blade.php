@props(['active'])
<x-tabs :tabs="[
    ['id' => 'forums', 'url' => request()->getPathInfo(), 'label' => trim(__('forums.text_forums'))],
    ['id' => 'search', 'url' => request()->getPathInfo().'?action=search', 'label' => __('forums.text_search')],
    ['id' => 'viewunread', 'url' => request()->getPathInfo().'?action=viewunread', 'label' => __('forums.text_view_unread')],
    ['id' => 'catchup', 'url' => request()->getPathInfo().'?catchup=1', 'label' => __('forums.text_catch_up')],
]" :active="$active" :label="__('forums.head_forums')" />
