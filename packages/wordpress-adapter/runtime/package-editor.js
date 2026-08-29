(function (wp, window) {
  'use strict';

  if (!wp || !wp.blocks || !wp.blockEditor || !wp.element || !wp.data) return;

  var definitions = window.intercargoPackageDefinitions || {};
  var createElement = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var useBlockProps = wp.blockEditor.useBlockProps;
  var useInnerBlocksProps = wp.blockEditor.useInnerBlocksProps;
  var InnerBlocks = wp.blockEditor.InnerBlocks;
  var BlockControls = wp.blockEditor.BlockControls;
  var InspectorControls = wp.blockEditor.InspectorControls;
  var MediaReplaceFlow = wp.blockEditor.MediaReplaceFlow;
  var LinkControl = wp.blockEditor.__experimentalLinkControl || wp.blockEditor.LinkControl;
  var useSelect = wp.data.useSelect;
  var dispatch = wp.data.dispatch;
  var ToolbarGroup = wp.components && wp.components.ToolbarGroup;
  var ToolbarButton = wp.components && wp.components.ToolbarButton;
  var Popover = wp.components && wp.components.Popover;
  var PanelBody = wp.components && wp.components.PanelBody;
  var TextControl = wp.components && wp.components.TextControl;
  var ToggleControl = wp.components && wp.components.ToggleControl;
  var useState = wp.element.useState;
  var useEffect = wp.element.useEffect;
  var SelectControl = wp.components && wp.components.SelectControl;
  var apiFetch = wp.apiFetch;

  function classTokens(block) {
    var attrs = (block && block.attributes) || {};
    return String(attrs.className || '').trim().split(/\s+/).filter(Boolean);
  }

  function findDescendant(block, className) {
    if (!block) return null;
    var children = Array.isArray(block.innerBlocks) ? block.innerBlocks : [];
    for (var i = 0; i < children.length; i += 1) {
      var child = children[i];
      if (classTokens(child).indexOf(className) !== -1) return child;
      var nested = findDescendant(child, className);
      if (nested) return nested;
    }
    return null;
  }

  function findDescendantByNames(block, names) {
    if (!block) return null;
    var allowed = Array.isArray(names) ? names : [];
    var children = Array.isArray(block.innerBlocks) ? block.innerBlocks : [];
    for (var i = 0; i < children.length; i += 1) {
      var child = children[i];
      if (allowed.indexOf(child.name) !== -1) return child;
      var nested = findDescendantByNames(child, allowed);
      if (nested) return nested;
    }
    return null;
  }

  function findNamedImage(blocks, targetName) {
    for (var i = 0; i < blocks.length; i += 1) {
      var block = blocks[i];
      var metadata = block.attributes && block.attributes.metadata;
      if (block.name === 'core/image' && metadata && metadata.name === targetName) return block;
      var nested = findNamedImage(block.innerBlocks || [], targetName);
      if (nested) return nested;
    }
    return null;
  }

  function registerSection(name, definition) {
    if (wp.blocks.getBlockType && wp.blocks.getBlockType(name)) return;
    var section = definition.section || {};
    var mediaDefinitions = Array.isArray(definition.mediaControls) ? definition.mediaControls : [];
    var collectionDefinitions = Array.isArray(definition.openCollections) ? definition.openCollections : [];
    var formDefinition = definition.formControl && typeof definition.formControl === 'object' ? definition.formControl : null;

    wp.blocks.registerBlockType(name, {
      edit: function Edit(props) {
        var clientId = props.clientId;
        var blockProps = useBlockProps({ className: section.className || '' });
        var innerProps = useInnerBlocksProps(blockProps, {
          template: definition.template || [],
          templateLock: definition.editorTemplateLock || 'contentOnly',
          renderAppender: false
        });
        var sectionState = useSelect(function (select) {
          var store = select('core/block-editor');
          var root = store && store.getBlock(clientId);
          if (!root) return { mediaTargets: [], formBlock: null, collectionBlocks: [] };
          return {
            mediaTargets: mediaDefinitions.map(function (control) {
              return { control: control, block: findNamedImage(root.innerBlocks || [], control.target) };
            }).filter(function (entry) { return !!entry.block; }),
            formBlock: formDefinition ? findDescendantByNames(root, formDefinition.blockNames || ['intercargo/form']) : null,
            collectionBlocks: collectionDefinitions.map(function (collection) {
              return { collection: collection, block: findDescendant(root, collection.className || '') };
            }).filter(function (entry) { return !!entry.block; })
          };
        }, [clientId]);
        var mediaTargets = sectionState.mediaTargets || [];
        var formBlock = sectionState.formBlock || null;
        var collectionBlocks = sectionState.collectionBlocks || [];
        var collectionSignature = collectionBlocks.map(function (entry) {
          var attrs = (entry.block && entry.block.attributes) || {};
          return entry.block.clientId + ':' + String(attrs.templateLock);
        }).join('|');

        /*
         * Collection groups (service cards, location rows, etc.) are intentionally
         * open even when the surrounding section structure is locked. Historical
         * pages may have serialized templateLock="all" before these collections
         * became repeatable. Unlock only the collection container in place; never
         * rebuild or replace its saved child blocks.
         */
        useEffect(function () {
          collectionBlocks.forEach(function (entry) {
            var block = entry.block;
            if (!block || !block.clientId) return;
            var attrs = block.attributes || {};
            var next = {};
            if (attrs.templateLock !== false) next.templateLock = false;
            var collection = entry.collection || {};
            var label = collection.label || '';
            var allowed = collection.allows ? [collection.allows] : [];
            var metadata = attrs.metadata && typeof attrs.metadata === 'object' ? attrs.metadata : {};
            if (label && metadata.name !== label) next.metadata = Object.assign({}, metadata, { name: label });
            if (allowed.length && (!Array.isArray(attrs.allowedBlocks) || attrs.allowedBlocks.join('|') !== allowed.join('|'))) next.allowedBlocks = allowed;
            if (Object.keys(next).length) dispatch('core/block-editor').updateBlockAttributes(block.clientId, next);
          });
        }, [collectionSignature]);
        var providerState = useState([]);
        var providers = providerState[0];
        var setProviders = providerState[1];
        useEffect(function () {
          if (!formDefinition || !apiFetch) return;
          var config = window.intercargoFormConfig || {};
          apiFetch({ path: config.restPath || '/intercargo/v1/forms' }).then(function (response) {
            setProviders(response && Array.isArray(response.providers) ? response.providers : []);
          }).catch(function () { setProviders([]); });
        }, []);

        var toolbar = null;
        if (BlockControls && MediaReplaceFlow && mediaTargets.length) {
          toolbar = createElement(BlockControls, { group: 'block' }, mediaTargets.map(function (entry) {
            var image = entry.block;
            return createElement(MediaReplaceFlow, {
              key: image.clientId,
              mediaId: image.attributes.id,
              mediaURL: image.attributes.url,
              allowedTypes: ['image'],
              accept: 'image/*',
              name: entry.control.label || 'Replace image',
              onSelect: function (media) {
                var id = Number(media && media.id);
                if (!Number.isInteger(id) || id <= 0 || !media.url) return;
                dispatch('core/block-editor').updateBlockAttributes(image.clientId, {
                  id: id,
                  url: media.url,
                  alt: media.alt || '',
                  sizeSlug: 'full',
                  linkDestination: 'none'
                });
              }
            });
          }));
        }

        var inspector = null;
        if (formDefinition && formBlock && InspectorControls && PanelBody && SelectControl) {
          var formAttributes = formBlock.attributes || {};
          var provider = formAttributes.provider || '';
          var currentProvider = providers.find(function (item) { return item.id === provider; });
          var providerOptions = [{ label: 'Select form provider', value: '' }].concat(providers.map(function (item) {
            return { label: item.title, value: item.id };
          }));
          var formOptions = [{ label: 'Select form', value: '' }].concat(currentProvider && Array.isArray(currentProvider.forms) ? currentProvider.forms.map(function (form) {
            return { label: form.title, value: String(form.id) };
          }) : []);
          var controls = [
            createElement(SelectControl, {
              key: 'provider',
              label: 'Provider',
              value: provider,
              options: providerOptions,
              onChange: function (nextProvider) {
                dispatch('core/block-editor').updateBlockAttributes(formBlock.clientId, { provider: nextProvider, formId: '' });
              }
            })
          ];
          if (provider) {
            controls.push(createElement(SelectControl, {
              key: 'form',
              label: 'Form',
              value: String(formAttributes.formId || ''),
              options: formOptions,
              onChange: function (formId) {
                dispatch('core/block-editor').updateBlockAttributes(formBlock.clientId, { formId: String(formId) });
              }
            }));
          }
          if (formDefinition.showAccessibleLabel !== false && TextControl) {
            controls.push(createElement(TextControl, {
              key: 'label',
              label: 'Accessible label',
              value: formAttributes.label || '',
              onChange: function (label) {
                dispatch('core/block-editor').updateBlockAttributes(formBlock.clientId, { label: label });
              }
            }));
          }
          inspector = createElement(InspectorControls, null,
            createElement(PanelBody, { title: formDefinition.title || 'Form', initialOpen: true }, controls)
          );
        }
        return createElement(Fragment, null, toolbar, inspector, createElement('section', innerProps));
      },
      save: function Save() { return createElement(InnerBlocks.Content); }
    });
  }

  function registerItem(name, definition) {
    if (wp.blocks.getBlockType && wp.blocks.getBlockType(name)) return;
    var item = definition.item || {};
    var allowed = ['div', 'article', 'li', 'section'];
    var element = allowed.indexOf(item.element) === -1 ? 'div' : item.element;
    var mediaDefinitions = Array.isArray(definition.mediaControls) ? definition.mediaControls : [];

    wp.blocks.registerBlockType(name, {
      edit: function Edit(props) {
        var attributes = props.attributes || {};
        var setAttributes = props.setAttributes;
        var linkState = useState(false);
        var linkOpen = linkState[0];
        var setLinkOpen = linkState[1];
        var blockProps = useBlockProps({ className: item.className || '' });
        var innerProps = useInnerBlocksProps(blockProps, {
          template: definition.template || [],
          templateLock: 'all',
          renderAppender: false
        });
        var root = useSelect(function (select) {
          var store = select('core/block-editor');
          return store && store.getBlock(props.clientId);
        }, [props.clientId]);

        var controls = [];
        if (item.linkOwner && ToolbarGroup && ToolbarButton) {
          controls.push(createElement(ToolbarGroup, { key: 'link' },
            createElement(ToolbarButton, {
              icon: 'admin-links',
              label: 'Edit whole-card link',
              isPressed: linkOpen,
              onClick: function () { setLinkOpen(!linkOpen); }
            }),
            linkOpen && LinkControl && Popover ? createElement(Popover, {
              placement: 'bottom-start',
              onClose: function () { setLinkOpen(false); }
            }, createElement(LinkControl, {
              value: { url: attributes.url || '', opensInNewTab: attributes.target === '_blank' },
              onChange: function (next) {
                setAttributes({
                  url: next && next.url ? next.url : '',
                  target: next && next.opensInNewTab ? '_blank' : '_self'
                });
              },
              settings: []
            })) : null
          ));
        }
        mediaDefinitions.forEach(function (control, index) {
          var mediaBlock = findDescendant(root, control.className);
          var mediaAttrs = (mediaBlock && mediaBlock.attributes) || {};
          if (!MediaReplaceFlow) return;
          controls.push(createElement(MediaReplaceFlow, {
            key: 'media-' + index,
            mediaId: mediaAttrs.id || 0,
            mediaURL: mediaAttrs.url || '',
            allowedTypes: control.allowedTypes || ['image'],
            name: control.label || 'Replace media',
            onSelect: function (media) {
              if (!mediaBlock || !media) return;
              dispatch('core/block-editor').updateBlockAttributes(mediaBlock.clientId, {
                id: media.id || 0,
                url: media.url || '',
                alt: media.alt || '',
                sizeSlug: 'full',
                linkDestination: 'none'
              });
            }
          }));
        });

        var inspector = null;
        if (item.linkOwner && InspectorControls && PanelBody && TextControl && ToggleControl) {
          inspector = createElement(InspectorControls, null,
            createElement(PanelBody, { title: 'Whole-card link', initialOpen: true },
              createElement(TextControl, {
                label: 'Card destination',
                value: attributes.url || '',
                onChange: function (url) { setAttributes({ url: url }); }
              }),
              createElement(ToggleControl, {
                label: 'Open in a new tab',
                checked: attributes.target === '_blank',
                onChange: function (checked) { setAttributes({ target: checked ? '_blank' : '_self' }); }
              })
            )
          );
        }

        return createElement(Fragment, null,
          controls.length && BlockControls ? createElement(BlockControls, null, controls) : null,
          inspector,
          createElement(element, innerProps)
        );
      },
      save: function Save() { return createElement(InnerBlocks.Content); }
    });
  }

  /**
   * Prevent frontend links from navigating while content is being edited.
   *
   * The block editor may render the canvas either in the wp-admin document or in
   * a same-origin iframe. We bind a capture listener to both forms. Only anchors
   * inside the editable canvas are affected; toolbar, inspector and admin links
   * continue to behave normally. We intentionally do not stop propagation so a
   * click can still select/edit the link-owning block.
   */
  function registerEditorSafeLinks() {
    var boundDocuments = [];

    function isBound(doc) {
      return boundDocuments.indexOf(doc) !== -1;
    }

    function isCanvasLink(doc, anchor) {
      if (!doc || !anchor) return false;
      if (doc !== window.document) return true;
      return !!anchor.closest('.editor-styles-wrapper, .block-editor-block-list__layout, .block-editor-writing-flow');
    }

    function preventNavigation(event) {
      if (!event || event.defaultPrevented) return;
      var target = event.target;
      if (!target || !target.closest) return;
      var anchor = target.closest('a[href]');
      if (!anchor || !isCanvasLink(anchor.ownerDocument, anchor)) return;
      event.preventDefault();
    }

    function bindDocument(doc) {
      if (!doc || isBound(doc)) return;
      boundDocuments.push(doc);
      doc.addEventListener('click', preventNavigation, true);
      doc.addEventListener('auxclick', preventNavigation, true);
    }

    function bindEditorFrames() {
      bindDocument(window.document);
      var frames = window.document.querySelectorAll('iframe[name="editor-canvas"], iframe.block-editor-iframe__container, .block-editor-iframe__container iframe');
      Array.prototype.forEach.call(frames, function (frame) {
        function bindFrameDocument() {
          try { bindDocument(frame.contentDocument); } catch (error) { /* same-origin editor expected */ }
        }
        bindFrameDocument();
        if (!frame.dataset.intercargoSafeLinksBound) {
          frame.dataset.intercargoSafeLinksBound = '1';
          frame.addEventListener('load', bindFrameDocument);
        }
      });
    }

    bindEditorFrames();
    if (window.MutationObserver && window.document.body) {
      var observer = new window.MutationObserver(bindEditorFrames);
      observer.observe(window.document.body, { childList: true, subtree: true });
    }
  }

  function registerMediaBridge() {
    if (!wp.hooks || !BlockControls || !MediaReplaceFlow) return;
    wp.hooks.addFilter('editor.BlockEdit', 'intercargo/package-section-media', function (BlockEdit) {
      return function PackageMediaAwareEdit(props) {
        var context = useSelect(function (select) {
          if (!props.isSelected) return null;
          var store = select('core/block-editor');
          if (!store) return null;
          var parents = store.getBlockParents(props.clientId) || [];
          for (var i = 0; i < parents.length; i += 1) {
            var parent = store.getBlock(parents[i]);
            var definition = parent && definitions[parent.name] && definitions[parent.name].definition;
            var mediaDefs = definition && Array.isArray(definition.mediaControls) ? definition.mediaControls : [];
            if (!parent || !mediaDefs.length) continue;
            var targets = mediaDefs.map(function (control) {
              return { control: control, block: findNamedImage(parent.innerBlocks || [], control.target) };
            }).filter(function (entry) { return !!entry.block; });
            if (targets.length) return targets;
          }
          return null;
        }, [props.clientId, props.isSelected]);
        var toolbar = null;
        if (context && context.length) {
          toolbar = createElement(BlockControls, { group: 'block' }, context.map(function (entry) {
            var image = entry.block;
            return createElement(MediaReplaceFlow, {
              key: image.clientId,
              mediaId: image.attributes.id,
              mediaURL: image.attributes.url,
              allowedTypes: ['image'],
              name: entry.control.label || 'Replace image',
              onSelect: function (media) {
                var id = Number(media && media.id);
                if (!Number.isInteger(id) || id <= 0 || !media.url) return;
                dispatch('core/block-editor').updateBlockAttributes(image.clientId, {
                  id: id, url: media.url, alt: media.alt || '', sizeSlug: 'full', linkDestination: 'none'
                });
              }
            });
          }));
        }
        return createElement(Fragment, null, createElement(BlockEdit, props), toolbar);
      };
    });
  }

  Object.keys(definitions).forEach(function (name) {
    var record = definitions[name] || {};
    if (record.type === 'section') registerSection(name, record.definition || {});
    if (record.type === 'item') registerItem(name, record.definition || {});
  });
  registerMediaBridge();
  registerEditorSafeLinks();
})(window.wp, window);
