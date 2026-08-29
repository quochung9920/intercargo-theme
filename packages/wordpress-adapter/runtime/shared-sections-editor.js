(function (wp, window) {
  'use strict';
  if (!wp || !wp.hooks || !wp.blockEditor || !wp.components || !wp.data || !wp.element || !wp.blocks || !wp.apiFetch) return;

  var config = window.intercargoSharedSectionsConfig || {};
  var sectionNames = Array.isArray(config.sectionNames) ? config.sectionNames : [];
  var restPath = config.restPath || '/intercargo/v1/shared-sections';
  var createElement = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var useEffect = wp.element.useEffect;
  var useState = wp.element.useState;
  var useSelect = wp.data.useSelect;
  var dispatch = wp.data.dispatch;
  var InspectorControls = wp.blockEditor.InspectorControls;
  var BlockControls = wp.blockEditor.BlockControls;
  var PanelBody = wp.components.PanelBody;
  var TextControl = wp.components.TextControl;
  var SelectControl = wp.components.SelectControl;
  var Button = wp.components.Button;
  var Notice = wp.components.Notice;
  var Spinner = wp.components.Spinner;
  var ToolbarGroup = wp.components.ToolbarGroup;
  var ToolbarButton = wp.components.ToolbarButton;
  var __ = wp.i18n && wp.i18n.__ ? wp.i18n.__ : function (text) { return text; };

  var listCache = {};

  function sectionTypeFromName(name) {
    if (sectionNames.indexOf(name) === -1) return '';
    return name.replace(/^intercargo\//, '');
  }

  function fetchSharedList(sectionType, force) {
    if (!force && listCache[sectionType]) return Promise.resolve(listCache[sectionType]);
    return wp.apiFetch({ path: restPath + '?sectionType=' + encodeURIComponent(sectionType) }).then(function (response) {
      var items = response && Array.isArray(response.items) ? response.items : [];
      listCache[sectionType] = items;
      return items;
    });
  }

  function fetchShared(ref) {
    return wp.apiFetch({ path: restPath + '/' + Number(ref) });
  }

  function normaliseTitle(value) {
    if (typeof value === 'string') return value.trim();
    if (value && typeof value.raw === 'string') return value.raw.trim();
    if (value && typeof value.rendered === 'string') return value.rendered.replace(/<[^>]+>/g, '').trim();
    return '';
  }

  function SharedPanel(props) {
    var name = props.name;
    var clientId = props.clientId;
    var attributes = props.attributes || {};
    var setAttributes = props.setAttributes;
    var sectionType = sectionTypeFromName(name);
    var directRef = name === 'core/block' ? Number(attributes.ref) || 0 : 0;
    var isReference = directRef > 0;

    var context = useSelect(function (select) {
      var store = select('core/block-editor');
      var editor = select('core/editor');
      if (!store) return { block: null, sharedRef: 0, controllerClientId: '', postId: 0, postType: '', postTitle: '' };

      var block = store.getBlock(clientId);
      var controllerClientId = '';
      var sharedRef = 0;
      var parents = store.getBlockParents(clientId, true) || [];

      parents.some(function (parentId) {
        var parent = store.getBlock(parentId);
        if (parent && parent.name === 'core/block' && Number(parent.attributes && parent.attributes.ref) > 0) {
          controllerClientId = parentId;
          sharedRef = Number(parent.attributes.ref);
          return true;
        }
        return false;
      });

      if (!sharedRef && store.getBlockRootClientId) {
        var rootId = store.getBlockRootClientId(clientId);
        var root = rootId ? store.getBlock(rootId) : null;
        if (root && root.name === 'core/block' && Number(root.attributes && root.attributes.ref) > 0) {
          controllerClientId = rootId;
          sharedRef = Number(root.attributes.ref);
        }
      }

      var postId = editor && editor.getCurrentPostId ? Number(editor.getCurrentPostId()) || 0 : 0;
      var postType = editor && editor.getCurrentPostType ? String(editor.getCurrentPostType() || '') : '';
      var postTitle = editor && editor.getEditedPostAttribute ? normaliseTitle(editor.getEditedPostAttribute('title')) : '';

      return {
        block: block,
        sharedRef: sharedRef,
        controllerClientId: controllerClientId,
        postId: postId,
        postType: postType,
        postTitle: postTitle
      };
    }, [clientId]);

    // When Gutenberg enters "Edit pattern" mode it edits the wp_block entity
    // directly and the core/block controller may no longer appear as a normal
    // parent in the current block tree. The current wp_block entity ID is then
    // the shared identity we need for status and usage reporting.
    var entityRef = sectionType && context.postType === 'wp_block' ? Number(context.postId) || 0 : 0;
    var effectiveRef = directRef || context.sharedRef || entityRef;

    var recordState = useState(null);
    var record = recordState[0];
    var setRecord = recordState[1];
    var listState = useState([]);
    var items = listState[0];
    var setItems = listState[1];
    var titleState = useState('');
    var newTitle = titleState[0];
    var setNewTitle = titleState[1];
    var chosenState = useState('');
    var chosen = chosenState[0];
    var setChosen = chosenState[1];
    var busyState = useState(false);
    var busy = busyState[0];
    var setBusy = busyState[1];
    var errorState = useState('');
    var error = errorState[0];
    var setError = errorState[1];

    useEffect(function () {
      var cancelled = false;
      setRecord(null);
      if (!effectiveRef) return function () { cancelled = true; };
      setBusy(true);
      fetchShared(effectiveRef).then(function (next) {
        if (cancelled) return;
        setRecord(next || null);
        var type = next && next.sectionType ? next.sectionType : '';
        if (type) return fetchSharedList(type).then(function (list) { if (!cancelled) setItems(list); });
      }).catch(function () {
        if (!cancelled) setRecord(null);
      }).finally(function () { if (!cancelled) setBusy(false); });
      return function () { cancelled = true; };
    }, [effectiveRef]);

    useEffect(function () {
      var cancelled = false;
      if (!sectionType || effectiveRef) return function () { cancelled = true; };
      fetchSharedList(sectionType).then(function (list) { if (!cancelled) setItems(list); }).catch(function () {});
      return function () { cancelled = true; };
    }, [sectionType, effectiveRef]);

    // The panel is intentionally persistent for every top-level Intercargo
    // section. core/block is inspected only when it resolves to one of our
    // shared section records.
    if (!isReference && !sectionType) return null;
    if (isReference && !busy && !record) return null;

    function saveAsShared() {
      if (!sectionType || !context.block || !newTitle.trim() || busy) return;
      setBusy(true); setError('');
      var content = wp.blocks.serialize([context.block]);
      wp.apiFetch({
        path: restPath,
        method: 'POST',
        data: { sectionType: sectionType, title: newTitle.trim(), content: content }
      }).then(function (created) {
        if (!created || !created.id) throw new Error(__('Could not create shared section.', 'intercargo-vite'));
        listCache[sectionType] = null;
        var refBlock = wp.blocks.createBlock('core/block', { ref: Number(created.id) });
        dispatch('core/block-editor').replaceBlock(clientId, refBlock);
      }).catch(function (err) {
        setError(err && err.message ? err.message : __('Could not create shared section.', 'intercargo-vite'));
      }).finally(function () { setBusy(false); });
    }

    function useExistingShared() {
      var ref = Number(chosen);
      if (!ref || busy) return;
      var selected = items.find(function (item) { return Number(item.id) === ref; });
      var label = selected ? selected.title : __('the selected shared section', 'intercargo-vite');
      if (!window.confirm(__('Replace this local section with shared content from ', 'intercargo-vite') + '“' + label + '”?\n\n' + __('The current local content will be removed from this page.', 'intercargo-vite'))) return;
      var refBlock = wp.blocks.createBlock('core/block', { ref: ref });
      dispatch('core/block-editor').replaceBlock(clientId, refBlock);
    }

    function detachToLocal() {
      if (!record || !record.content || busy) return;
      var targetClientId = isReference ? clientId : context.controllerClientId;
      if (!targetClientId) {
        setError(__('Exit pattern editing and select the shared pattern wrapper on the page to detach it.', 'intercargo-vite'));
        return;
      }
      setError('');
      var parsed = wp.blocks.parse(record.content).filter(function (block) { return block && block.name; });
      if (parsed.length !== 1 || sectionNames.indexOf(parsed[0].name) === -1) {
        setError(__('This shared section does not contain one valid section block.', 'intercargo-vite'));
        return;
      }
      dispatch('core/block-editor').replaceBlock(targetClientId, parsed[0]);
    }

    function switchShared() {
      var ref = Number(chosen);
      if (!ref || ref === Number(effectiveRef) || busy) return;
      var selected = items.find(function (item) { return Number(item.id) === ref; });
      var label = selected ? selected.title : __('the selected variation', 'intercargo-vite');
      if (!window.confirm(__('Switch this page to shared section ', 'intercargo-vite') + '“' + label + '”?')) return;

      if (isReference) {
        setAttributes({ ref: ref });
      } else if (context.controllerClientId) {
        dispatch('core/block-editor').updateBlockAttributes(context.controllerClientId, { ref: ref });
      } else {
        setError(__('Exit pattern editing and select the shared pattern wrapper on the page to switch variations.', 'intercargo-vite'));
        return;
      }
      setChosen('');
    }

    function mergedUsage() {
      var usage = record && Array.isArray(record.usage) ? record.usage.slice() : [];
      if (record && context.postId && context.postType && context.postType !== 'wp_block') {
        var exists = usage.some(function (item) { return Number(item.id) === Number(context.postId); });
        if (!exists) {
          usage.unshift({
            id: Number(context.postId),
            title: context.postTitle || __('Current page', 'intercargo-vite'),
            postType: context.postType,
            postTypeLabel: context.postType === 'page' ? __('Page', 'intercargo-vite') : context.postType,
            status: __('unsaved reference', 'intercargo-vite'),
            editUrl: window.location.href.split('#')[0],
            viewUrl: ''
          });
        }
      }
      return usage;
    }

    function renderUsageList() {
      if (!record) return null;
      var usage = mergedUsage();
      var count = usage.length;
      return createElement('div', { className: 'intercargo-shared-usage', style: { marginTop: '18px' } },
        createElement('strong', { style: { display: 'block', marginBottom: '8px' } },
          count === 1 ? __('Used on 1 page', 'intercargo-vite') : __('Used on ', 'intercargo-vite') + count + __(' pages', 'intercargo-vite')
        ),
        count ? createElement('ul', { style: { margin: '0', paddingLeft: '18px' } }, usage.map(function (item) {
          var meta = [item.postTypeLabel, item.status].filter(Boolean).join(' · ');
          return createElement('li', { key: String(item.id), style: { marginBottom: '8px' } },
            item.editUrl ? createElement('a', { href: item.editUrl, target: '_blank', rel: 'noreferrer' }, item.title) : createElement('span', null, item.title),
            meta ? createElement('span', { style: { display: 'block', color: '#757575', fontSize: '12px', marginTop: '2px' } }, meta) : null
          );
        })) : createElement('p', { style: { margin: 0, color: '#757575' } }, __('Not used on any saved pages yet.', 'intercargo-vite'))
      );
    }

    var options = [{ label: __('Select shared section…', 'intercargo-vite'), value: '' }].concat(items.map(function (item) {
      return { label: item.title, value: String(item.id) };
    }));

    var panelContent;
    var toolbar = null;
    var sharedMode = !!record;

    if (sharedMode) {
      if (ToolbarGroup && ToolbarButton) {
        toolbar = createElement(BlockControls, { group: 'block' },
          createElement(ToolbarGroup, null,
            createElement(ToolbarButton, { icon: 'admin-links', label: __('Shared: ', 'intercargo-vite') + record.title, isPressed: true }, __('Shared', 'intercargo-vite'))
          )
        );
      }
      panelContent = createElement(PanelBody, { title: __('Reuse & sync', 'intercargo-vite'), initialOpen: true },
        busy ? createElement(Spinner) : null,
        createElement(Notice, { status: 'info', isDismissible: false },
          createElement('strong', null, __('Shared: ', 'intercargo-vite') + record.title),
          createElement('br'),
          __('Edits to this synced section update every page using the same shared variation.', 'intercargo-vite')
        ),
        renderUsageList(),
        items.length > 1 ? createElement(Fragment, null,
          createElement('hr', { style: { margin: '20px 0' } }),
          createElement(SelectControl, { label: __('Switch shared variation', 'intercargo-vite'), value: chosen, options: options, onChange: setChosen }),
          createElement(Button, { variant: 'secondary', onClick: switchShared, disabled: !chosen || busy }, __('Use selected variation', 'intercargo-vite'))
        ) : null,
        createElement('div', { style: { marginTop: '16px' } },
          createElement(Button, { variant: 'secondary', isDestructive: true, onClick: detachToLocal, disabled: busy }, __('Detach to local', 'intercargo-vite'))
        ),
        error ? createElement(Notice, { status: 'error', isDismissible: false }, error) : null
      );
    } else if (effectiveRef && busy) {
      panelContent = createElement(PanelBody, { title: __('Reuse & sync', 'intercargo-vite'), initialOpen: true }, createElement(Spinner));
    } else {
      panelContent = createElement(PanelBody, { title: __('Reuse & sync', 'intercargo-vite'), initialOpen: true },
        createElement(Notice, { status: 'info', isDismissible: false }, __('Local section. Changes currently affect only this page.', 'intercargo-vite')),
        context.postType && context.postType !== 'wp_block' ? createElement('div', { style: { margin: '12px 0 18px' } },
          createElement('strong', { style: { display: 'block', marginBottom: '4px' } }, __('Used on this page only', 'intercargo-vite')),
          createElement('span', { style: { color: '#757575', fontSize: '12px' } }, context.postTitle || __('Current page', 'intercargo-vite'))
        ) : null,
        createElement(TextControl, {
          label: __('Save as shared', 'intercargo-vite'),
          help: __('Example: Services — Main or Services — Air Freight', 'intercargo-vite'),
          value: newTitle,
          onChange: setNewTitle
        }),
        createElement(Button, { variant: 'primary', onClick: saveAsShared, disabled: !newTitle.trim() || busy }, busy ? __('Saving…', 'intercargo-vite') : __('Save as shared', 'intercargo-vite')),
        items.length ? createElement(Fragment, null,
          createElement('hr', { style: { margin: '20px 0' } }),
          createElement(SelectControl, { label: __('Use existing shared section', 'intercargo-vite'), value: chosen, options: options, onChange: setChosen }),
          createElement(Button, { variant: 'secondary', onClick: useExistingShared, disabled: !chosen || busy }, __('Use shared section', 'intercargo-vite'))
        ) : null,
        error ? createElement(Notice, { status: 'error', isDismissible: false }, error) : null
      );
    }

    return createElement(Fragment, null, toolbar, createElement(InspectorControls, null, panelContent));
  }

  // Keep Reuse & sync visible even when the editor selects a nested content block
  // (for example Hero background image, a heading, a service-card child, or a form).
  // Gutenberg's sidebar normally follows the exact selected block, which made the
  // section-level sharing controls appear to vanish unless the outer section wrapper
  // was selected. Resolve the owning top-level section and proxy its identity into the
  // same SharedPanel instead. This changes editor UX only; saved block structure is not
  // mutated until the user explicitly chooses a sharing action.
  function SharedControlsForSelection(props) {
    var owner = useSelect(function (select) {
      if (!props.isSelected) return null;
      var store = select('core/block-editor');
      if (!store) return null;

      // A selected core/block wrapper already owns the shared identity directly.
      var selected = store.getBlock(props.clientId);
      if (selected && selected.name === 'core/block' && Number(selected.attributes && selected.attributes.ref) > 0) {
        return selected;
      }

      // Otherwise walk from the selected block through its ancestors and find the
      // top-level Intercargo section that owns this nested block.
      var ids = [props.clientId].concat(store.getBlockParents(props.clientId, true) || []);
      for (var i = 0; i < ids.length; i += 1) {
        var candidate = store.getBlock(ids[i]);
        if (candidate && sectionNames.indexOf(candidate.name) !== -1) return candidate;
      }
      return null;
    }, [props.clientId, props.isSelected]);

    if (!owner) return null;

    return createElement(SharedPanel, {
      name: owner.name,
      clientId: owner.clientId,
      attributes: owner.attributes || {},
      setAttributes: function (next) {
        dispatch('core/block-editor').updateBlockAttributes(owner.clientId, next || {});
      }
    });
  }

  wp.hooks.addFilter('editor.BlockEdit', 'intercargo/shared-section-controls', function (BlockEdit) {
    return function IntercargoSharedSectionAwareEdit(props) {
      return createElement(Fragment, null,
        createElement(BlockEdit, props),
        props.isSelected ? createElement(SharedControlsForSelection, props) : null
      );
    };
  });
})(window.wp, window);
