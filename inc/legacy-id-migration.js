(function (wp, window) {
  'use strict';

  if (!wp || !wp.blocks || !wp.data) return;

  var map = window.intercargoLegacyBlockMap || {};
  var legacyNames = Object.keys(map);
  if (!legacyNames.length) return;

  var select = wp.data.select;
  var dispatch = wp.data.dispatch;
  var createBlock = wp.blocks.createBlock;
  var finished = false;
  var running = false;

  function legacyBlocks(blocks, out) {
    out = out || [];
    (blocks || []).forEach(function (block) {
      if (!block) return;
      if (legacyNames.indexOf(block.name) !== -1) out.push(block);
      if (block.innerBlocks && block.innerBlocks.length) legacyBlocks(block.innerBlocks, out);
    });
    return out;
  }

  function run() {
    if (finished || running) return;
    var store = select('core/block-editor');
    if (!store || !store.getBlocks) return;

    var blocks = store.getBlocks();
    if (!Array.isArray(blocks) || !blocks.length) return;

    var found = legacyBlocks(blocks, []);
    if (!found.length) {
      finished = true;
      return;
    }

    // Wait until every canonical block type is available in the client registry.
    // This avoids racing PHP metadata registration against editor scripts.
    for (var i = 0; i < found.length; i += 1) {
      var canonical = map[found[i].name];
      if (!canonical || !wp.blocks.getBlockType(canonical)) return;
    }

    running = true;
    found.forEach(function (block) {
      var canonical = map[block.name];
      var attrs = Object.assign({}, block.attributes || {});
      var inner = Array.isArray(block.innerBlocks) ? block.innerBlocks : [];
      dispatch('core/block-editor').replaceBlock(
        block.clientId,
        createBlock(canonical, attrs, inner)
      );
    });
    running = false;
    // Keep the subscription for one more store update. It will stop once no
    // historical IDs remain in the editor tree.
  }

  var unsubscribe = wp.data.subscribe(function () {
    run();
    if (finished && unsubscribe) unsubscribe();
  });

  window.setTimeout(run, 0);
})(window.wp, window);
