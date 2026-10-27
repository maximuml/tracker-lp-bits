<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Hello World!</title>
    <style>
        .guest-wrap { padding: 15px; display: flex; justify-content: center; }
        .guest-inner { max-width: 1120px; }
    </style>
</head>
<body>
<div class="guest-wrap">
    <div class="guest-inner">
        {{ $content }}
    </div>
</div>
</body>
</html>
