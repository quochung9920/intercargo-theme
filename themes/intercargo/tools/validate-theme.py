#!/usr/bin/env python3
from pathlib import Path
import json, re, sys
ROOT = Path(__file__).resolve().parents[1]
errors=[]
design_sections=ROOT/'design'/'sections'
section_roots=(design_sections,)
components=ROOT/'design'/'components'
contracts=json.loads((ROOT/'tests/section-contracts.json').read_text())

all_meta={}
for base in (*section_roots, components):
    if not base.exists(): continue
    for block_json in base.rglob('block.json'):
        try: meta=json.loads(block_json.read_text())
        except Exception as e:
            errors.append(f'{block_json.relative_to(ROOT)}: invalid JSON: {e}')
            continue
        name=str(meta.get('name',''))
        if not name.startswith('intercargo/'):
            errors.append(f'{block_json.relative_to(ROOT)}: non-canonical name {name!r}')
        if name in all_meta:
            errors.append(f'duplicate block name {name}: {block_json.relative_to(ROOT)} and {all_meta[name][0].relative_to(ROOT)}')
        all_meta[name]=(block_json,meta)
        for key in ('render','style','editorStyle','editorScript','viewScript','viewStyle','script'):
            refs=meta.get(key,[])
            if not isinstance(refs,list): refs=[refs]
            for ref in refs:
                if isinstance(ref,str) and ref.startswith('file:'):
                    target=block_json.parent/ref[5:]
                    if not target.is_file(): errors.append(f'{block_json.relative_to(ROOT)}: missing {key} file {ref}')

expected=set(contracts['topLevelSections'])
actual=set()
for base in section_roots:
    if not base.exists(): continue
    for p in base.glob('*/block.json'):
        try:
            meta=json.loads(p.read_text())
            cfg=meta.get('intercargo') if isinstance(meta.get('intercargo'),dict) else {}
            if cfg.get('sectionPackage') is True:
                actual.add(meta.get('name',''))
        except Exception:
            pass
if actual != expected:
    errors.append(f'top-level section set mismatch: missing={sorted(expected-actual)}, extra={sorted(actual-expected)}')

for name, contract in contracts['topLevelSections'].items():
    if name not in all_meta: continue
    path,meta=all_meta[name]
    if meta.get('category') != 'intercargo-sections':
        errors.append(f'{name}: top-level section must use the Intercargo Sections category')
    default_path='design/sections/'+name.removeprefix('intercargo/')
    expected_path=contract.get('packagePath', default_path)
    actual_path=str(path.parent.relative_to(ROOT)).replace('\\','/')
    if actual_path != expected_path:
        errors.append(f'{name}: package path mismatch; expected {expected_path}, got {actual_path}')
    supports=meta.get('supports') if isinstance(meta.get('supports'),dict) else {}
    expected_syncable=contract.get('syncable', True) is True
    if supports.get('reusable') is not expected_syncable:
        errors.append(f'{name}: reusable/syncable contract mismatch; expected {expected_syncable}')
    if supports.get('multiple') is not True: errors.append(f'{name}: top-level sections must remain repeatable (supports.multiple=true)')
    if contract['editor']=='composition':
        cfg=meta.get('intercargo') if isinstance(meta.get('intercargo'),dict) else {}
        if meta.get('editorScript')!='intercargo-package-editor': errors.append(f'{name}: composition section lost intercargo-package-editor')
        if cfg.get('packageType')!='section': errors.append(f'{name}: composition section missing intercargo.packageType=section')
        if cfg.get('compositionSlug')!=contract['compositionSlug']: errors.append(f'{name}: compositionSlug mismatch')
        t=path.parent/'template.json'
        if not t.is_file(): errors.append(f'{name}: missing template.json')
        else:
            try:
                td=json.loads(t.read_text())
                if not isinstance(td.get('template'),list) or not td['template']: errors.append(f'{name}: template.json has empty/missing template')
            except Exception as e: errors.append(f'{name}: invalid template.json: {e}')
    else:
        if meta.get('editorScript')!='file:./editor.js': errors.append(f'{name}: custom native editor must remain local file:./editor.js')

for name in contracts['nonReusableComponents']:
    if name not in all_meta:
        errors.append(f'{name}: expected child/component block missing')
        continue
    supports=all_meta[name][1].get('supports') if isinstance(all_meta[name][1].get('supports'),dict) else {}
    if supports.get('reusable') is not False: errors.append(f'{name}: child/component must remain reusable=false')

