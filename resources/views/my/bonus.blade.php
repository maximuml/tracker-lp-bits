@extends('layouts.legacy')

@section('title', $title ?? (($CURUSER['username'] ?? '') . (__('legacy/mybonus.head_karma_page'))))

@section('content')
@include('my.sections.bonus', ['action' => $action ?? ''])
@endsection
