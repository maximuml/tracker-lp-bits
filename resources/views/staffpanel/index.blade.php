@extends('layouts.app')

@section('title', ('Administration'))

@section('content')
<x-page :title="'Administration'" width="list">

@include('staffpanel._group', ['title' => 'For SysOp Only', 'rows' => $vm->sysopPanels])
@include('staffpanel._group', ['title' => 'For Administrator Only', 'rows' => $vm->adminPanels])
@include('staffpanel._group', ['title' => 'For Moderator Only', 'rows' => $vm->modPanels])
</x-page>
@endsection
