(function (wp, window, document) {
  'use strict';

  if (!wp || !wp.blocks || !wp.blockEditor || !wp.element) {
    return;
  }

  var blockName = 'intercargo/statement';
  if (wp.blocks.getBlockType && wp.blocks.getBlockType(blockName)) {
    return;
  }

  var scriptUrl = document.currentScript && document.currentScript.src
    ? new URL(document.currentScript.src, window.location.href)
    : null;
  var fallbackBandImage = '';
  if (scriptUrl) {
    var fallbackUrl = new URL('./assets/statement-band.png', scriptUrl);
    if (scriptUrl.search) {
      fallbackUrl.search = scriptUrl.search;
    }
    fallbackBandImage = fallbackUrl.toString();
  }

  var createElement = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var useBlockProps = wp.blockEditor.useBlockProps;
  var RichText = wp.blockEditor.RichText;
  var BlockControls = wp.blockEditor.BlockControls;
  var InspectorControls = wp.blockEditor.InspectorControls;
  var MediaReplaceFlow = wp.blockEditor.MediaReplaceFlow;
  var MediaUpload = wp.blockEditor.MediaUpload;
  var MediaUploadCheck = wp.blockEditor.MediaUploadCheck;
  var PanelBody = wp.components && wp.components.PanelBody;
  var TextControl = wp.components && wp.components.TextControl;
  var Button = wp.components && wp.components.Button;
  var useSelect = wp.data && wp.data.useSelect;
  var __ = wp.i18n && wp.i18n.__ ? wp.i18n.__ : function (value) { return value; };

  function legacyData(attributes) {
    return attributes && attributes.data && typeof attributes.data === 'object'
      ? attributes.data
      : {};
  }

  function hasOwn(object, key) {
    return Object.prototype.hasOwnProperty.call(object || {}, key);
  }

  function stringValue(attributes, attributeName, legacyName, fallback) {
    if (hasOwn(attributes, attributeName)) {
      return attributes[attributeName] == null ? '' : String(attributes[attributeName]);
    }
    var data = legacyData(attributes);
    if (hasOwn(data, legacyName) && data[legacyName] != null) {
      return String(data[legacyName]);
    }
    return fallback;
  }

  function numericValue(attributes, attributeName, legacyName) {
    if (hasOwn(attributes, attributeName)) {
      var current = Number(attributes[attributeName]);
      return Number.isInteger(current) && current > 0 ? current : 0;
    }
    var data = legacyData(attributes);
    var legacy = Number(data[legacyName]);
    return Number.isInteger(legacy) && legacy > 0 ? legacy : 0;
  }

  function mediaUrlFromRecord(record) {
    if (!record || typeof record !== 'object') {
      return '';
    }
    if (record.source_url) {
      return String(record.source_url);
    }
    if (record.guid && record.guid.rendered) {
      return String(record.guid.rendered);
    }
    return '';
  }

  function editableLine(value, placeholder, onChange, key) {
    return createElement(RichText, {
      key: key,
      tagName: 'span',
      className: 'statement-line-editor',
      value: value,
      placeholder: placeholder,
      allowedFormats: [],
      preserveWhiteSpace: false,
      onChange: onChange
    });
  }

  wp.blocks.registerBlockType(blockName, {
    edit: function Edit(props) {
      var attributes = props.attributes || {};
      var setAttributes = props.setAttributes;
      var lineOne = stringValue(attributes, 'lineOne', 'line_one', 'Thirteen services.');
      var lineTwo = stringValue(attributes, 'lineTwo', 'line_two', 'Five locations.');
      var lineThree = stringValue(attributes, 'lineThree', 'line_three', 'One team accountable.');
      var bandImageId = numericValue(attributes, 'bandImageId', 'band_image');
      var backgroundImageId = numericValue(attributes, 'backgroundImageId', 'background_image');
      var sectionAnchor = stringValue(attributes, 'sectionAnchor', 'section_anchor', '');

      var media = useSelect ? useSelect(function (select) {
        var core = select('core');
        return {
          band: bandImageId > 0 && core ? core.getMedia(bandImageId) : null,
          background: backgroundImageId > 0 && core ? core.getMedia(backgroundImageId) : null
        };
      }, [bandImageId, backgroundImageId]) : { band: null, background: null };

      var bandImageUrl = hasOwn(attributes, 'bandImageUrl') && attributes.bandImageUrl
        ? String(attributes.bandImageUrl)
        : (mediaUrlFromRecord(media.band) || fallbackBandImage);
      var backgroundImageUrl = hasOwn(attributes, 'backgroundImageUrl') && attributes.backgroundImageUrl
        ? String(attributes.backgroundImageUrl)
        : mediaUrlFromRecord(media.background);

      function onBandImage(mediaItem) {
        var id = Number(mediaItem && mediaItem.id);
        var url = mediaItem && mediaItem.url ? String(mediaItem.url) : '';
        if (!Number.isInteger(id) || id <= 0 || !url) {
          return;
        }
        setAttributes({ bandImageId: id, bandImageUrl: url });
      }

      function onBackgroundImage(mediaItem) {
        var id = Number(mediaItem && mediaItem.id);
        var url = mediaItem && mediaItem.url ? String(mediaItem.url) : '';
        if (!Number.isInteger(id) || id <= 0 || !url) {
          return;
        }
        setAttributes({ backgroundImageId: id, backgroundImageUrl: url });
      }

      var rootStyle = backgroundImageUrl
        ? { backgroundImage: 'url("' + backgroundImageUrl.replace(/"/g, '%22') + '")' }
        : undefined;
      var blockProps = useBlockProps({
        className: 'statement-section',
        style: rootStyle
      });

      var toolbar = null;
      if (BlockControls && MediaReplaceFlow && bandImageUrl) {
        toolbar = createElement(
          BlockControls,
          { group: 'block' },
          createElement(MediaReplaceFlow, {
            mediaId: bandImageId || undefined,
            mediaURL: bandImageUrl,
            allowedTypes: ['image'],
            accept: 'image/*',
            name: __('Replace band image', 'intercargo-vite'),
            onSelect: onBandImage
          })
        );
      }

      var inspector = null;
      if (InspectorControls && PanelBody && TextControl) {
        inspector = createElement(
          InspectorControls,
          null,
          createElement(
            PanelBody,
            {
              title: __('Section settings', 'intercargo-vite'),
              initialOpen: true
            },
            createElement(TextControl, {
              label: __('Section anchor', 'intercargo-vite'),
              help: __('Optional HTML id without the leading #.', 'intercargo-vite'),
              value: sectionAnchor,
              onChange: function (value) {
                setAttributes({ sectionAnchor: value });
              }
            }),
            MediaUpload && MediaUploadCheck && Button
              ? createElement(
                  MediaUploadCheck,
                  null,
                  createElement(MediaUpload, {
                    allowedTypes: ['image'],
                    value: backgroundImageId || undefined,
                    onSelect: onBackgroundImage,
                    render: function (control) {
                      return createElement(
                        Fragment,
                        null,
                        createElement(
                          Button,
                          {
                            variant: 'secondary',
                            onClick: control.open
                          },
                          backgroundImageUrl
                            ? __('Replace section background', 'intercargo-vite')
                            : __('Choose section background', 'intercargo-vite')
                        ),
                        backgroundImageUrl
                          ? createElement(
                              Button,
                              {
                                variant: 'tertiary',
                                isDestructive: true,
                                onClick: function () {
                                  setAttributes({ backgroundImageId: 0, backgroundImageUrl: '' });
                                }
                              },
                              __('Remove section background', 'intercargo-vite')
                            )
                          : null
                      );
                    }
                  })
                )
              : null
          )
        );
      }

      return createElement(
        Fragment,
        null,
        toolbar,
        inspector,
        createElement(
          'section',
          blockProps,
          createElement(
            'div',
            { className: 'band-media', 'aria-hidden': 'true' },
            createElement('img', { src: bandImageUrl, alt: '' })
          ),
          createElement(
            'div',
            { className: 'container statement-content' },
            createElement(
              'h2',
              { className: 'statement-title' },
              createElement(
                'span',
                { className: 'statement-mobile' },
                createElement(
                  'span',
                  { className: 'line' },
                  editableLine(lineOne, __('First line', 'intercargo-vite'), function (value) {
                    setAttributes({ lineOne: value });
                  }, 'mobile-one')
                ),
                createElement(
                  'span',
                  { className: 'line' },
                  editableLine(lineTwo, __('Second line', 'intercargo-vite'), function (value) {
                    setAttributes({ lineTwo: value });
                  }, 'mobile-two')
                ),
                createElement(
                  'span',
                  { className: 'line' },
                  editableLine(lineThree, __('Third line', 'intercargo-vite'), function (value) {
                    setAttributes({ lineThree: value });
                  }, 'mobile-three')
                )
              ),
              createElement(
                'span',
                { className: 'statement-desktop' },
                createElement(
                  'span',
                  { className: 'line' },
                  editableLine(lineOne, __('First line', 'intercargo-vite'), function (value) {
                    setAttributes({ lineOne: value });
                  }, 'desktop-one'),
                  createElement('span', { 'aria-hidden': 'true' }, ' '),
                  editableLine(lineTwo, __('Second line', 'intercargo-vite'), function (value) {
                    setAttributes({ lineTwo: value });
                  }, 'desktop-two')
                ),
                createElement(
                  'span',
                  { className: 'line' },
                  editableLine(lineThree, __('Third line', 'intercargo-vite'), function (value) {
                    setAttributes({ lineThree: value });
                  }, 'desktop-three')
                )
              )
            )
          )
        )
      );
    },

    save: function Save() {
      return null;
    }
  });
})(window.wp, window, document);
