<?php

declare(strict_types=1);

namespace App\Support\Html;

/**
 * Escaping utilities extracted from the legacy Html facade.
 *
 * Pure functions — no DI, no DB, no config, no global state.
 */
final class Escape
{
    /**
     * Escape a string for safe output inside an HTML attribute value
     * (e.g. `value="..."`). Uses ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE
     * so both single and double quotes are encoded and invalid UTF-8
     * is replaced rather than passed through.
     */
    public static function escapeAttr(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5 | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Remove disallowed direct children (e.g. <br>) from <ul>/<ol> elements
     * to satisfy WCAG 2.1 AA "list" rule.
     */
    public static function cleanListChildren(string $html): string
    {
        $dom = new \DOMDocument;
        $previous = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        foreach (['ul', 'ol'] as $tag) {
            foreach (iterator_to_array($dom->getElementsByTagName($tag)) as $list) {
                foreach (iterator_to_array($list->childNodes) as $child) {
                    if ($child->nodeName === 'br') {
                        $list->removeChild($child);
                    }
                }
            }
        }

        $div = $dom->getElementsByTagName('div')->item(0);
        if ($div === null) {
            return $html;
        }

        $result = '';
        foreach ($div->childNodes as $child) {
            $result .= $dom->saveHTML($child);
        }

        return $result;
    }
}
