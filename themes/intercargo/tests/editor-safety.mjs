import fs from 'node:fs';
import path from 'node:path';
import url from 'node:url';

const here = path.dirname(url.fileURLToPath(import.meta.url));
const root = path.dirname(here);
const editor = fs.readFileSync(path.join(root, 'inc/package-editor.js'), 'utf8');
const migration = fs.readFileSync(path.join(root, 'inc/legacy-id-migration.js'), 'utf8');
const shared = fs.readFileSync(path.join(root, 'inc/shared-sections-editor.js'), 'utf8');
const serviceNavEditor = fs.readFileSync(path.join(root, 'design/sections/service-navigation/editor.js'), 'utf8');
const serviceNavConfig = fs.readFileSync(path.join(root, 'design/sections/service-navigation/editor-config.js'), 'utf8');
const serviceNavView = fs.readFileSync(path.join(root, 'design/sections/service-navigation/view.js'), 'utf8');
const reasonsEditor = fs.readFileSync(path.join(root, 'design/sections/reasons/editor.js'), 'utf8');
const enquiryEditor = fs.readFileSync(path.join(root, 'design/sections/enquiry/editor.js'), 'utf8');
const enquiryEditorCss = fs.readFileSync(path.join(root, 'design/sections/enquiry/editor.css'), 'utf8');
const faqTemplate = fs.readFileSync(path.join(root, 'design/sections/faq/template.json'), 'utf8');
const faqStyle = fs.readFileSync(path.join(root, 'design/sections/faq/style.css'), 'utf8');
const errors = [];

// Generic composition editing may update attributes/media, but it must never rebuild
// the existing InnerBlocks tree from template data. That exact class of mutation can
// replace saved page content with defaults/placeholders during a theme upgrade.
const destructive = [
  'replaceInnerBlocks',
  'resetBlocks',
  'removeBlocks',
  'insertBlocks',
  'insertBlock(',
  'replaceBlocks'
];
for (const token of destructive) {
  if (editor.includes(token)) errors.push(`package-editor.js contains destructive block-tree API: ${token}`);
}

if (!editor.includes('useInnerBlocksProps(blockProps')) {
  errors.push('Generic section editor no longer delegates existing content to useInnerBlocksProps().');
}
if (!editor.includes('template: definition.template || []')) {
  errors.push('Generic editor template contract changed; review hydration behavior before release.');
}
if (!editor.includes("store.getBlock(clientId)")) {
  errors.push('Generic editor no longer reads the existing block tree from the block-editor store.');
}

for (const token of [
  'collectionDefinitions',
  'definition.openCollections',
  "next.templateLock = false",
  "updateBlockAttributes(block.clientId, next)",
  "next.allowedBlocks = allowed"
]) {
  if (!editor.includes(token)) errors.push(`package-editor.js lost repeatable item collection contract: ${token}`);
}

// The historical-ID migration is the one intentional structural replacement. It must
// preserve both attributes and the existing innerBlocks array when it creates the
// canonical block.
if (!migration.includes('createBlock(canonical, attrs, inner)')) {
  errors.push('Legacy ID migration no longer preserves attrs + innerBlocks.');
}
if (!migration.includes("dispatch('core/block-editor').replaceBlock(")) {
  errors.push('Legacy ID migration implementation changed; review saved-content safety.');
}



// Content Columns intentionally performs one structural migration only when an
// old attribute-based instance has zero saved children. Normal existing InnerBlocks
// are never rebuilt from defaults.
for (const token of [
  "if (!block || innerCount !== 0) return;",
  "replaceInnerBlocks(props.clientId, initialTree(attributes), false);",
  "intercargo/content-column",
  "allowedBlocks: ['intercargo/content-column']",
  "token !== 'section-head-gap'"
]) {
  if (!reasonsEditor.includes(token)) errors.push(`reasons/editor.js lost safe Content Columns migration/collection contract: ${token}`);
}



// Enquiry steps are a repeatable attribute collection. Editors must be able to add,
// duplicate, delete and drag them without changing the frontend content model.
for (const token of [
  'function addStep(',
  'function duplicateStep(',
  'function removeStep(',
  'function moveStep(',
  'draggable: true',
  "'+ Add step'",
  'intercargo-enquiry-step-actions'
]) {
  if (!enquiryEditor.includes(token)) errors.push(`enquiry/editor.js lost repeatable-step UX contract: ${token}`);
}
if (!enquiryEditorCss.includes('.intercargo-enquiry-step-actions') || !enquiryEditorCss.includes('.intercargo-enquiry-add-step')) {
  errors.push('enquiry/editor.css lost repeatable-step editor affordances.');
}