# Every custom block referenced by a composition template must be registered somewhere.
def walk(nodes, owner):
    if not isinstance(nodes,list): return
    for node in nodes:
        if not isinstance(node,list) or not node: continue
        name=str(node[0])
        if name.startswith('intercargo/') and name not in all_meta:
            errors.append(f'{owner}: template references unregistered custom block {name}')
        if len(node)>2: walk(node[2], owner)
for name,(p,meta) in all_meta.items():
    t=p.parent/'template.json'
    if t.is_file():
        try: walk(json.loads(t.read_text()).get('template',[]), t.relative_to(ROOT))
        except Exception: pass

if (ROOT/'blocks').exists(): errors.append('legacy /blocks directory must not exist in V4')
for needle in ('acf_add_local_field_group','acf_register_block_type','get_field('):
    for p in list((ROOT/'inc').glob('*.php'))+[ROOT/'functions.php']:
        if p.is_file() and needle in p.read_text(errors='ignore'):
            errors.append(f'{p.relative_to(ROOT)} still contains ACF API {needle}')

# Registration/hydration wiring invariants that caught the failed 4.1.x split class of bug.
loader=(ROOT/'inc/block-packages.php').read_text(errors='ignore')
if 'register_block_type($dir)' not in loader: errors.append('inc/block-packages.php no longer registers discovered packages via register_block_type($dir)')
if "wp_add_inline_script('intercargo-package-editor'" not in loader or 'intercargo_package_editor_definitions()' not in loader:
    errors.append('generic package editor definitions are no longer injected before editor runtime')
if "add_action('init', 'intercargo_register_block_packages', 19)" not in loader:
    errors.append('block package registration init timing changed; existing-content hydration requires explicit review')


# UI component and vendor-library contract.
component_files=list((ROOT/'design/components').glob('*/component.json'))
for component_json in component_files:
    try: meta=json.loads(component_json.read_text())
    except Exception as e:
        errors.append(f'{component_json.relative_to(ROOT)}: invalid component JSON: {e}'); continue
    for key in ('style','viewScript'):
        ref=meta.get(key)
        if ref and not (component_json.parent/ref).is_file(): errors.append(f'{component_json.relative_to(ROOT)}: missing {key} {ref}')
allowed_vendor={'gsap','scrolltrigger','flip','splittext','scrollto','motionpath','observer','draggable','swiper','lenis','lottie'}
for name,(path,meta) in all_meta.items():
    cfg=meta.get('intercargo') if isinstance(meta.get('intercargo'),dict) else {}
    unknown=set(cfg.get('libraries',[]) or [])-allowed_vendor
    if unknown: errors.append(f'{name}: unknown vendor libraries {sorted(unknown)}')
# Final design-layer contract: visual authorities live under design/, while
# WordPress/Vite entry paths remain stable proxies.
phase1_required = [
    ROOT/'design/header/header.php',
    ROOT/'design/footer/footer.php',
    ROOT/'design/global/css/global.css',
    ROOT/'design/global/css/editor.css',
    ROOT/'design/global/js/site.js',
    ROOT/'design/global/js/editor.js',
    ROOT/'design/global/js/editor/accent.js',
]
for p in phase1_required:
    if not p.is_file(): errors.append(f'{p.relative_to(ROOT)}: missing Phase-1 design authority')
proxy_expectations = {
    ROOT/'header.php': "design/header/header.php",
    ROOT/'footer.php': "design/footer/footer.php",
    ROOT/'src/css/global.css': "../../design/global/css/global.css",
    ROOT/'src/css/editor.css': "../../design/global/css/editor.css",
    ROOT/'src/js/site.js': "../../design/global/js/site.js",
    ROOT/'src/js/editor.js': "../../design/global/js/editor.js",
}
for p, needle in proxy_expectations.items():
    if not p.is_file():
        errors.append(f'{p.relative_to(ROOT)}: missing stable proxy')
    elif needle not in p.read_text(errors='ignore'):
        errors.append(f'{p.relative_to(ROOT)}: proxy no longer points to {needle}')


