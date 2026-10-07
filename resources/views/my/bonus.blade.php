@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', $title ?? (($CURUSER['username'] ?? '') . (__('mybonus.head_karma_page'))))

@section('content')
@include('my.sections.bonus', ['action' => $action ?? ''])
@endsection
