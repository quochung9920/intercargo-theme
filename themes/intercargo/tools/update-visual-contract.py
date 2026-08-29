#!/usr/bin/env python3
"""Regenerate the reviewed visual/source baseline after an intentional design change.
Do not call this automatically from build or validation.
"""
from pathlib import Path
import hashlib, json
ROOT=Path(__file__).resolve().parents[1]
contract_path=ROOT/'tests/visual-contract.json'
current=json.loads(contract_path.read_text()) if contract_path.exists() else {'files':{}}
files=sorted(current.get('files', {}).keys())
if not files:
    raise SystemExit('No baseline file list found.')
out={}
for rel in files:
    p=ROOT/rel
    if not p.is_file(): raise SystemExit(f'Missing baseline file: {rel}')
    out[rel]=hashlib.sha256(p.read_bytes()).hexdigest()
contract_path.write_text(json.dumps({'baseline':'reviewed-manual-update','files':out},indent=2)+'\n')
print(f'Updated {len(out)} visual/source fingerprints. Review the diff before release.')
