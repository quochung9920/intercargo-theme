(function (wp, window) {
  'use strict';
  if (!wp || !wp.blocks || !wp.blockEditor || !wp.element) return;

  var name = 'intercargo/enquiry';
  if (wp.blocks.getBlockType && wp.blocks.getBlockType(name)) return;

  var ce = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var useEffect = wp.element.useEffect;
  var useState = wp.element.useState;
  var RichText = wp.blockEditor.RichText;
  var useBlockProps = wp.blockEditor.useBlockProps;
  var InspectorControls = wp.blockEditor.InspectorControls;
  var MediaUpload = wp.blockEditor.MediaUpload;
  var MediaUploadCheck = wp.blockEditor.MediaUploadCheck;
  var PanelBody = wp.components.PanelBody;
  var TextControl = wp.components.TextControl;
  var SelectControl = wp.components.SelectControl;
  var Button = wp.components.Button;
  var SSR = wp.serverSideRender;
  var apiFetch = wp.apiFetch;
  var useSelect = wp.data.useSelect;

  var fallbackSteps = [
    { title: 'We reply the same business day', copy: 'A senior person, across your enquiry.' },
    { title: 'We scope it in one conversation', copy: 'Route, volume, timing and what you have been paying, if you want it beaten.' },
    { title: 'You get one written price', copy: 'Every fee included. Take it or keep it as a benchmark, no pressure either way.' }
  ];

  function own(object, key) {
    return Object.prototype.hasOwnProperty.call(object || {}, key);
  }

  function legacyData(attributes) {
    return attributes && attributes.data && typeof attributes.data === 'object' ? attributes.data : {};
  }

  function legacyText(attributes, nativeKey, legacyKey, fallback) {
    if (own(attributes, nativeKey)) return attributes[nativeKey] == null ? '' : String(attributes[nativeKey]);
    var data = legacyData(attributes);
    return own(data, legacyKey) && data[legacyKey] != null ? String(data[legacyKey]) : fallback;
  }

  function normalizeStep(row) {
    return {
      title: row && row.title ? String(row.title) : '',
      copy: row && row.copy ? String(row.copy) : ''
    };
  }

  function stepsFromAttributes(attributes) {
    if (own(attributes, 'steps') && Array.isArray(attributes.steps)) {
      return attributes.steps.map(normalizeStep);
    }
    var data = legacyData(attributes);
    if (Array.isArray(data.steps) && data.steps.length) return data.steps.map(normalizeStep);
    var count = Number(data.steps) || 0;
    if (!count) {
      Object.keys(data).forEach(function (key) {
        var match = key.match(/^steps_(\d+)_/);
        if (match) count = Math.max(count, Number(match[1]) + 1);
      });
    }
    var output = [];
    for (var index = 0; index < count; index += 1) {
      var row = {
        title: data['steps_' + index + '_title'] || '',
        copy: data['steps_' + index + '_copy'] || ''
      };
      if (row.title || row.copy) output.push(normalizeStep(row));
    }
    return output.length ? output : fallbackSteps.map(normalizeStep);
  }

  function mediaId(attributes, nativeKey, legacyKey) {
    var raw = own(attributes, nativeKey) ? attributes[nativeKey] : legacyData(attributes)[legacyKey];
    var value = Number(raw);
    return Number.isInteger(value) && value > 0 ? value : 0;
  }

  function mediaUrl(media) {
    if (media && media.source_url) return String(media.source_url);
    if (media && media.guid && media.guid.rendered) return String(media.guid.rendered);
    return '';
  }

  wp.blocks.registerBlockType(name, {
    edit: function Edit(props) {
      var attributes = props.attributes || {};
      var setAttributes = props.setAttributes;
      var steps = stepsFromAttributes(attributes);
      var provider = attributes.provider || '';
      var formId = String(attributes.formId || '');
      var legacyShortcode = legacyText(attributes, 'legacyShortcode', 'form_shortcode', '');
      var dragState = useState(-1);
      var dragIndex = dragState[0];
      var setDragIndex = dragState[1];
      var providerState = useState([]);
      var providers = providerState[0];
      var setProviders = providerState[1];

      useEffect(function () {
        if (!apiFetch) return;
        apiFetch({ path: (window.intercargoFormConfig && window.intercargoFormConfig.restPath) || '/intercargo/v1/forms' })
          .then(function (response) {
            setProviders(response && Array.isArray(response.providers) ? response.providers : []);
          })
          .catch(function () { setProviders([]); });
      }, []);

      var currentProvider = providers.find(function (item) { return item.id === provider; });
      var providerOptions = [{ label: 'Select form provider', value: '' }].concat(providers.map(function (item) {
        return { label: item.title, value: item.id };
      }));
      var formOptions = [{ label: 'Select form', value: '' }].concat(currentProvider && Array.isArray(currentProvider.forms)
        ? currentProvider.forms.map(function (form) { return { label: form.title, value: String(form.id) }; })
        : []);

      var backgroundId = mediaId(attributes, 'backgroundImageId', 'background_image');
      var media = useSelect ? useSelect(function (select) {
        var core = select('core');
        return backgroundId && core ? core.getMedia(backgroundId) : null;
      }, [backgroundId]) : null;
      var background = own(attributes, 'backgroundImageUrl') && attributes.backgroundImageUrl
        ? String(attributes.backgroundImageUrl)
        : mediaUrl(media);

      function commitSteps(next) {
        setAttributes({ steps: next.map(normalizeStep) });
      }

      function updateStep(index, key, value) {
        var next = steps.map(normalizeStep);
        if (!next[index]) return;
        next[index][key] = value;
        commitSteps(next);
      }

      function addStep(afterIndex) {
        var next = steps.map(normalizeStep);
        var row = { title: 'New step', copy: 'Describe this step.' };
        var insertAt = Number.isInteger(afterIndex) ? afterIndex + 1 : next.length;
        next.splice(insertAt, 0, row);
        commitSteps(next);
      }

      function duplicateStep(index) {
        var next = steps.map(normalizeStep);
        if (!next[index]) return;
        next.splice(index + 1, 0, normalizeStep(next[index]));
        commitSteps(next);
      }

      function removeStep(index) {
        var next = steps.map(normalizeStep);
        next.splice(index, 1);
        commitSteps(next);
      }

      function moveStep(from, to) {
        var next = steps.map(normalizeStep);
        if (from < 0 || to < 0 || from >= next.length || to >= next.length || from === to) return;
        var moved = next.splice(from, 1)[0];
        next.splice(to, 0, moved);
        commitSteps(next);
      }

      function setBackground(mediaItem) {
        var id = Number(mediaItem && mediaItem.id);
        if (!Number.isInteger(id) || id <= 0 || !mediaItem.url) return;
        setAttributes({ backgroundImageId: id, backgroundImageUrl: String(mediaItem.url) });
      }

      var title = legacyText(attributes, 'title', 'title', 'Talk to the team.');
      var lead = legacyText(attributes, 'lead', 'lead', 'Tell us what you are importing and how often. A senior person replies the same business day, with a real answer, not a ticket number.');
      var contactNote = legacyText(attributes, 'contactNote', 'contact_note', '');
      var formLabel = legacyText(attributes, 'formLabel', 'form_label', 'Enquiry form');
      var formNote = legacyText(attributes, 'formNote', 'form_note', 'A person replies. No quote-bot, no ticket queue.');
      var anchor = legacyText(attributes, 'sectionAnchor', 'section_anchor', '');
      if (contactNote === 'Phone and direct email publish at launch.') contactNote = '';

      var inspector = ce(InspectorControls, null,
        ce(PanelBody, { title: 'Form', initialOpen: true },
          ce(SelectControl, {
            label: 'Provider', value: provider, options: providerOptions,
            onChange: function (value) { setAttributes({ provider: value, formId: '' }); }
          }),
          provider ? ce(SelectControl, {
            label: 'Form', value: formId, options: formOptions,
            onChange: function (value) { setAttributes({ formId: String(value) }); }
          }) : null,
          ce(TextControl, {
            label: 'Accessible label', value: formLabel,
            onChange: function (value) { setAttributes({ formLabel: value }); }
          })
        ),
        ce(PanelBody, { title: 'Section settings', initialOpen: false },
          ce(TextControl, {
            label: 'Section anchor', value: anchor,
            onChange: function (value) { setAttributes({ sectionAnchor: value }); }
          }),
          MediaUpload && MediaUploadCheck ? ce(MediaUploadCheck, null,
            ce(MediaUpload, {
              allowedTypes: ['image'], value: backgroundId || undefined, onSelect: setBackground,
              render: function (control) {
                return ce(Fragment, null,
                  ce(Button, { variant: 'secondary', onClick: control.open }, background ? 'Replace section background' : 'Choose section background'),
                  background ? ce(Button, {
                    variant: 'tertiary', isDestructive: true,
                    onClick: function () { setAttributes({ backgroundImageId: 0, backgroundImageUrl: '' }); }
                  }, 'Remove background') : null
                );
              }
            })
          ) : null
        )
      );

      var blockProps = useBlockProps({
        className: 'enquiry-section section-pad',
        style: background ? { backgroundImage: 'url("' + background.replace(/"/g, '%22') + '")' } : undefined
      });

      var formPreview = ce('div', {
        className: 'intercargo-form-shell intercargo-form-shell--' + (provider === 'gravity' ? 'gravity' : 'contact-form-7'),
        'aria-label': formLabel
      },
        SSR ? ce(SSR, {
          block: 'intercargo/form',
          attributes: { provider: provider, formId: formId, legacyShortcode: legacyShortcode, variant: 'bare' },
          skipBlockSupportAttributes: true
        }) : null,
        ce(RichText, {
          tagName: 'p', className: 'form-note', value: formNote, allowedFormats: [],
          onChange: function (value) { setAttributes({ formNote: value }); }
        })
      );

      var stepNodes = steps.map(function (step, index) {
        return ce('article', {
          className: 'enquiry-step intercargo-enquiry-step-editor' + (dragIndex === index ? ' is-dragging' : ''),
          key: index,
          onDragOver: function (event) { if (dragIndex >= 0) event.preventDefault(); },
          onDrop: function (event) {
            event.preventDefault();
            if (dragIndex >= 0) moveStep(dragIndex, index);
            setDragIndex(-1);
          }
        },
          ce('div', { className: 'intercargo-enquiry-step-actions' },
            ce('button', {
              type: 'button', className: 'intercargo-enquiry-step-drag', draggable: true,
              title: 'Drag to reorder', 'aria-label': 'Drag step ' + (index + 1) + ' to reorder',
              onDragStart: function (event) {
                setDragIndex(index);
                if (event.dataTransfer) {
                  event.dataTransfer.effectAllowed = 'move';
                  event.dataTransfer.setData('text/plain', String(index));
                }
              },
              onDragEnd: function () { setDragIndex(-1); }
            }, '⋮⋮'),
            ce(Button, { variant: 'tertiary', size: 'compact', onClick: function () { addStep(index); } }, '+'),
            ce(Button, { variant: 'tertiary', size: 'compact', onClick: function () { duplicateStep(index); } }, 'Duplicate'),
            ce(Button, { variant: 'tertiary', size: 'compact', isDestructive: true, onClick: function () { removeStep(index); } }, 'Delete')
          ),
          ce(RichText, {
            tagName: 'h3', value: step.title, allowedFormats: [], placeholder: 'Step title',
            onChange: function (value) { updateStep(index, 'title', value); }
          }),
          ce(RichText, {
            tagName: 'p', value: step.copy, allowedFormats: [], placeholder: 'Step description',
            onChange: function (value) { updateStep(index, 'copy', value); }
          })
        );
      });

      return ce(Fragment, null,
        inspector,
        ce('section', blockProps,
          ce('div', { className: 'container enquiry-layout' },
            ce('div', { className: 'enquiry-copy' },
              ce(RichText, {
                tagName: 'h2', className: 'section-title', value: title, allowedFormats: [],
                onChange: function (value) { setAttributes({ title: value }); }
              }),
              ce(RichText, {
                tagName: 'p', className: 'enquiry-lead', value: lead, allowedFormats: [],
                onChange: function (value) { setAttributes({ lead: value }); }
              }),
              ce('div', { className: 'enquiry-steps intercargo-enquiry-steps-editor' },
                stepNodes,
                ce(Button, { className: 'intercargo-enquiry-add-step', variant: 'secondary', onClick: function () { addStep(); } }, '+ Add step')
              ),
              ce(RichText, {
                tagName: 'p', className: 'enquiry-note enquiry-note--desktop', value: contactNote, allowedFormats: [],
                onChange: function (value) { setAttributes({ contactNote: value }); }
              })
            ),
            formPreview,
            ce(RichText, {
              tagName: 'p', className: 'enquiry-note enquiry-note--mobile', value: contactNote, allowedFormats: [],
              onChange: function (value) { setAttributes({ contactNote: value }); }
            })
          )
        )
      );
    },
    save: function () { return null; }
  });
})(window.wp, window);
