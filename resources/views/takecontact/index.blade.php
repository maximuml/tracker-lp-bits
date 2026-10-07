@extends('layouts.app', ['chromeVariant' => 'legacy'])

@section('title', '')

@section('content')
<x-std-message :heading="__('takecontact.std_succeeded')" :text="__('takecontact.std_message_succesfully_sent')" :htmlstrip="false" />

@endsection
