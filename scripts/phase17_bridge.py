#!/usr/bin/env python3
"""Bulk-bridge remaining Blade partials that still do DB/side effects.

Moves the body of `resources/views/<dir>/_<name>.blade.php` into
`resources/legacy/<name>.php` (or <dir>_<name>.php to avoid collisions),
applies legacy-compatible shim replacements, and replaces the view with a
single echo of `LegacyViewRepository::render()`.

Usage:
    python3 scripts/phase17_bridge.py \
        messages/_messages \
        sendmessage/_sendmessage \
        takemessage/_takemessage \
        ...
"""
import os
import re
import sys

VIEWS_DIR = 'resources/views'
LEGACY_DIR = 'resources/legacy'

DB_RE = re.compile(
    r'(?:NexusDB::(?:table|query|select|insert|update|delete|getOne|get_all|cache_get|cache_set)|'
    r'->\s*(?:insert|update|delete|save)\s*\(|sql_query\s*\(|\$Cache\s*->|'
    r'Settings::saveBatch|Message::query\s*\(|User::query\s*\(|UserModifyLog::|UserBanLog::|StaffMessage::add)',
    re.IGNORECASE,
)


def path_for(view_path: str) -> str:
    """resources/views/messages/_messages.blade.php -> resources/views/messages/_messages.blade.php"""
    return os.path.join(VIEWS_DIR, view_path + '.blade.php')


def legacy_name(view_path: str) -> str:
    """messages/_messages -> messages; my/_bonus -> my_bonus"""
    if '/' in view_path:
        dirname, filename = view_path.split('/', 1)
        filename = filename.lstrip('_').replace('.blade.php', '')
        if filename == dirname or filename == 'index':
            return dirname
        return f"{dirname}_{filename}"
    return view_path.lstrip('_').replace('.blade.php', '')


def detect_top_level_vars(text: str) -> list[str]:
    """Find variables that may be globals."""
    names: list[str] = []
    for m in re.finditer(r'\$([A-Za-z_][A-Za-z0-9_]*)', text):
        n = m.group(1)
        if n not in names and not n.startswith('_'):
            names.append(n)
    return names


def transform_body(text: str, name: str) -> str:
    # Remove a leading @php and trailing @endphp wrapper if present
    text = text.strip()
    text = re.sub(r'^@php\s*\n', '<?php\n', text, count=1, flags=re.IGNORECASE)
    text = re.sub(r'\n@endphp\s*$', '\n', text, count=1, flags=re.IGNORECASE)

    # Ensure file starts with <?php
    if not text.lstrip().startswith('<?php'):
        text = '<?php\n' + text

    # Remove error_reporting(...) calls
    text = re.sub(r'^\s*error_reporting\s*\([^)]*\)\s*;\s*$', '', text, flags=re.MULTILINE)

    # Detect used globals
    used_vars = detect_top_level_vars(text)

    prelude_lines: list[str] = []
    prelude_lines.append('// Auto-generated legacy bridge shims')

    if 'CURUSER' in used_vars or re.search(r'\bCURUSER\b', text):
        prelude_lines.append("if (!isset($CURUSER)) $CURUSER = (array) (\\App\\Support\\SupportContext::getUser() ?? []);")
    if 'Cache' in used_vars or re.search(r'\bCache\b', text):
        prelude_lines.append("if (!isset($Cache)) $Cache = \\App\\Support\\SupportContext::getCache();")
    if 'BASEURL' in used_vars or re.search(r'\bBASEURL\b', text):
        prelude_lines.append("if (!isset($BASEURL)) $BASEURL = \\App\\Support\\SupportContext::getGlobal('BASEURL', '');")
    # Language arrays
    lang_names = sorted(set(re.findall(r'\$lang_([A-Za-z_][A-Za-z0-9_]*)', text)))
    for ln in lang_names:
        prelude_lines.append(f"if (!isset($lang_{ln})) $lang_{ln} = (array) (\\App\\Support\\SupportContext::getGlobal('lang_{ln}') ?? []);")

    # Insert prelude after opening <?php
    body = text
    if prelude_lines:
        m = re.match(r'^(<\?php)\s*\n?', body)
        if m:
            body = body[:m.end()] + '\n'.join(prelude_lines) + '\n' + body[m.end():]
        else:
            body = '<?php\n' + '\n'.join(prelude_lines) + '\n' + body

    # Replace exit/die with return so legacyPageRaw can capture output
    body = re.sub(r'\bexit\s*\([^)]*\)\s*;', 'return;', body)
    body = re.sub(r'\bexit\s*\(\s*\)\s*;', 'return;', body)
    body = re.sub(r'\bexit\s*;', 'return;', body)
    body = re.sub(r'\bdie\s*\([^)]*\)\s*;', 'return;', body)
    body = re.sub(r'\bdie\s*;', 'return;', body)

    return body


def main(view_paths: list[str]) -> None:
    os.makedirs(LEGACY_DIR, exist_ok=True)
    for vp in view_paths:
        src = path_for(vp)
        if not os.path.exists(src):
            print(f'WARN: source not found: {src}', file=sys.stderr)
            continue
        name = legacy_name(vp)
        dst = os.path.join(LEGACY_DIR, name + '.php')
        if os.path.exists(dst):
            print(f'WARN: legacy file already exists: {dst}', file=sys.stderr)
            # continue? overwrite for idempotency
        text = open(src, encoding='utf-8', errors='ignore').read()
        if not DB_RE.search(text):
            print(f'INFO: no DB/side effects in {vp}, skipping', file=sys.stderr)
            continue
        transformed = transform_body(text, name)
        with open(dst, 'w', encoding='utf-8') as f:
            f.write(transformed)
        with open(src, 'w', encoding='utf-8') as f:
            f.write(f"<?php\necho \\App\\Repositories\\LegacyViewRepository::render('{name}', get_defined_vars());\n")
        print(f'BRIDGED {vp} -> {dst}')


if __name__ == '__main__':
    if len(sys.argv) < 2:
        print('Usage: phase17_bridge.py <view-path> [view-path...]')
        sys.exit(1)
    main(sys.argv[1:])
