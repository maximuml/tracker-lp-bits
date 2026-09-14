@extends('layouts.legacy')

@section('title', $title ?? (($CURUSER['username'] ?? '') . ($lang_mybonus['head_karma_page'] ?? ' - Karma')))

@section('content')
@include('my.sections.bonus', ['action' => $action ?? ''])
@endsection
