#!/usr/bin/env python3
"""Generate tests/phpstan/globals.stub.php from legacy global variables."""

import os
import re
from pathlib import Path

BASE = Path(__file__).resolve().parent.parent

# Variables that are known objects / strings / arrays.
EXPLICIT_TYPES = {
    'Cache': 'class_cache_redis',
    'CURUSER': 'array<string, mixed>',
    'BASEURL': 'string',
    'rootpath': 'string',
    'CURLANGDIR': 'string',
    'deflang': 'string',
    'defcss': 'string',
    'iv': 'string',
    'headers': 'array<int, string>',
    'httpdirectory_attachment': 'string',
    'savedirectory_attachment': 'string',
    'torrent_dir': 'string',
}

def parse_global_vars(text: str) -> set[str]:
    """Extract variable names from `global $a, $b;` statements."""
    out = set()
    for m in re.finditer(r'global\s+([^;]+);', text):
        out.update(re.findall(r'\$([A-Za-z_][A-Za-z0-9_]*)', m.group(1)))
    return out

def parse_lang_vars(text: str) -> set[str]:
    """Extract language variable names like `$lang_index = ...`."""
    return set(re.findall(r'^\$lang_([A-Za-z0-9_]+)\s*=', text, re.MULTILINE))

def parse_configurations(text: str) -> set[str]:
    """Parse the `$CONFIGURATIONS` list from include/config.php."""
    m = re.search(r"\$CONFIGURATIONS\s*=\s*array\s*\(\s*(.+?)\s*\)", text, re.DOTALL)
    if not m:
        return set()
    inner = m.group(1)
    return set(re.findall(r"'([A-Z_]+)'", inner))

def infer_type(name: str) -> str:
    if name in EXPLICIT_TYPES:
        return EXPLICIT_TYPES[name]
    if name.startswith('lang_'):
        return 'array<string, mixed>'
    # Config sections loaded into arrays.
    if name.isupper():
        return 'array<string, mixed>'
    return 'mixed'

def main() -> None:
    include_dir = BASE / 'include'
    public_dir = BASE / 'public'
    lang_dir = BASE / 'lang' / 'en'

    vars = set()
    # Read global declarations from include/ and public/
    for root, _, files in os.walk(include_dir):
        for f in files:
            if f.endswith('.php'):
                text = (Path(root) / f).read_text(errors='ignore')
                vars.update(parse_global_vars(text))
    for root, _, files in os.walk(public_dir):
        for f in files:
            if f.endswith('.php'):
                text = (Path(root) / f).read_text(errors='ignore')
                vars.update(parse_global_vars(text))

    # Language variables from lang/en/*.php
    if lang_dir.exists():
        for f in lang_dir.glob('lang_*.php'):
            text = f.read_text(errors='ignore')
            for v in parse_lang_vars(text):
                vars.add(f'lang_{v}')

    # Config sections loaded dynamically into $GLOBALS
    config_file = include_dir / 'config.php'
    if config_file.exists():
        vars.update(parse_configurations(config_file.read_text(errors='ignore')))

    # Always include these well-known globals even if not found by the heuristics
    vars.update(['Cache', 'CURUSER', 'BASEURL', 'rootpath', 'CURLANGDIR', 'deflang', 'defcss', 'iv', 'lang_functions'])

    lines = ['<?php', '/**', ' * Bootstrap stub declaring legacy globals for PHPStan.']
    lines.append(' *')
    lines.append(' * This file is intentionally a no-op at runtime; it only helps PHPStan')
    lines.append(' * understand variables populated by bittorrent.php, language files and DB config.')
    lines.append(' */')
    lines.append('')

    for v in sorted(vars):
        t = infer_type(v)
        if t == 'mixed':
            lines.append(f"/** @var mixed ${v} */")
            lines.append(f"${v} = null;")
        elif t == 'array<string, mixed>':
            lines.append(f"/** @var array<string, mixed> ${v} */")
            lines.append(f"${v} = [];")
        elif t == 'array<int, string>':
            lines.append(f"/** @var array<int, string> ${v} */")
            lines.append(f"${v} = [];")
        elif t == 'class_cache_redis':
            lines.append(f"/** @var class_cache_redis ${v} */")
            lines.append(f"${v} = null;")
        else:
            lines.append(f"/** @var {t} ${v} */")
            if t == 'string':
                lines.append(f"${v} = '';")
            else:
                lines.append(f"${v} = null;")
        lines.append('')

    out = BASE / 'tests' / 'phpstan' / 'globals.stub.php'
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text('\n'.join(lines))
    print(f'Wrote {out} with {len(vars)} globals')

if __name__ == '__main__':
    main()