# Final section contract: all top-level section packages live under design/sections;
# no legacy section/component roots remain.
if (ROOT/'sections').exists():
    errors.append('obsolete root /sections directory must not exist in final release')
for block_name, contract in contracts['topLevelSections'].items():
    default_path = 'design/sections/' + block_name.removeprefix('intercargo/')
    package_path = contract.get('packagePath', default_path)
    block_file = ROOT / package_path / 'block.json'
    if not block_file.is_file():
        errors.append(f'{block_name}: missing {package_path}/block.json')
for child_name, contract in contracts.get('compositionItems', {}).items():
    package_path=contract.get('packagePath')
    if not package_path:
        continue
    child_path=ROOT/package_path/'block.json'
    if not child_path.is_file():
        errors.append(f'{child_name}: nested package missing at {package_path}/block.json')
        continue
    try:
        child_meta=json.loads(child_path.read_text())
    except Exception:
        continue
    expected_category=contract.get('category', 'intercargo-section-items')
    if child_meta.get('category') != expected_category:
        errors.append(f'{child_name}: nested item must use category {expected_category}')
    if 'inserter' in contract:
        supports=child_meta.get('supports') if isinstance(child_meta.get('supports'),dict) else {}
        if supports.get('inserter') is not contract['inserter']:
            errors.append(f'{child_name}: supports.inserter must be {contract["inserter"]}')


# CSS relative asset contract: every local url(...) must resolve from the CSS file's
# actual filesystem location. This catches regressions when sections/components move.
css_url_re = re.compile(r'url\(\s*[\"\']?([^\"\')]+)[\"\']?\s*\)', re.I)
for css in ROOT.rglob('*.css'):
    # Generated dist files may contain rewritten/bundled URLs; validate source authorities only.
    if 'dist' in css.parts:
        continue
    content = css.read_text(errors='ignore')
    for match in css_url_re.finditer(content):
        ref = match.group(1).strip()
        if not ref or ref.startswith(('data:', 'http://', 'https://', '//', '#', 'var(')):
            continue
        # Strip query/hash because they are not part of the local filesystem path.
        local_ref = ref.split('#', 1)[0].split('?', 1)[0]
        if not local_ref:
            continue
        target = (css.parent / local_ref).resolve()
        try:
            target.relative_to(ROOT.resolve())
        except ValueError:
            errors.append(f'{css.relative_to(ROOT)}: CSS url escapes theme root: {ref}')
            continue
        if not target.is_file():
            errors.append(f'{css.relative_to(ROOT)}: missing CSS url asset {ref}')

if (ROOT/'components').exists(): errors.append('obsolete root /components directory must not exist in final release')
if not (ROOT/'design/components/form/block.json').is_file(): errors.append('design/components/form block package missing')


# Shared-section foundation: Core wp_block storage + editor UX must remain wired.
shared_php = ROOT/'inc/shared-sections.php'
shared_js = ROOT/'inc/shared-sections-editor.js'
if not shared_php.is_file(): errors.append('inc/shared-sections.php: missing shared-section Core storage layer')
if not shared_js.is_file(): errors.append('inc/shared-sections-editor.js: missing shared-section editor UX')
functions_text=(ROOT/'functions.php').read_text(errors='ignore')
if "inc/shared-sections.php" not in functions_text: errors.append('functions.php no longer loads shared-section foundation')
if shared_php.is_file():
    shared_text=shared_php.read_text(errors='ignore')
    for token in ('post_type\'    => \'wp_block', 'intercargo_shared_section_type_from_content', "register_rest_route('intercargo/v1', '/shared-sections'", 'INTERCARGO_SHARED_SECTION_KEY_META', 'intercargo_shared_section_usage'):
        if token not in shared_text: errors.append(f'inc/shared-sections.php missing contract token: {token}')
if shared_js.is_file():
    shared_editor=shared_js.read_text(errors='ignore')
    for token in ("wp.blocks.serialize([context.block])", "wp.blocks.createBlock('core/block', { ref:", "wp.blocks.parse(record.content)", "replaceBlock(clientId", "Detach to local", "Save as shared", "record.usage", "initialOpen: true"):
        if token not in shared_editor: errors.append(f'inc/shared-sections-editor.js missing UX contract token: {token}')


