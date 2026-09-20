@extends('layouts.legacy')

@section('title', '')

@section('content')
<x-std-message :heading="__('legacy/takecontact.std_succeeded')" :text="__('legacy/takecontact.std_message_succesfully_sent')" :htmlstrip="false" />

@endsection
