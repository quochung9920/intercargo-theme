#!/usr/bin/env python3
"""Validate workspace boundaries and generated-theme independence."""
from __future__ import annotations

import hashlib
import json
import re
import sys
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
ERRORS: list[str] = []


def fail(message: str) -> None:
    ERRORS.append(message)


def rel(path: Path) -> str:
    try:
        return path.relative_to(ROOT).as_posix()
    except ValueError:
        return str(path)


def read_json(path: Path) -> dict:
    try:
        value = json.loads(path.read_text(encoding="utf-8"))
    except Exception as exc:
        fail(f"{rel(path)}: invalid JSON: {exc}")
        return {}
    if not isinstance(value, dict):
        fail(f"{rel(path)}: expected a JSON object")
        return {}
    return value


def digest(path: Path) -> str:
    value = hashlib.sha256()
    with path.open("rb") as stream:
        for chunk in iter(lambda: stream.read(1024 * 1024), b""):
            value.update(chunk)
    return value.hexdigest()


def tree_map(root: Path) -> dict[str, str]:
    if not root.is_dir():
        return {}
    return {
        path.relative_to(root).as_posix(): digest(path)
        for path in root.rglob("*")
        if path.is_file()
    }


def require_same_tree(source: Path, output: Path, label: str) -> None:
    if not source.is_dir():
        fail(f"{label}: source directory missing: {rel(source)}")
        return
    if not output.is_dir():
        fail(f"{label}: generated directory missing: {rel(output)}")
        return
    expected = tree_map(source)
    actual = tree_map(output)
    if expected == actual:
        return
    missing = sorted(set(expected) - set(actual))[:8]
    extra = sorted(set(actual) - set(expected))[:8]
    changed = sorted(
        name for name in set(expected) & set(actual) if expected[name] != actual[name]
    )[:8]
    parts: list[str] = []
    if missing:
        parts.append("missing=" + ", ".join(missing))
    if extra:
        parts.append("extra=" + ", ".join(extra))
    if changed:
        parts.append("changed=" + ", ".join(changed))
    fail(f"{label}: generated output is stale ({'; '.join(parts)})")


workspace_path = ROOT / "workspace.json"
workspace = read_json(workspace_path) if workspace_path.is_file() else {}
if not workspace:
    fail("workspace.json is missing or invalid")

phase = workspace.get("migrationPhase")
if not isinstance(phase, int) or phase < 4:
    fail("workspace migrationPhase must be >= 4 after source separation")

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

packages = workspace.get("packages", {}) if isinstance(workspace, dict) else {}
projects = workspace.get("projects", {}) if isinstance(workspace, dict) else {}
core_root = ROOT / str(packages.get("designCore", ""))
adapter_root = ROOT / str(packages.get("wordpressAdapter", ""))

if not core_root.is_dir():
    fail("portable Design Core directory is missing")
if not (adapter_root / "runtime").is_dir():
    fail("WordPress adapter runtime is missing")

# Core contracts are portable. Documentation can describe boundaries, but executable
# files and schemas must not acquire WordPress APIs or project-specific symbols.
forbidden_core = re.compile(
    r"\b(?:ABSPATH|WP_Block|register_block_type|wp_enqueue_|wp_insert_post|register_rest_route|intercargo)\b",
    re.IGNORECASE,
)
if core_root.is_dir():
    for path in core_root.rglob("*"):
        if not path.is_file() or path.suffix.lower() not in {
            ".php", ".js", ".mjs", ".ts", ".tsx", ".py", ".json"
        }:
            continue
        if forbidden_core.search(path.read_text(encoding="utf-8", errors="ignore")):
            fail(f"{rel(path)}: Design Core contains project/platform-specific language")

# The repository root is a workspace, never a second WordPress theme copy.
legacy_root_paths = [
    "design", "inc", "src", "assets", "dist", "patterns", "tests",
    "archive.php", "footer.php", "front-page.php", "functions.php", "header.php",
    "index.php", "page.php", "single.php", "style.css", "theme.json",
    "template-section-builder.php", "screenshot.png", "vite.config.js",
]
for value in legacy_root_paths:
    if (ROOT / value).exists():
        fail(f"legacy theme path remains at workspace root: {value}")

if not isinstance(projects, dict) or not projects:
    fail("workspace must define at least one project")