# Page-shell settings contract (4.7.0).
page_settings = ROOT/'inc'/'page-settings.php'
page_editor = ROOT/'inc'/'page-settings-editor.js'
header_css = ROOT/'design'/'header'/'header.css'
for required in (page_settings, page_editor, header_css):
    if not required.is_file(): errors.append(f'{required.relative_to(ROOT)}: missing page-shell foundation file')
if page_settings.is_file():
    pst = page_settings.read_text(errors='ignore')
    for needle in ('_intercargo_transparent_header','_intercargo_hide_announcement','intercargo_migrate_page_shell_defaults_470'):
        if needle not in pst: errors.append(f'inc/page-settings.php: missing {needle}')
if page_editor.is_file():
    pet = page_editor.read_text(errors='ignore')
    for needle in ('Intercargo Page Settings','Transparent header','Hide announcement / world clocks'):
        if needle not in pet: errors.append(f'inc/page-settings-editor.js: missing {needle}')
header_file = ROOT/'design'/'header'/'header.php'
if header_file.is_file():
    h = header_file.read_text(errors='ignore')
    for needle in ('site-header--<?php echo esc_attr($header_variant); ?>','site-nav--<?php echo esc_attr($header_variant); ?>','$header_logo_url'):
        if needle not in h: errors.append(f'design/header/header.php: missing page-shell variant hook {needle}')


# Announcement / world-clock contract (4.7.2).
announcement_php = ROOT/'inc'/'announcement.php'
announcement_dir = ROOT/'design'/'components'/'announcement'
for required in (announcement_php, announcement_dir/'render.php', announcement_dir/'style.css', announcement_dir/'view.js', announcement_dir/'clock.svg'):
    if not required.is_file(): errors.append(f'{required.relative_to(ROOT)}: missing world-clock component file')
if announcement_php.is_file():
    at = announcement_php.read_text(errors='ignore')
    for needle in ('Australia/Sydney','Asia/Shanghai','America/Los_Angeles','Asia/Singapore','Asia/Tokyo','Asia/Seoul','intercargo_page_hides_announcement'):
        if needle not in at: errors.append(f'inc/announcement.php: missing 4.7.2 world-clock contract token {needle}')
announcement_render = announcement_dir/'render.php'
announcement_style = announcement_dir/'style.css'
if announcement_render.is_file() and 'announce-inner' not in announcement_render.read_text(errors='ignore'):
    errors.append('design/components/announcement/render.php: missing header-aligned .announce-inner wrapper')
if announcement_style.is_file():
    ast = announcement_style.read_text(errors='ignore')
    for token in ('.announce-inner', 'var(--nav-gutter)', 'margin-inline: auto'):
        if token not in ast: errors.append(f'design/components/announcement/style.css: missing header-alignment contract token {token}')

announcement_js = announcement_dir/'view.js'
if announcement_js.is_file():
    aj = announcement_js.read_text(errors='ignore')
    for needle in ('Intl.DateTimeFormat','data-tz','setInterval','--intercargo-announcement-height'):
        if needle not in aj: errors.append(f'design/components/announcement/view.js: missing live-clock contract token {needle}')
if header_file.is_file():
    ht = header_file.read_text(errors='ignore')
    if 'intercargo_render_announcement' not in ht: errors.append('design/header/header.php: announcement is no longer rendered above the header')


# Branding/header variant contract (4.7.1).
brand_php = ROOT/'inc'/'brand.php'
if not brand_php.is_file():
    errors.append('inc/brand.php: missing branding layer')
else:
    bt = brand_php.read_text(errors='ignore')
    for needle in ('intercargo_primary_logo', 'intercargo_white_logo', 'intercargo_brand_primary_logo_url', 'intercargo_brand_white_logo_url', 'intercargo_migrate_logo_settings_471'):
        if needle not in bt:
            errors.append(f'inc/brand.php: missing 4.7.1 branding contract token {needle}')
if header_css.is_file():
    hct = header_css.read_text(errors='ignore')
    for needle in ('.site-nav--light .nav-links .sub-menu', 'background: #fff', 'color: #5b5955'):
        if needle not in hct:
            errors.append(f'design/header/header.css: missing 4.7.1 light dropdown contract token {needle}')

if errors:
    print('\n'.join('ERROR: '+e for e in errors)); sys.exit(1)
print(f'STRUCTURE OK: {len(expected)} top-level sections, {len(all_meta)} total packages, canonical registration intact.')
