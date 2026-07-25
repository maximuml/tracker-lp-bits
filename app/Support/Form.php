<?php

namespace App\Support;

/**
 * Legacy form-emitter helpers extracted from `include/functions.php`.
 *
 * Backs `datetimepicker_input()` and similar HTML/JS input builders.
 */
final class Form
{
    /**
     * Render a jQuery datetimepicker input and queue its assets.
     *
     * Mirrors `datetimepicker_input()`.
     */
    public static function datetimepickerInput(string $name, string $value = '', string $label = '', array $options = []): string
    {
        $lang = Locale::folderFromCookie($_COOKIE['c_lang_folder'] ?? null, true);
        if ($lang === 'zh_CN') {
            $lang = 'zh';
        }
        $lang = str_replace('_', '-', $lang);

        $js = '';
        if (! empty($options['require_files'])) {
            \Nexus\Nexus::css('vendor/jquery-datetimepicker/jquery.datetimepicker.min.css', 'footer', true);
            \Nexus\Nexus::js('vendor/jquery-datetimepicker/jquery.datetimepicker.full.min.js', 'footer', true);
            $js = "jQuery.datetimepicker.setLocale('{$lang}');";
        }

        $id = "datetime-picker-$name";
        $input = sprintf(
            '%s<input type="text" id="%s" name="%s" value="%s" autocomplete="off" style="%s">',
            $label,
            $id,
            $name,
            $value,
            $options['style'] ?? ''
        );

        $format = $options['format'] ?? 'Y-m-d H:i';
        $js .= "jQuery(\"#{$id}\").datetimepicker({ format: '{$format}' })";
        \Nexus\Nexus::js($js, 'footer', false);

        return $input;
    }
}
