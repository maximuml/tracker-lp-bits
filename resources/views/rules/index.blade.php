@extends('layouts.legacy')

@section('title', $lang_rules['head_rules'] ?? '')

@section('content')
@php
$Cache->new_page('rules', 900, true);
$showRules = ! $Cache->get_page();
if ($showRules) {
    $Cache->add_whole_row();
    $lang_id = \App\Support\Locale::guestIdWithContext();
    $is_rulelang = \Nexus\Database\NexusDB::table('language')->where('id', $lang_id)->value('rule_lang');
    if (! $is_rulelang) {
        $lang_id = 6; // English
    }
    $rules = \Nexus\Database\NexusDB::table('rules')->where('lang_id', $lang_id)->orderBy('id')->get();
}
@endphp

@if ($showRules)
    @php \App\Support\Frame::mainFrameOpen(); @endphp
    @foreach ($rules as $rule)
        @php $arr = (array) $rule; @endphp
        @php \App\Support\Html::beginFrame($arr['title'], false); @endphp
        {!! \App\Support\Format::formatComment($arr['text']) !!}
        @php \App\Support\Html::endFrame(); @endphp
    @endforeach
    @php \App\Support\Frame::mainFrameClose(); @endphp
@endif
@endsection
