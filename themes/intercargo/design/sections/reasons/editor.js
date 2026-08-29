(function (wp) {
  'use strict';
  if (!wp || !wp.blocks || !wp.blockEditor || !wp.element || !wp.data || !wp.components) return;

  var name = 'intercargo/reasons';
  if (wp.blocks.getBlockType && wp.blocks.getBlockType(name)) return;

  var ce = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var useEffect = wp.element.useEffect;
  var useBlockProps = wp.blockEditor.useBlockProps;
  var useInnerBlocksProps = wp.blockEditor.useInnerBlocksProps;
  var InnerBlocks = wp.blockEditor.InnerBlocks;
  var InspectorControls = wp.blockEditor.InspectorControls;
  var SelectControl = wp.components.SelectControl;
  var PanelBody = wp.components.PanelBody;
  var useSelect = wp.data.useSelect;
  var useDispatch = wp.data.useDispatch;
  var createBlock = wp.blocks.createBlock;

  var fallbackRows = [
    { title: 'The bill matches the quote.', copy: "This industry's worst habit is the fee stack: cartage fee, dehire fee, invoice after invoice. We price all-in and put it in writing before your freight moves." },
    { title: 'You never chase us.', copy: 'You hear from us before you have to ask. Milestone updates on every shipment, and a person who picks up the phone.' },
    { title: 'One team owns it.', copy: 'Forwarding, customs, cartage and warehousing under one roof. Nothing gets handballed to a third party you have never met.' }
  ];

  function own(object, key) {
    return Object.prototype.hasOwnProperty.call(object || {}, key);
  }

  function legacyData(attributes) {
    return attributes && attributes.data && typeof attributes.data === 'object' ? attributes.data : {};
  }

  function legacyText(attributes, nativeKey, legacyKey, fallback) {
    if (own(attributes, nativeKey) && attributes[nativeKey] != null && String(attributes[nativeKey]) !== '') return String(attributes[nativeKey]);
    var data = legacyData(attributes);
    if (own(data, legacyKey) && data[legacyKey] != null && String(data[legacyKey]) !== '') return String(data[legacyKey]);
    return fallback;
  }

  function normalizeRow(row) {
    return {
      title: row && row.title ? String(row.title) : '',
      copy: row && row.copy ? String(row.copy) : ''
    };
  }

  function legacyRows(attributes) {
    if (Array.isArray(attributes.reasons) && attributes.reasons.length) return attributes.reasons.map(normalizeRow);
    var data = legacyData(attributes);
    if (Array.isArray(data.reasons) && data.reasons.length) return data.reasons.map(normalizeRow);

    var count = Number(data.reasons) || 0;
    if (!count) {
      Object.keys(data).forEach(function (key) {
        var match = key.match(/^reasons_(\d+)_/);
        if (match) count = Math.max(count, Number(match[1]) + 1);
      });
    }
    var rows = [];
    for (var index = 0; index < count; index += 1) {
      var row = normalizeRow({
        title: data['reasons_' + index + '_title'] || '',
        copy: data['reasons_' + index + '_copy'] || ''
      });
      if (row.title || row.copy) rows.push(row);
    }
    return rows.length ? rows : fallbackRows.map(normalizeRow);
  }

  function columnBlock(row, index) {
    return createBlock('intercargo/content-column', {
      metadata: { name: 'Column ' + (index + 1) + (row.title ? ' — ' + row.title : '') }
    }, [
      createBlock('core/heading', {
        level: 3,
        className: 'content-column-title',
        placeholder: 'Column title',
        content: row.title || '',
        metadata: { name: 'Column title' }
      }),
      createBlock('core/paragraph', {
        className: 'content-column-copy',
        placeholder: 'Column description',
        content: row.copy || '',
        metadata: { name: 'Column description' }
      })
    ]);
  }

  function initialTree(attributes) {
    var heading = legacyText(attributes, 'title', 'title', 'Why importers switch.');
    var intro = legacyText(attributes, 'intro', 'intro', '');
    var rows = legacyRows(attributes);
    return [
      createBlock('core/group', {
        className: 'container content-columns-content content-columns-layout',
        templateLock: 'all',
        metadata: { name: 'Content Columns content' }
      }, [
        createBlock('core/heading', {
          level: 2,
          className: 'section-title',
          placeholder: 'Section title',
          content: heading,
          metadata: { name: 'Section title' }
        }),
        createBlock('core/paragraph', {
          className: 'content-columns-intro',
          placeholder: 'Optional introduction…',
          content: intro,
          metadata: { name: 'Section introduction (optional)' }
        }),
        createBlock('core/group', {
          className: 'three-cols content-columns-grid',
          templateLock: false,
          allowedBlocks: ['intercargo/content-column'],
          metadata: { name: 'Columns — add, duplicate, delete or reorder' }
        }, rows.map(columnBlock))
      ])
    ];
  }


  function classTokens(block) {
    var className = block && block.attributes ? String(block.attributes.className || '') : '';
    return className.trim().split(/\s+/).filter(Boolean);
  }

  function findCollection(block) {
    if (!block) return null;
    if (classTokens(block).indexOf('content-columns-grid') !== -1) return block;
    var children = Array.isArray(block.innerBlocks) ? block.innerBlocks : [];
    for (var index = 0; index < children.length; index += 1) {
      var found = findCollection(children[index]);
      if (found) return found;
    }
    return null;
  }

  function findContentGroup(block) {
    if (!block) return null;
    var tokens = classTokens(block);
    if (tokens.indexOf('content-columns-content') !== -1 && tokens.indexOf('content-columns-layout') !== -1) return block;
    var children = Array.isArray(block.innerBlocks) ? block.innerBlocks : [];
    for (var index = 0; index < children.length; index += 1) {
      var found = findContentGroup(children[index]);
      if (found) return found;
    }
    return null;
  }

  function themeValue(attributes) {
    var value = attributes && attributes.theme ? String(attributes.theme) : 'homepage';
    return ['homepage', 'service-paper', 'service-white', 'about-paper'].indexOf(value) !== -1 ? value : 'homepage';
  }

  wp.blocks.registerBlockType(name, {
    edit: function Edit(props) {
      var attributes = props.attributes || {};
      var theme = themeValue(attributes);
      var block = useSelect(function (select) {
        var store = select('core/block-editor');
        return store && store.getBlock(props.clientId);
      }, [props.clientId]);
      var blockEditorDispatch = useDispatch('core/block-editor');
      var replaceInnerBlocks = blockEditorDispatch.replaceInnerBlocks;
      var updateBlockAttributes = blockEditorDispatch.updateBlockAttributes;
      var innerCount = block && Array.isArray(block.innerBlocks) ? block.innerBlocks.length : 0;

      // Pre-4.11.2 Content Columns stored title/rows as block attributes. Hydrate
      // those values into the new native child-block model once, in place, and let
      // normal Gutenberg serialization persist the new tree on the next save.
      useEffect(function () {
        if (!block || innerCount !== 0) return;
        replaceInnerBlocks(props.clientId, initialTree(attributes), false);
      }, [props.clientId, innerCount]);

      var contentGroup = findContentGroup(block);
      var contentGroupId = contentGroup ? contentGroup.clientId : '';
      var contentGroupClassName = contentGroup && contentGroup.attributes ? String(contentGroup.attributes.className || '') : '';
      useEffect(function () {
        if (!contentGroupId || !/(^|\s)section-head-gap(\s|$)/.test(contentGroupClassName)) return;
        var cleaned = contentGroupClassName.split(/\s+/).filter(function (token) {
          return token && token !== 'section-head-gap';
        }).join(' ');
        updateBlockAttributes(contentGroupId, { className: cleaned });
      }, [contentGroupId, contentGroupClassName]);

      var collection = findCollection(block);
      var collectionId = collection ? collection.clientId : '';
      var collectionLock = collection && collection.attributes ? collection.attributes.templateLock : null;
      var collectionAllowed = collection && collection.attributes && Array.isArray(collection.attributes.allowedBlocks)
        ? collection.attributes.allowedBlocks.join('|')
        : '';
      useEffect(function () {
        if (!collectionId) return;
        var next = {};
        if (collectionLock !== false) next.templateLock = false;
        if (collectionAllowed !== 'intercargo/content-column') next.allowedBlocks = ['intercargo/content-column'];
        if (Object.keys(next).length) updateBlockAttributes(collectionId, next);
      }, [collectionId, collectionLock, collectionAllowed]);

      var blockProps = useBlockProps({
        className: 'why-section content-columns section-pad content-columns--' + theme
      });
      var innerProps = useInnerBlocksProps(blockProps, {
        template: [],
        templateLock: 'all',
        renderAppender: false
      });

      var inspector = ce(InspectorControls, null,
        ce(PanelBody, { title: 'Section style', initialOpen: true },
          ce(SelectControl, {
            label: 'Theme',
            value: theme,
            options: [
              { label: 'Homepage', value: 'homepage' },
              { label: 'Service — Paper', value: 'service-paper' },
              { label: 'Service — White', value: 'service-white' },
              { label: 'About — Paper', value: 'about-paper' }
            ],
            onChange: function (value) { props.setAttributes({ theme: value }); }
          })
        )
      );

      return ce(Fragment, null, inspector, ce('section', innerProps));
    },
    save: function Save() {
      return ce(InnerBlocks.Content);
    }
  });
})(window.wp);
