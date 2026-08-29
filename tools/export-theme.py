#!/usr/bin/env python3
"""Generate a standalone WordPress theme from workspace source."""
from __future__ import annotations

import argparse
import json
import shutil
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read_json(path: Path) -> dict:
    return json.loads(path.read_text(encoding="utf-8"))


def remove_path(path: Path) -> None:
    if path.is_dir() and not path.is_symlink():
        shutil.rmtree(path)
    elif path.exists() or path.is_symlink():
        path.unlink()


def copy_dir(source: Path, destination: Path) -> None:
    if not source.is_dir():
        raise SystemExit(f"Missing source directory: {source.relative_to(ROOT)}")
    shutil.copytree(source, destination, dirs_exist_ok=True)


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--project", default="intercargo")
    parser.add_argument(
        "--clean-dist",
        action="store_true",
        help="Remove the existing Vite dist directory before export.",
    )
    args = parser.parse_args()

    workspace = read_json(ROOT / "workspace.json")
    project_entry = workspace.get("projects", {}).get(args.project)
    if not isinstance(project_entry, dict):
        raise SystemExit(f"Unknown project: {args.project}")

    project_root = ROOT / project_entry["source"]
    output_root = ROOT / project_entry["themeOutput"]
    project = read_json(project_root / "project.json")
    adapter_root = ROOT / workspace["packages"]["wordpressAdapter"]
    design_core_root = ROOT / workspace["packages"]["designCore"]

    paths = project["paths"]
    shell_root = project_root / paths["theme"]
    design_root = project_root / paths["design"]
    assets_root = project_root / paths["assets"]
    qa_root = project_root / paths["qa"]
    runtime_root = adapter_root / "runtime"

    output_root.mkdir(parents=True, exist_ok=True)

    # Generated source/runtime is fully replaced. Vite output is preserved during
    # sync and rebuilt by `npm run build` immediately afterwards.
    for child in list(output_root.iterdir()):
        if child.name == "dist" and not args.clean_dist:
            continue
        remove_path(child)

    copy_dir(shell_root, output_root)
    copy_dir(design_root, output_root / "design")
    copy_dir(assets_root, output_root / "assets")
    copy_dir(runtime_root, output_root / "inc")

    qa_tests = qa_root / "tests"
    qa_tools = qa_root / "tools"
    if qa_tests.is_dir():
        copy_dir(qa_tests, output_root / "tests")
    if qa_tools.is_dir():
        copy_dir(qa_tools, output_root / "tools")

    core_package = read_json(design_core_root / "package.json")
    adapter_package = read_json(adapter_root / "package.json")
    manifest = {
        "project": project["id"],
        "themeDirectory": project["themeDirectory"],
        "themeVersion": project["version"],
        "designCoreVersion": core_package.get("version", "unknown"),
        "wordpressAdapterVersion": adapter_package.get("version", "unknown"),
        "architectureVersion": workspace.get("architectureVersion"),
        "generated": True,
        "runtimeStandalone": True,
    }
    (output_root / "BUILD-MANIFEST.json").write_text(
        json.dumps(manifest, indent=2) + "\n",
        encoding="utf-8",
    )

    print(f"Generated {output_root.relative_to(ROOT)} from {project_root.relative_to(ROOT)}")


if __name__ == "__main__":
    main()
