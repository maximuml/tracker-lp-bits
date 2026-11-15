@extends('layouts.app', ['shell' => 'bare'])

@section('title', $vm->pageTitle)

@section('content')
    @include('components.bitbucket.upload', [
        'maxFileSize' => $vm->maxFileSize,
        'scaleHeight' => $vm->scaleHeight,
        'scaleWidth' => $vm->scaleWidth,
        'bucketWritable' => $vm->bucketWritable,
    ])
@endsection
