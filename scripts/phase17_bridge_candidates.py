#!/usr/bin/env python3
import re
import os
from collections import defaultdict

VIEWS_DIR = 'resources/views'

# Patterns that mean the file still does DB or side effects that must leave the view
DB_PATTERNS = [
    re.compile(r'NexusDB::(?:table|query|select|insert|update|delete|getOne|get_all|cache_get|cache_set)'),
    re.compile(r'->\s*(?:insert|update|delete|save)\s*\('),
    re.compile(r'sql_query\s*\('),
    re.compile(r'\$Cache\s*->'),
    re.compile(r'Settings::saveBatch'),
    re.compile(r'Message::query\s*\('),
    re.compile(r'User::query\s*\('),
    re.compile(r'UserModifyLog::'),
    re.compile(r'UserBanLog::'),
    re.compile(r'StaffMessage::add'),
]

# Blade directives / syntax. We cannot safely include these in raw PHP because
# they are compiled by Laravel; if we keep the file .blade.php and only echo,
# the directives are compiled before our PHP runs.
BLADE_PATTERNS = [
    re.compile(r'^\s*@\w+', re.MULTILINE),
    re.compile(r'\{\{[^\n]*\}\}'),
    re.compile(r'\{!![^\n]*!!\}'),
]

def has_db(text):
    return any(p.search(text) for p in DB_PATTERNS)

def has_blade(text):
    return any(p.search(text) for p in BLADE_PATTERNS)

candidates = []
for root, dirs, files in os.walk(VIEWS_DIR):
    for f in files:
        if not f.endswith('.blade.php'):
            continue
        path = os.path.join(root, f)
        with open(path, 'r', encoding='utf-8', errors='ignore') as fh:
            text = fh.read()
        if not has_db(text):
            continue
        rel = os.path.relpath(path, VIEWS_DIR)
        lines = text.count('\n')
        candidates.append({
            'rel': rel,
            'path': path,
            'lines': lines,
            'blade': has_blade(text),
            'db_matches': sum(1 for p in DB_PATTERNS for _ in p.finditer(text)),
            'db_match_types': sorted({p.pattern for p in DB_PATTERNS if p.search(text)}),
        })

candidates.sort(key=lambda x: x['db_matches'], reverse=True)

print(f"{'MATCH':>5} {'LINES':>6} {'BLADE':>5} {'FILE'}")
for c in candidates:
    print(f"{c['db_matches']:>5} {c['lines']:>6} {'YES' if c['blade'] else 'no':>5} {c['rel']}")

print(f"\nTotal: {len(candidates)}")