else:
    core_package = read_json(core_root / "package.json") if (core_root / "package.json").is_file() else {}
    adapter_package = read_json(adapter_root / "package.json") if (adapter_root / "package.json").is_file() else {}

    for project_id, entry in projects.items():
        if not isinstance(entry, dict):
            fail(f"project {project_id}: invalid workspace entry")
            continue

        source_root = ROOT / str(entry.get("source", ""))
        output_root = ROOT / str(entry.get("themeOutput", ""))
        project_file = source_root / "project.json"
        project = read_json(project_file) if project_file.is_file() else {}
        if not project:
            fail(f"project {project_id}: project.json is missing or invalid")
            continue

        paths = project.get("paths", {})
        if not isinstance(paths, dict):
            fail(f"project {project_id}: paths must be an object")
            continue

        for key in ("design", "assets", "theme", "src", "qa"):
            value = paths.get(key)
            if not isinstance(value, str) or not (source_root / value).is_dir():
                fail(f"project {project_id}: missing source path {key}")

        required_theme = [
            "style.css", "functions.php", "index.php", "page.php", "header.php",
            "footer.php", "theme.json", "design", "inc", "assets", "src", "dist",
            "BUILD-MANIFEST.json",
        ]
        for value in required_theme:
            if not (output_root / value).exists():
                fail(f"project {project_id}: generated theme missing {value}")

        if output_root.is_dir():
            # Nothing in the standalone theme may reach back into workspace source.
            escape = re.compile(
                r"(?:\.\./)+(?:packages|projects)/|(?:^|[\"'(/])(?:packages|projects)/"
            )
            for path in output_root.rglob("*"):
                if not path.is_file() or path.suffix.lower() not in {
                    ".php", ".js", ".mjs", ".css", ".json"
                }:
                    continue
                if escape.search(path.read_text(encoding="utf-8", errors="ignore")):
                    fail(f"{rel(path)}: standalone theme references workspace source")

            for forbidden in ("vite.config.js", "package.json", "package-lock.json"):
                if (output_root / forbidden).exists():
                    fail(f"project {project_id}: generated runtime contains build-only path {forbidden}")

        shell_root = source_root / str(paths.get("theme", ""))
        qa_root = source_root / str(paths.get("qa", ""))
        require_same_tree(source_root / str(paths.get("design", "")), output_root / "design", f"project {project_id} design")
        require_same_tree(source_root / str(paths.get("assets", "")), output_root / "assets", f"project {project_id} assets")
        require_same_tree(source_root / str(paths.get("src", "")), output_root / "src", f"project {project_id} source bridges")
        require_same_tree(adapter_root / "runtime", output_root / "inc", f"project {project_id} adapter runtime")
        require_same_tree(shell_root / "patterns", output_root / "patterns", f"project {project_id} patterns")
        require_same_tree(qa_root / "tests", output_root / "tests", f"project {project_id} tests")
        require_same_tree(qa_root / "tools", output_root / "tools", f"project {project_id} QA tools")

        if shell_root.is_dir():
            for shell_file in shell_root.rglob("*"):
                if not shell_file.is_file():
                    continue
                relative = shell_file.relative_to(shell_root)
                if relative.parts and relative.parts[0] == "patterns":
                    continue
                destination = output_root / relative
                if not destination.is_file() or digest(shell_file) != digest(destination):
                    fail(f"project {project_id}: generated shell is stale: {relative.as_posix()}")

        # Canonical Gutenberg identities are independent from filesystem moves.
        source_names: set[str] = set()
        output_names: set[str] = set()
        design_root = source_root / str(paths.get("design", ""))
        for block_json in design_root.rglob("block.json") if design_root.is_dir() else []:
            data = read_json(block_json)
            if isinstance(data.get("name"), str):
                source_names.add(data["name"])
        generated_design = output_root / "design"
        for block_json in generated_design.rglob("block.json") if generated_design.is_dir() else []:
            data = read_json(block_json)
            if isinstance(data.get("name"), str):
                output_names.add(data["name"])
        if source_names != output_names:
            fail(f"project {project_id}: canonical block ID set changed during generation")

        manifest_path = output_root / "BUILD-MANIFEST.json"
        manifest = read_json(manifest_path) if manifest_path.is_file() else {}
        expected_manifest = {
            "project": project.get("id"),
            "themeDirectory": project.get("themeDirectory"),
            "themeVersion": project.get("version"),
            "designCoreVersion": core_package.get("version"),
            "wordpressAdapterVersion": adapter_package.get("version"),
            "architectureVersion": workspace.get("architectureVersion"),
            "generated": True,
            "runtimeStandalone": True,
        }
        if manifest != expected_manifest:
            fail(f"project {project_id}: BUILD-MANIFEST.json is stale or inconsistent")

if ERRORS:
    print("Architecture validation failed:")
    for error in ERRORS:
        print(f"- {error}")
    sys.exit(1)

print(f"Architecture validation passed (migration phase {phase}).")