// FAQ actions stay native core/button collections, but each historical responsive
// group must be unlocked and clearly named. The question toggle also reserves right
// padding so long titles cannot collide with the custom +/- indicator.
for (const token of [
  'Primary FAQ actions — add, remove or reorder',
  'Secondary FAQ actions — add, remove or reorder',
  'Mobile FAQ actions — add, remove or reorder',
  '"allows": "core/button"'
]) {
  if (!faqTemplate.includes(token)) errors.push(`faq/template.json lost editable action collection contract: ${token}`);
}
for (const token of [
  'padding: 15px 48px 15px 0',
  'padding: 22px 52px 22px 0',
  'overflow-wrap: anywhere'
]) {
  if (!faqStyle.includes(token)) errors.push(`faq/style.css lost question/icon spacing contract: ${token}`);
}

// Shared sections may intentionally replace one selected outer block with a Core
// `core/block` reference, but they must serialize the CURRENT block tree first and
// detach from the saved wp_block content rather than rebuilding defaults.
for (const token of [
  'wp.blocks.serialize([context.block])',
  "wp.blocks.createBlock('core/block', { ref:",
  'wp.blocks.parse(record.content)',
  "dispatch('core/block-editor').replaceBlock(clientId"
]) {
  if (!shared.includes(token)) errors.push(`shared-sections-editor.js lost contract: ${token}`);
}
for (const token of [
  'initialOpen: true',
  'record.usage',
  'Used on ',
  "context.postType === 'wp_block'",
  'context.controllerClientId'
]) {
  if (!shared.includes(token)) errors.push(`shared-sections-editor.js lost persistent/usage UX contract: ${token}`);
}

for (const token of [
  'SharedControlsForSelection',
  'getBlockParents(props.clientId, true)',
  "updateBlockAttributes(owner.clientId",
  'props.isSelected ? createElement(SharedControlsForSelection, props)'
]) {
  if (!shared.includes(token)) errors.push(`shared-sections-editor.js lost nested-selection proxy contract: ${token}`);
}

if (shared.includes('replaceInnerBlocks') || shared.includes('resetBlocks')) {
  errors.push('shared-sections-editor.js must not rebuild an existing section from template defaults.');
}


// Service Navigation is derived from sibling sections, but owns a page-local
// presentation order. The manager must edit section metadata rather than create
// a second set of jump-link blocks.
for (const token of [
  "store.getBlocks ? store.getBlocks()",
  "serviceNavTitle",
  "serviceNavHidden",
  "serviceNavKey",
  "sectionAnchor",
  "tabOrder",
  "draggable: true",
  "edit the title inline",
  "Targets are generated automatically",
  "intercargo-service-nav-manager__title-input",
  "updateBlockAttributes(item.clientId",
  "serviceNavTitle: event.target.value"
]) {
  if (!serviceNavEditor.includes(token)) errors.push(`service-navigation/editor.js lost live tab-manager contract: ${token}`);
}
for (const forbidden of ['service-jump-link', 'insertBlock(', 'insertBlocks', 'replaceInnerBlocks', "label: 'Target ID'", "label: 'Tab title'"]) {
  if (serviceNavEditor.includes(forbidden)) errors.push(`service-navigation/editor.js contains obsolete/manual navigation behavior: ${forbidden}`);
}
if (!serviceNavConfig.includes('Service Navigation editor configuration')) {
  errors.push('service-navigation/editor-config.js bridge is missing.');
}


// Service Navigation scroll sync must not animate every intermediate tab while a
// long vertical click-scroll passes through many sections.
for (const token of [
  'programmaticPair',
  'scheduleActiveTabReveal',
  'instantWindowScroll',
  'stopProgrammaticScroll',
  'ACTIVE_LINE_RATIO',
  "window.scrollTo({ top: top, behavior: 'smooth' })"
]) {
  if (!serviceNavView.includes(token)) errors.push(`service-navigation/view.js lost scroll-sync UX contract: ${token}`);
}
if (serviceNavView.includes("updateFromViewport(reducedMotion ? 'auto' : 'smooth')")) {
  errors.push('service-navigation/view.js restored the old per-scroll horizontal smooth animation.');
}

if (errors.length) {
  for (const error of errors) console.error(`ERROR: ${error}`);
  process.exit(1);
}
console.log('EDITOR SAFETY OK: generic editor has no destructive tree mutation; legacy rename preserves saved children.');
