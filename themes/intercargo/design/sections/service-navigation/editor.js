(function (wp, window) {
  'use strict';
  if (!wp || !wp.blocks || !wp.blockEditor || !wp.components || !wp.data || !wp.element) return;

  var name = 'intercargo/service-navigation';
  if (wp.blocks.getBlockType && wp.blocks.getBlockType(name)) return;

  var config = window.intercargoServiceNavigationEditorConfig || {};
  var eligible = Array.isArray(config.eligible) ? config.eligible : [];
  var anchors = config.anchors && typeof config.anchors === 'object' ? config.anchors : {};
  var ce = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var useEffect = wp.element.useEffect;
  var useState = wp.element.useState;
  var useBlockProps = wp.blockEditor.useBlockProps;
  var RichText = wp.blockEditor.RichText;
  var InspectorControls = wp.blockEditor.InspectorControls;
  var useSelect = wp.data.useSelect;
  var useDispatch = wp.data.useDispatch;
  var PanelBody = wp.components.PanelBody;
  var TextControl = wp.components.TextControl;

  function clean(value) {
    var holder = document.createElement('div');
    holder.innerHTML = value == null ? '' : String(value);
    return (holder.textContent || holder.innerText || '').replace(/\s+/g, ' ').trim();
  }

  function sanitizeId(value, fallback) {
    var source = clean(value || '').replace(/^#+/, '') || fallback || '';
    var result = String(source).replace(/[^A-Za-z0-9_-]/g, '');
    return result || String(fallback || '').replace(/[^A-Za-z0-9_-]/g, '');
  }

  function firstHeading(block) {
    if (!block) return '';
    if (block.name === 'core/heading') {
      var content = block.attributes && block.attributes.content;
      var label = clean(content || '');
      if (label) return label;
    }
    var children = Array.isArray(block.innerBlocks) ? block.innerBlocks : [];
    for (var i = 0; i < children.length; i += 1) {
      var nested = firstHeading(children[i]);
      if (nested) return nested;
    }
    return '';
  }

  function defaultSectionLabel(block) {
    var attrs = (block && block.attributes) || {};
    var direct = clean(attrs.title || attrs.heading || '');
    if (direct) return direct;
    var heading = firstHeading(block);
    if (heading) return heading;
    var type = wp.blocks.getBlockType && wp.blocks.getBlockType(block.name);
    return type && type.title ? clean(type.title) : '';
  }

  function sectionLabel(block) {
    var attrs = (block && block.attributes) || {};
    var override = clean(attrs.serviceNavTitle || '');
    return override || defaultSectionLabel(block);
  }

  function generatedKey(clientId, anchor) {
    var compact = String(clientId || '').replace(/[^A-Za-z0-9]/g, '').toLowerCase();
    if (compact) return 'sn-' + compact.slice(0, 20);
    return 'sn-' + sanitizeId(anchor || 'section', 'section');
  }

  function expandBlocks(blocks, core) {
    var expanded = [];
    (blocks || []).forEach(function (block) {
      if (!block) return;
      if (block.name === 'core/block' && block.attributes && block.attributes.ref && core && core.getEntityRecord) {
        var record = core.getEntityRecord('postType', 'wp_block', Number(block.attributes.ref));
        var content = record && record.content;
        var raw = content && typeof content === 'object' ? (content.raw || content.rendered || '') : (content || '');
        if (raw && wp.blocks.parse) {
          wp.blocks.parse(String(raw)).forEach(function (parsed) {
            if (!parsed) return;
            parsed.__intercargoSyncedRef = Number(block.attributes.ref);
            expanded.push(parsed);
          });
          return;
        }
      }
      expanded.push(block);
    });
    return expanded;
  }

  /**
   * Discover every eligible section after this navigation instance.
   * Hidden sections remain in the manager so they can be re-enabled.
   */
  function collect(blocks, clientId, core) {
    var after = false;
    var anchorCounts = {};
    var keyCounts = {};
    var items = [];

    expandBlocks(blocks, core).forEach(function (block) {
      if (!block) return;
      if (block.clientId === clientId) {
        after = true;
        return;
      }
      if (block.name === name) return;
      if (!String(block.name || '').startsWith('intercargo/')) return;

      var fallback = anchors[block.name] || String(block.name).replace('intercargo/', '');
      var base = sanitizeId(block.attributes && block.attributes.sectionAnchor, fallback);
      if (!base) return;
      anchorCounts[base] = (anchorCounts[base] || 0) + 1;
      var runtimeAnchor = anchorCounts[base] === 1 ? base : base + '-' + anchorCounts[base];

      if (!after || eligible.indexOf(block.name) === -1) return;

      var attrs = block.attributes || {};
      var requestedKey = sanitizeId(attrs.serviceNavKey || '', '');
      var keyBase = requestedKey || generatedKey(block.clientId, runtimeAnchor);
      keyCounts[keyBase] = (keyCounts[keyBase] || 0) + 1;
      var key = keyCounts[keyBase] === 1 ? keyBase : generatedKey(block.clientId, runtimeAnchor);
      if (keyCounts[keyBase] > 1 && key === keyBase) key += '-' + keyCounts[keyBase];

      var defaultLabel = defaultSectionLabel(block);
      var effectiveLabel = sectionLabel(block);
      if (!effectiveLabel) effectiveLabel = defaultLabel || runtimeAnchor;

      items.push({
        key: key,
        clientId: block.clientId || '',
        blockName: block.name,
        label: effectiveLabel,
        defaultLabel: defaultLabel,
        titleOverride: clean(attrs.serviceNavTitle || ''),
        hidden: attrs.serviceNavHidden === true,
        anchor: runtimeAnchor,
        needsKey: !requestedKey || requestedKey !== key,
        syncedRef: block.__intercargoSyncedRef || 0,
        editable: !!block.clientId
      });
    });

    return items;
  }

  function normalizedOrder(items, order) {
    var keys = items.map(function (item) { return item.key; });
    var valid = {};
    keys.forEach(function (key) { valid[key] = true; });
    var result = [];
    (Array.isArray(order) ? order : []).forEach(function (raw) {
      var key = sanitizeId(raw, '');
      if (key && valid[key] && result.indexOf(key) === -1) result.push(key);
    });
    keys.forEach(function (key) {
      if (result.indexOf(key) === -1) result.push(key);
    });
    return result;
  }

  function orderItems(items, order) {
    var byKey = {};
    items.forEach(function (item) { byKey[item.key] = item; });
    return normalizedOrder(items, order).map(function (key) { return byKey[key]; }).filter(Boolean);
  }

  function sameArray(a, b) {
    if (!Array.isArray(a) || !Array.isArray(b) || a.length !== b.length) return false;
    for (var i = 0; i < a.length; i += 1) if (a[i] !== b[i]) return false;
    return true;
  }

  function moveKey(order, movingKey, targetKey) {
    var next = order.slice();
    var from = next.indexOf(movingKey);
    var target = next.indexOf(targetKey);
    if (from === -1 || target === -1 || from === target) return next;
    next.splice(from, 1);
    target = next.indexOf(targetKey);
    next.splice(target, 0, movingKey);
    return next;
  }

  wp.blocks.registerBlockType(name, {
    edit: function Edit(props) {
      var attrs = props.attributes || {};
      var set = props.setAttributes;
      var updateBlockAttributes = useDispatch('core/block-editor').updateBlockAttributes;
      var stateDrag = useState('');
      var dragKey = stateDrag[0];
      var setDragKey = stateDrag[1];
      var stateOver = useState('');
      var overKey = stateOver[0];
      var setOverKey = stateOver[1];

      var items = useSelect(function (select) {
        var store = select('core/block-editor');
        var core = select('core');
        return collect(store && store.getBlocks ? store.getBlocks() : [], props.clientId, core);
      }, [props.clientId]);

      var naturalSignature = items.map(function (item) {
        return [item.clientId, item.key, item.needsKey ? '1' : '0'].join(':');
      }).join('|');

      // Give every local section instance a stable page-local key. Gutenberg block
      // duplication copies attributes, so duplicates are also re-keyed here.
      useEffect(function () {
        items.forEach(function (item) {
          if (item.editable && item.needsKey && item.clientId && updateBlockAttributes) {
            updateBlockAttributes(item.clientId, { serviceNavKey: item.key });
          }
        });
      }, [naturalSignature]);

      var order = normalizedOrder(items, attrs.tabOrder || []);
      var orderSignature = order.join('|');
      useEffect(function () {
        if (!sameArray(order, attrs.tabOrder || [])) set({ tabOrder: order });
      }, [orderSignature]);

      var orderedItems = orderItems(items, order);
      var visibleItems = orderedItems.filter(function (item) { return !item.hidden; });

      function updateSection(item, patch) {
        if (!item || !item.editable || !item.clientId || !updateBlockAttributes) return;
        updateBlockAttributes(item.clientId, patch);
      }

      function dropOn(targetKey, event) {
        if (event) event.preventDefault();
        if (!dragKey || dragKey === targetKey) {
          setDragKey('');
          setOverKey('');
          return;
        }
        set({ tabOrder: moveKey(order, dragKey, targetKey) });
        setDragKey('');
        setOverKey('');
      }

      function tabManagerRow(item, index) {
        var rowClass = 'intercargo-service-nav-manager__row' +
          (item.hidden ? ' is-hidden' : '') +
          (overKey === item.key && dragKey && dragKey !== item.key ? ' is-drop-target' : '');

        return ce('div', {
          key: item.key,
          className: rowClass,
          onDragOver: function (event) {
            if (!dragKey || dragKey === item.key) return;
            event.preventDefault();
            if (event.dataTransfer) event.dataTransfer.dropEffect = 'move';
            setOverKey(item.key);
          },
          onDragLeave: function () {
            if (overKey === item.key) setOverKey('');
          },
          onDrop: function (event) { dropOn(item.key, event); }
        },
          ce('div', { className: 'intercargo-service-nav-manager__line' },
            ce('span', {
              className: 'intercargo-service-nav-manager__drag',
              draggable: true,
              role: 'button',
              tabIndex: 0,
              title: 'Drag to reorder tab',
              'aria-label': 'Drag ' + item.label + ' to reorder',
              onDragStart: function (event) {
                setDragKey(item.key);
                if (event.dataTransfer) {
                  event.dataTransfer.effectAllowed = 'move';
                  try { event.dataTransfer.setData('text/plain', item.key); } catch (error) {}
                }
              },
              onDragEnd: function () {
                setDragKey('');
                setOverKey('');
              }
            }, '⋮⋮'),
            ce('input', {
              className: 'intercargo-service-nav-manager__title-input',
              type: 'text',
              value: item.titleOverride || item.defaultLabel || item.label,
              disabled: !item.editable,
              'aria-label': 'Tab title for ' + item.label,
              title: item.editable ? 'Edit tab title' : 'This title comes from a read-only synced section',
              onChange: function (event) {
                updateSection(item, { serviceNavTitle: event.target.value });
              }
            }),
            ce('label', {
              className: 'intercargo-service-nav-manager__checkbox',
              title: item.hidden ? 'Show tab' : 'Hide tab'
            },
              ce('input', {
                type: 'checkbox',
                checked: !item.hidden,
                disabled: !item.editable,
                'aria-label': 'Show ' + item.label + ' in Service Navigation',
                onChange: function (event) { updateSection(item, { serviceNavHidden: !event.target.checked }); }
              })
            )
          )
        );
      }

      var bp = useBlockProps({ className: 'service-secnav intercargo-native intercargo-native-service-navigation' });
      var inspector = ce(InspectorControls, null,
        ce(PanelBody, { title: 'Tabs (' + items.length + ')', initialOpen: true },
          ce('p', { className: 'intercargo-service-nav-manager__help' }, 'Tabs follow the sections below. Drag to reorder, edit the title inline, or untick to hide. Targets are generated automatically.'),
          items.length ? ce('div', { className: 'intercargo-service-nav-manager' }, orderedItems.map(tabManagerRow))
            : ce('p', { className: 'intercargo-service-nav-manager__empty' }, 'Add page sections below this block. They will appear here automatically.')
        ),
        ce(PanelBody, { title: 'Actions', initialOpen: false },
          ce(TextControl, {
            label: 'Primary action URL',
            value: attrs.primaryCtaUrl || '#enquiry',
            onChange: function (value) { set({ primaryCtaUrl: value }); }
          }),
          ce(TextControl, {
            label: 'Secondary action URL',
            value: attrs.secondaryCtaUrl || '#guide',
            onChange: function (value) { set({ secondaryCtaUrl: value }); }
          })
        ),
        ce(PanelBody, { title: 'Accessibility', initialOpen: false },
          ce(TextControl, {
            label: 'Navigation label',
            value: attrs.ariaLabel || 'Service page navigation',
            onChange: function (value) { set({ ariaLabel: value }); }
          })
        )
      );

      return ce(Fragment, null, inspector,
        ce('section', bp,
          ce('div', { className: 'service-secnav-inner container' },
            ce('nav', { className: 'service-secnav-links', 'aria-label': 'On this page' },
              visibleItems.length ? visibleItems.map(function (item, index) {
                return ce('a', {
                  key: item.key,
                  className: 'service-secnav-link' + (index === 0 ? ' is-active' : ''),
                  href: '#' + item.anchor,
                  onClick: function (event) { event.preventDefault(); }
                }, item.label);
              }) : ce('span', { className: 'service-secnav-empty' }, 'No visible tabs. Add sections below or enable them in the Tabs panel.')
            ),
            ce('div', { className: 'service-secnav-actions' },
              ce('p', { className: 'service-secnav-action service-secnav-action--primary' },
                ce('a', { href: attrs.primaryCtaUrl || '#enquiry', onClick: function (event) { event.preventDefault(); } },
                  ce(RichText, {
                    tagName: 'span',
                    value: attrs.primaryCtaLabel || 'Talk to our team',
                    allowedFormats: [],
                    onChange: function (value) { set({ primaryCtaLabel: value }); }
                  })
                )
              ),
              ce('p', { className: 'service-secnav-action service-secnav-action--secondary' },
                ce('a', { href: attrs.secondaryCtaUrl || '#guide', onClick: function (event) { event.preventDefault(); } },
                  ce(RichText, {
                    tagName: 'span',
                    value: attrs.secondaryCtaLabel || 'Get the guide',
                    allowedFormats: [],
                    onChange: function (value) { set({ secondaryCtaLabel: value }); }
                  })
                )
              )
            )
          )
        )
      );
    },
    save: function Save() { return null; }
  });
})(window.wp, window);
