#!/usr/bin/env python3
r"""Add PHPDoc blocks to global functions in legacy include files.

Uses the mapped \\App\\Support or \\Nexus\\* static method to infer parameter and
return types. Falls back to mixed when no mapping is found.
"""

import json
import os
import re
import subprocess
import sys
from pathlib import Path

RE_STATIC_CALL = re.compile(
    r'(?:return\s+)?(?:echo\s+)?((?:\\?[A-Za-z_][A-Za-z0-9_]*\\)+[A-Za-z_][A-Za-z0-9_]*)::([A-Za-z_][A-Za-z0-9_]*)\s*\(',
    re.DOTALL,
)


def get_method_signature(class_method: str) -> dict | None:
    try:
        out = subprocess.check_output(
            ['php', 'scripts/get_method_signature.php', class_method],
            text=True,
            timeout=5,
        )
    except subprocess.CalledProcessError as e:
        print(f'Warning: failed to reflect {class_method}: {e}', file=sys.stderr)
        return None
    try:
        data = json.loads(out)
    except json.JSONDecodeError:
        return None
    if not data or 'error' in data:
        return None
    return data


def expand_type(t: str) -> str:
    """Add generic parameters to bare collection/iterable types for PHPStan level 6."""
    if not t:
        return 'mixed'

    parts = [p.strip() for p in t.split('|')]
    expanded = []
    for part in parts:
        if part == '?':
            continue
        if '<' not in part:
            if part in ('array', 'iterable'):
                part = f'{part}<array-key, mixed>'
            elif part == 'Collection':
                part = '\\Illuminate\\Support\\Collection<array-key, mixed>'
            elif part.endswith('Collection'):
                part = f'{part}<array-key, mixed>'
        expanded.append(part)
    return '|'.join(expanded) if expanded else 'mixed'


def build_phpdoc(params: list[dict], return_type: str, is_void: bool) -> str:
    lines = ['/**']
    for p in params:
        t = p.get('type', 'mixed') or 'mixed'
        # PHPStan prefers `Type|null` over `?Type` in PHPDoc, but both work.
        # Normalize `?` prefix to union for consistency.
        if t.startswith('?') and '|' not in t:
            t = t[1:] + '|null'
        t = expand_type(t)
        lines.append(f' * @param {t} ${p["name"]}')
    if is_void:
        lines.append(' * @return void')
    else:
        rt = return_type or 'mixed'
        if rt.startswith('?') and '|' not in rt:
            rt = rt[1:] + '|null'
        rt = expand_type(rt)
        lines.append(f' * @return {rt}')
    lines.append(' */')
    return '\n'.join(lines)


def strip_global_statements(body: str) -> str:
    """Remove leading `global $a, $b;` statements from the function body."""
    while True:
        m = re.match(r'^global\s+\$[A-Za-z_][A-Za-z0-9_]*(?:\s*,\s*\$[A-Za-z_][A-Za-z0-9_]*)*;\s*', body)
        if not m:
            break
        body = body[m.end():]
    return body


def extract_static_call_args(body: str) -> list[str] | None:
    """Extract the argument list from a static call like Class::method(args)."""
    m = RE_STATIC_CALL.search(body)
    if not m:
        return None
    # The regex ends at the opening parenthesis, so m.end() is one character past it.
    end = m.end()
    if body[end - 1] != '(':
        return None
    depth = 1
    args = ''
    i = end
    while i < len(body) and depth > 0:
        c = body[i]
        if c == '(':
            depth += 1
        elif c == ')':
            depth -= 1
            if depth == 0:
                break
        args += c
        i += 1
    if depth != 0:
        return None
    # Split by top-level commas
    result = []
    current = ''
    depth = 0
    in_str = False
    str_char = ''
    for c in args:
        if in_str:
            current += c
            if c == str_char:
                in_str = False
            continue
        if c in ('"', "'"):
            in_str = True
            str_char = c
            current += c
            continue
        if c in '([{':
            depth += 1
        elif c in ')]}':
            depth -= 1
        if c == ',' and depth == 0:
            result.append(current.strip())
            current = ''
        else:
            current += c
    if current.strip():
        result.append(current.strip())
    return result


