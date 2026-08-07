<?php
extract($context, EXTR_SKIP);
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);


if (!empty(\App\Support\SupportContext::getRequestInput('view'))) {
    $view = trim(\App\Support\SupportContext::getRequestInput('view'), "/.");
    $view = str_replace(".", "/", $view);
    if (!empty(\App\Support\SupportContext::getRequestInput('plugin'))) {
        $pluginId = \App\Support\SupportContext::getRequestInput('plugin');
        $plugin = \Nexus\Plugin\Plugin::getById($pluginId);
        $viewFile = $plugin->getNexusView($view);
    } else {
        $viewFile = ROOT_PATH . "resources/views/$view";
    }

    if (!str_ends_with($viewFile, ".php")) {
        $viewFile .= ".php";
    }
    if (file_exists($viewFile)) {
        require $viewFile;
    } else {
        $msg = "viewFile: $viewFile not exists, _REQUEST: " . json_encode(\App\Support\SupportContext::allRequest());
        do_log($msg, "error");
        throw new \RuntimeException($msg);
    }
} else {
    $msg = "require view parameter, _REQUEST: " . json_encode(\App\Support\SupportContext::allRequest());
    do_log($msg, "error");
    throw new \RuntimeException($msg);
}
