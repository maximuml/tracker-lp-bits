<?php

declare(strict_types=1);

/*
 * BBCode → HTML substitution tables for App\Support\Comment::format().
 * The values are the markup fragments the formatter emits for each bbcode;
 * they are transform-engine data, not assembled view output.
 */

return [

    // Literal tag substitutions (str_replace map). `siteurl`, `site` and
    // the double-space padding are resolved per-request in the formatter.
    'literal_map' => [
        '[*]' => '&#x2022; ',
        '[b]' => '<b>',
        '[/b]' => '</b>',
        '[i]' => '<i>',
        '[/i]' => '</i>',
        '[u]' => '<u>',
        '[/u]' => '</u>',
        '[s]' => '<s>',
        '[/s]' => '</s>',
        '[pre]' => '<pre>',
        '[/pre]' => '</pre>',
        '[/color]' => '</span>',
        '[/font]' => '</span>',
        '[/size]' => '</span>',
        '[hr]' => '<hr>',
        '  ' => ' &nbsp;',
    ],

    // Ordered regex substitutions: pattern => replacement (applied via
    // preg_replace; `\1`/`$1` backrefs refer to the pattern's groups).
    'span_map' => [
        "/\[font=([^\[\(&\\\\;]+?)\]/is" => '<span face="\\1">',
        '/\[color=([#0-9a-z]{1,15})\]/is' => '<span>',
        '/\[color=([a-z]+)\]/is' => '<span>',
        '/\[size=([1-7])\]/is' => '<span>',
    ],

];
