#!/usr/bin/env python3
"""Validate portable Design Core / project / standalone-theme boundaries."""
from __future__ import annotations

import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
WORKSPACE = ROOT / "workspace.json"
errors: list[str] = []


def fail(message: str) -> None:
    errors.append(message)


def rel(path: Path) -> str:
    try:
        return path.relative_to(ROOT).as_posix()
    except ValueError:
        return str(path)


if not WORKSPACE.is_file():
    fail("workspace.json is missing")
    workspace = {}
else:
    try:
        workspace = json.loads(WORKSPACE.read_text())
    except Exception as exc:
        fail(f"workspace.json is invalid JSON: {exc}")
        workspace = {}

phase = workspace.get("migrationPhase") if isinstance(workspace, dict) else None
if not isinstance(phase, int) or phase < 1:
    fail("workspace.json migrationPhase must be an integer >= 1")

packages = workspace.get("packages", {}) if isinstance(workspace, dict) else {}
projects = workspace.get("projects", {}) if isinstance(workspace, dict) else {}
rules = workspace.get("rules", {}) if isinstance(workspace, dict) else {}

required_rules = {
    "themeRuntimeStandalone": True,
    "themeMayImportPackagesAtRuntime": False,
    "themeMayImportProjectsAtRuntime": False,
    "canonicalBlockIdsAreStable": True,
}
for key, expected in required_rules.items():
    if rules.get(key) is not expected:
        fail(f"workspace rule {key} must be {expected!r}")

for key in ("designCore", "wordpressAdapter"):
    value = packages.get(key)
    if not isinstance(value, str) or not value:
        fail(f"workspace packages.{key} is missing")
        continue
    path = ROOT / value
    if not path.is_dir():
        fail(f"{value}: package directory is missing")
    if not (path / "package.json").is_file():
        fail(f"{value}/package.json is missing")

if not isinstance(projects, dict) or not projects:
    fail("workspace must define at least one project")
else:
    for project_id, config in projects.items():
        if not isinstance(config, dict):
            fail(f"project {project_id}: config must be an object")
            continue
        source = config.get("source")
        output = config.get("themeOutput")
        if not isinstance(source, str) or not (ROOT / source).is_dir():
            fail(f"project {project_id}: source directory is missing: {source!r}")
        if not isinstance(output, str) or not (ROOT / output).is_dir():
            fail(f"project {project_id}: theme output directory is missing: {output!r}")
        project_json = ROOT / str(source) / "project.json"
        if not project_json.is_file():
            fail(f"project {project_id}: {rel(project_json)} is missing")

# During phase 1 the existing theme remains the runnable/visual baseline.
if phase == 1:
    for legacy in (ROOT / "design", ROOT / "inc", ROOT / "src"):
        if not legacy.is_dir():
            fail(f"phase 1 baseline directory unexpectedly missing: {rel(legacy)}")

# The portable core must not gain WordPress runtime code.
core_value = packages.get("designCore")
core_dir = ROOT / core_value if isinstance(core_value, str) else None
wp_tokens = re.compile(r"\b(?:ABSPATH|WP_Block|register_block_type|wp_enqueue_|wp_insert_post|register_rest_route)\b")
if core_dir and core_dir.is_dir():
    for path in core_dir.rglob("*"):
        if not path.is_file() or path.suffix.lower() not in {".php", ".js", ".mjs", ".ts", ".tsx"}:
            continue
        text = path.read_text(errors="ignore")
        if wp_tokens.search(text):
            fail(f"{rel(path)}: portable Design Core contains WordPress runtime API usage")

# Generated theme runtime must never reach back into workspace source packages/projects.
for project_id, config in projects.items() if isinstance(projects, dict) else []:
    if not isinstance(config, dict):
        continue
    output = config.get("themeOutput")
    theme_dir = ROOT / output if isinstance(output, str) else None
    if not theme_dir or not theme_dir.is_dir():
        continue
    escape = re.compile(r"(?:\.\./)+(?:packages|projects)/|(?:^|[\"'(/])packages/|(?:^|[\"'(/])projects/")
    for path in theme_dir.rglob("*"):
        if not path.is_file() or path.suffix.lower() not in {".php", ".js", ".mjs", ".css"}:
            continue
        text = path.read_text(errors="ignore")
        if escape.search(text):
            fail(f"{rel(path)}: standalone theme runtime references workspace source")

if errors:
    print("Architecture validation failed:")
    for error in errors:
        print(f"- {error}")
    sys.exit(1)

print(f"Architecture validation passed (migration phase {phase}).")
