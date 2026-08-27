<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


if (!empty(\request()->input('view'))) {
    $view = trim(\request()->input('view'), "/.");
    $view = str_replace(".", "/", $view);
    $viewFile = ROOT_PATH . "resources/views/$view";

    if (!str_ends_with($viewFile, ".php")) {
        $viewFile .= ".php";
    }
    if (file_exists($viewFile)) {
        require $viewFile;
    } else {
        $msg = "viewFile: $viewFile not exists, _REQUEST: " . json_encode(\request()->all());
        \App\Support\Logger::writeWithContext((string) $msg, (string) "error", (bool) false);
        throw new \RuntimeException($msg);
    }
} else {
    $msg = "require view parameter, _REQUEST: " . json_encode(\request()->all());
    \App\Support\Logger::writeWithContext((string) $msg, (string) "error", (bool) false);
    abort(400, 'require view parameter');
}
