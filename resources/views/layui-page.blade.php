<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link rel="stylesheet" href="/styles/sprites.css">
    <link rel="stylesheet" href="/vendor/layui/css/layui.css">
    @stack('css')
    <script type="text/javascript" src="/js/csrf.js"></script>
    <script type="text/javascript" src="/vendor/layui/layui.js"></script>
    @stack('scripts')
</head>
<body>
@yield('content')
</body>
</html>
