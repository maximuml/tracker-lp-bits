#!/usr/bin/env python3
"""W0-04 fail-closed coverage gate.

Reads line coverage from the Clover XML files produced by the
Unit+Integration and Feature runs. Fails on missing files, parse errors,
or Unit coverage below the floor. Only Unit coverage is gated — Feature
coverage is informational since those tests need full infrastructure.
"""
import sys
import xml.etree.ElementTree as ET
from pathlib import Path

UNIT_THRESHOLD = 35.0
FILES = ["coverage-unit.xml", "coverage-feature.xml"]

per_file = {}
for f in FILES:
    p = Path(f)
    if not p.exists():
        print(f"::error::Coverage file not found: {f}")
        sys.exit(1)
    try:
        root = ET.parse(f).getroot()
        # Clover <metrics>: statements, coveredstatements.
        # Prefer project-level metrics for aggregate counts. NB: an
        # Element is falsy when it has no children, so `or` cannot be
        # used here — <metrics> is a leaf and would always fall through
        # to the first file-level <metrics> element.
        metrics = root.find(".//project/metrics")
        if metrics is None:
            metrics = root.find(".//metrics")
        if metrics is None:
            print(f"::error::No <metrics> element in {f}")
            sys.exit(1)
        stmts = int(metrics.get("statements", "0"))
        covered = int(metrics.get("coveredstatements", "0"))
    except ET.ParseError as e:
        print(f"::error::Failed to parse {f}: {e}")
        sys.exit(1)
    except (TypeError, ValueError) as e:
        print(f"::error::Invalid metrics in {f}: {e}")
        sys.exit(1)
    per_file[f] = (stmts, covered)

print("## Coverage gate")
print("| Suite | Statements | Covered | % |")
print("|---|---:|---:|---:|")
for f, (s, c) in per_file.items():
    fp = (c / s * 100) if s > 0 else 0.0
    print(f"| {f} | {s} | {c} | {fp:.2f}% |")

unit_stmts, unit_covered = per_file["coverage-unit.xml"]
unit_pct = (unit_covered / unit_stmts * 100) if unit_stmts > 0 else 0.0
print(f"\nUnit threshold: {UNIT_THRESHOLD:.0f}%  Unit coverage: {unit_pct:.2f}%")
if unit_pct < UNIT_THRESHOLD:
    print(f"::error::Unit coverage {unit_pct:.2f}% is below threshold {UNIT_THRESHOLD:.0f}%")
    sys.exit(1)
print("Coverage gate passed.")
