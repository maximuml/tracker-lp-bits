<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('viewpeerlist.head_title') }}</title>
</head>
<body>
@include('viewpeerlist.index', ['seederTable' => $seederTable, 'leecherTable' => $leecherTable])
</body>
</html>
