#!/usr/bin/env python3
"""Create a production WordPress theme ZIP from generated output."""
from __future__ import annotations

import argparse
import json
import zipfile
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def read_json(path: Path) -> dict:
    return json.loads(path.read_text(encoding="utf-8"))


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--project", default="intercargo")
    args = parser.parse_args()

    workspace = read_json(ROOT / "workspace.json")
    project_entry = workspace.get("projects", {}).get(args.project)
    if not isinstance(project_entry, dict):
        raise SystemExit(f"Unknown project: {args.project}")

    project_root = ROOT / project_entry["source"]
    output_root = ROOT / project_entry["themeOutput"]
    project = read_json(project_root / "project.json")
    if not output_root.is_dir():
        raise SystemExit("Theme output is missing. Run npm run build first.")
    if not (output_root / "dist" / ".vite" / "manifest.json").is_file():
        raise SystemExit("Built Vite manifest is missing. Run npm run build first.")

    release_dir = ROOT / "release"
    release_dir.mkdir(parents=True, exist_ok=True)
    archive = release_dir / f"{project['id']}-theme-{project['version']}.zip"
    theme_directory = project["themeDirectory"]
    excluded_roots = {"tests", "tools"}

    with zipfile.ZipFile(archive, "w", compression=zipfile.ZIP_DEFLATED) as bundle:
        for path in sorted(output_root.rglob("*")):
            if not path.is_file():
                continue
            relative = path.relative_to(output_root)
            if relative.parts and relative.parts[0] in excluded_roots:
                continue
            bundle.write(path, Path(theme_directory) / relative)

    print(f"Created {archive.relative_to(ROOT)}")


if __name__ == "__main__":
    main()
