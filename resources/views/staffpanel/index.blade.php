@extends('layouts.app')

@section('title', ('Administration'))

@section('content')
<x-page :title="'Administration'" width="list">

@include('staffpanel._group', ['title' => 'For SysOp Only', 'rows' => $sysopPanels])
@include('staffpanel._group', ['title' => 'For Administrator Only', 'rows' => $adminPanels])
@include('staffpanel._group', ['title' => 'For Moderator Only', 'rows' => $modPanels])
</x-page>
@endsection
