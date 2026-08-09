@extends('layouts.nexus_legacy')

@section('title', $lang['head_original_comment'] ?? 'Original comment')

@section('content')
@php
echo '<h1>' . ($lang['text_original_content_of_comment'] ?? 'Original contents of comment ') . $commentId . '</h1>';
echo '<table width="737" border="1" cellspacing="0" cellpadding="5">';
echo '<tr><td class="text">';
echo \App\Support\Comment::format((string) $arr['ori_text']);
echo '</td></tr></table>';
if ($returnto) {
    echo '<p><font size="small">(<a href="' . e($returnto) . '">' . ($lang['text_back'] ?? 'back') . '</a>)</font></p>';
}
@endphp
@endsection