def phpdoc_for_function(func: dict) -> str | None:
    body = strip_global_statements(func.get('body_one_line', ''))
    name = func['name']
    params = func.get('params') or []

    is_void = body.startswith('echo')
    has_return = body.startswith('return')

    m = RE_STATIC_CALL.search(body)
    if not m:
        return build_phpdoc(
            [{'name': p['name'], 'type': 'mixed'} for p in params],
            'mixed',
            is_void and not has_return,
        )

    class_name = m.group(1).replace('\\\\', '\\')
    method_name = m.group(2)
    sig = get_method_signature(f'{class_name}::{method_name}')

    if not sig:
        return build_phpdoc(
            [{'name': p['name'], 'type': 'mixed'} for p in params],
            'mixed',
            is_void and not has_return,
        )

    mapped_params = sig.get('params', [])
    mapped_return = sig.get('return', 'mixed')

    call_args = extract_static_call_args(body)

    # Map wrapper param names to types inferred from the method call arguments.
    param_types: dict[str, str] = {}
    if call_args:
        for i, arg in enumerate(call_args):
            if i >= len(mapped_params):
                break
            # Look for an argument that references a wrapper parameter:
            #   $foo, (int)$foo, (string)$foo, $foo ?? '', etc.
            argm = re.search(r'(?:\(\s*\w+\s*\))?\s*\$([A-Za-z_][A-Za-z0-9_]*)', arg)
            if argm:
                param_types[argm.group(1)] = mapped_params[i]['type'] or 'mixed'

    typed_params = []
    for p in params:
        t = param_types.get(p['name'])
        if not t or t == 'mixed':
            # Fall back to the native parameter type from the function signature.
            native = (p.get('type') or '').strip()
            if native:
                t = native
            else:
                t = 'mixed'
        typed_params.append({'name': p['name'], 'type': t})

    if is_void:
        ret = 'void'
    elif has_return:
        ret = mapped_return
    else:
        # Method call used as statement (e.g. echo Class::method(); or Class::method();)
        ret = 'void'

    return build_phpdoc(typed_params, ret, is_void and not has_return)


def has_docblock(lines: list[str], func_line: int) -> bool:
    # Check lines immediately before the function line for a docblock.
    idx = func_line - 1
    while idx > 0:
        line = lines[idx - 1].strip()
        if line == '*/':
            return True
        if line.startswith('function '):
            return False
        if line and not line.startswith('//') and not line.startswith('#') and not line.startswith('*'):
            return False
        idx -= 1
    return False


def process_file(path: str) -> None:
    p = Path(path)
    text = p.read_text()
    original_lines = text.splitlines(keepends=True)
    lines = list(original_lines)

    extract = subprocess.run(
        ['php', 'scripts/extract_functions.php', path],
        capture_output=True,
        text=True,
    )
    if extract.returncode != 0:
        print(extract.stderr, file=sys.stderr)
        sys.exit(1)
    functions = json.loads(extract.stdout)

    offset = 0
    replaced = inserted = 0
    for func in sorted(functions, key=lambda f: f['line']):
        func_line = func['line']  # 1-based in original file
        if has_docblock(original_lines, func_line):
            continue

        phpdoc = phpdoc_for_function(func)
        if not phpdoc:
            continue

        phpdoc_block_lines = [line + '\n' for line in phpdoc.split('\n')]

        idx = func_line - 1 + offset
        # If the line immediately above the function is blank, replace it.
        if idx > 0 and lines[idx - 1].strip() == '':
            lines[idx - 1 : idx - 1 + 1] = phpdoc_block_lines
            offset += len(phpdoc_block_lines) - 1
            replaced += 1
        else:
            lines[idx:idx] = phpdoc_block_lines
            offset += len(phpdoc_block_lines)
            inserted += 1

    p.write_text(''.join(lines))
    print(f'Inserted {inserted} and replaced {replaced} PHPDoc blocks into {path}')


if __name__ == '__main__':
    for f in sys.argv[1:]:
        process_file(f)
