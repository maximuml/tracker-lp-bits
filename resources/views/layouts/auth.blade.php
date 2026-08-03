<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <title>@yield('title', $siteName ?? config('app.name'))</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; background: #f5f5f5; margin: 0; padding: 0; }
        .container { max-width: 600px; margin: 2em auto; background: #fff; border: 1px solid #ccc; padding: 1em; }
        h1 { text-align: center; font-size: 1.4em; }
        .error { color: #d00; margin: 1em 0; text-align: center; }
        table { width: 100%; border-collapse: collapse; }
        .rowhead { width: 30%; padding: 0.5em; background: #eee; font-weight: bold; text-align: right; }
        .rowfollow { padding: 0.5em; }
        input[type="text"], input[type="password"], input[type="email"], select { width: 100%; box-sizing: border-box; padding: 0.3em; }
        .btn { padding: 0.4em 1em; margin: 0.2em; }
        .toolbox { padding: 0.5em; text-align: center; background: #f0f0f0; }
        .small { font-size: 0.85em; }
        p { margin: 0.5em 0; }
        a { color: #06c; }
    </style>
</head>
<body>
    <div class="container">
        @yield('content')
    </div>
</body>
</html>
