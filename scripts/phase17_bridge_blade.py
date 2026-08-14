#!/usr/bin/env python3
"""Bridge full-Blade index views into resources/legacy/*.php partials.

The view is replaced by a one-liner that renders the legacy partial.
The legacy file contains the original Blade content converted to raw PHP,
including stdhead/mainFrame/stdfoot for pages that used layouts.legacy.
"""
import re
import sys
import pathlib
import textwrap

ROOT = pathlib.Path(__file__).resolve().parent.parent
LEGACY_DIR = ROOT / 'resources' / 'legacy'
VIEWS_DIR = ROOT / 'resources' / 'views'

PRELUDE_TEMPLATE = """<?php
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING & ~E_DEPRECATED);

// Auto-generated legacy bridge shims
if (!isset($CURUSER)) $CURUSER = (array) (\\App\\Support\\SupportContext::getUser() ?? []);
if (!isset($Cache)) $Cache = \\App\\Support\\SupportContext::getCache();
if (!isset($BASEURL)) $BASEURL = \\App\\Support\\SupportContext::getGlobal('BASEURL', '');
"""

DIRECTIVE_REPLACEMENTS = {
    '@if': '<?php if ({expr}): ?>',
    '@elseif': '<?php elseif ({expr}): ?>',
    '@foreach': '<?php foreach ({expr}): ?>',
}


def balanced_paren_index(text: str, start: int) -> int:
    """Return index after the closing paren that matches the paren at start-1."""
    depth = 1
    i = start
    while i < len(text) and depth > 0:
        if text[i] == '(':
            depth += 1
        elif text[i] == ')':
            depth -= 1
        i += 1
    return i


def convert_blade(text: str) -> str:
    # Replace directives with balanced parentheses
    for directive, replacement in DIRECTIVE_REPLACEMENTS.items():
        while True:
            m = re.search(re.escape(directive) + r'\s*\(', text)
            if not m:
                break
            end = balanced_paren_index(text, m.end())
            expr = text[m.end():end - 1]
            text = text[:m.start()] + replacement.format(expr=expr) + text[end:]

    # Other directives (no parentheses)
    text = re.sub(r'@else\b', '<?php else: ?>', text)
    text = re.sub(r'@endif\b', '<?php endif; ?>', text)
    text = re.sub(r'@endforeach\b', '<?php endforeach; ?>', text)
    text = re.sub(r'@php\b', '<?php', text)
    text = re.sub(r'@endphp\b', '?>', text)

    # Echo tags
    text = re.sub(r'\{\{(.+?)\}\}', r"<?php echo htmlspecialchars((string) (\1), ENT_QUOTES, 'UTF-8'); ?>", text, flags=re.DOTALL)
    text = re.sub(r'\{!!(.+?)!!\}', r'<?php echo \1; ?>', text, flags=re.DOTALL)
    return text


def process(name: str) -> None:
    view_file = VIEWS_DIR / name / 'index.blade.php'
    if not view_file.exists():
        print(f'WARN: view not found: {view_file}')
        return

    src = view_file.read_text(encoding='utf-8', errors='ignore')

    has_layout = bool(re.search(r"@extends\(['\"]layouts\.", src))
    title_match = re.search(r"@section\(['\"]title['\"],\s*(.+?)\)", src, re.DOTALL)
    title_expr = title_match.group(1).strip() if title_match else "''"

    content = src
    if has_layout:
        m = re.search(r"@section\(['\"]content['\"]\)\s*(.*)\s*@endsection\s*$", src, re.DOTALL)
        if m:
            content = m.group(1)
        else:
            has_layout = False

    # Strip layout/title/section directives
    content = re.sub(r"@extends\(['\"][^'\"]+['\"]\)\s*\n?", "", content)
    content = re.sub(r"@section\(['\"]title['\"],\s*.+?\)\s*\n?", "", content)
    content = re.sub(r"@section\(['\"]content['\"]\)\s*\n?", "", content)
    content = re.sub(r"@endsection\s*$", "", content)

    php_content = convert_blade(content)

    if has_layout:
        php_content = textwrap.dedent(f"""<?php
        $title = {title_expr};
        \\App\\Support\\Html::stdhead($title);
        \\App\\Support\\Frame::mainFrameOpen();
        ?>
        {php_content}
        <?php
        \\App\\Support\\Frame::mainFrameClose();
        \\App\\Support\\Html::stdfoot();
        ?>
        """)

    lang_var = f'lang_{name.replace("-", "_")}'
    prelude = PRELUDE_TEMPLATE + f"if (!isset(${lang_var})) ${lang_var} = (array) (\\App\\Support\\SupportContext::getGlobal('{lang_var}') ?? []);\n?>\n"
    legacy_body = prelude + php_content

    legacy_path = LEGACY_DIR / f'{name}.php'
    legacy_path.write_text(legacy_body, encoding='utf-8')

    view_file.write_text(
        "<?php echo \\App\\Repositories\\LegacyViewRepository::render('" + name + "', get_defined_vars()); ?>\n",
        encoding='utf-8'
    )
    print(f'BRIDGED {name}/index -> {legacy_path}')


if __name__ == '__main__':
    for arg in sys.argv[1:]:
        process(arg)
