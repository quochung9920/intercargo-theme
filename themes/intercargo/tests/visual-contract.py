#!/usr/bin/env python3
from pathlib import Path
import hashlib, json, sys
ROOT = Path(__file__).resolve().parents[1]
contract = json.loads((ROOT/'tests/visual-contract.json').read_text())
errors=[]
for rel, expected in contract.get('files', {}).items():
    p=ROOT/rel
    if not p.is_file():
        errors.append(f'{rel}: missing baseline file')
        continue
    actual=hashlib.sha256(p.read_bytes()).hexdigest()
    if actual != expected:
        errors.append(f'{rel}: differs from approved {contract.get("baseline", "baseline")} visual/source contract')
if errors:
    for e in errors: print('ERROR:', e)
    print('If this is an intentional visual/section change, review it first, then regenerate tests/visual-contract.json explicitly.')
    sys.exit(1)
print(f'VISUAL CONTRACT OK: {len(contract.get("files", {}))} approved authority files unchanged.')
