(function (wp) {
  'use strict';

  if (!wp || !wp.hooks || !wp.element || !wp.blockEditor || !wp.components || !wp.data) return;

  var createElement = wp.element.createElement;
  var Fragment = wp.element.Fragment;
  var InspectorControls = wp.blockEditor.InspectorControls;
  var PanelBody = wp.components.PanelBody;
  var SelectControl = wp.components.SelectControl;
  var useSelect = wp.data.useSelect;

  function normalizeSpan(value, fallback) {
    var span = Number(value);
    return Number.isInteger(span) && span >= 1 && span <= 12 ? span : fallback;
  }

  function normalizeDevice(value) {
    return ['Mobile', 'Tablet', 'Desktop'].indexOf(value) !== -1 ? value : 'Desktop';
  }

  function readPreviewDevice(select) {
    var coreEditor = select('core/editor');
    if (coreEditor && typeof coreEditor.getDeviceType === 'function') {
      return normalizeDevice(coreEditor.getDeviceType());
    }

    var editPost = select('core/edit-post');
    if (editPost && typeof editPost.__experimentalGetPreviewDeviceType === 'function') {
      return normalizeDevice(editPost.__experimentalGetPreviewDeviceType());
    }

    return 'Desktop';
  }

  var widthOptions = [];
  for (var span = 1; span <= 12; span += 1) {
    widthOptions.push({ label: span + ' / 12', value: span });
  }

  var deviceSettings = {
    Mobile: { attribute: 'spanMobile', fallback: 12 },
    Tablet: { attribute: 'spanTablet', fallback: 4 },
    Desktop: { attribute: 'spanDesktop', fallback: 3 }
  };

  function deviceSpans(attributes) {
    return {
      spanMobile: normalizeSpan(attributes && attributes.spanMobile, 12),
      spanTablet: normalizeSpan(attributes && attributes.spanTablet, 4),
      spanDesktop: normalizeSpan(attributes && attributes.spanDesktop, 3)
    };
  }

  wp.hooks.addFilter('editor.BlockEdit', 'intercargo/media-tile-preview-width', function (BlockEdit) {
    return function MediaTilePreviewWidth(props) {
      if (props.name !== 'intercargo/media-tile') {
        return createElement(BlockEdit, props);
      }

      var previewDevice = useSelect(readPreviewDevice, []);
      var setting = deviceSettings[previewDevice];
      var values = deviceSpans(props.attributes);
      var currentValue = values[setting.attribute];

      return createElement(Fragment, null,
        createElement(BlockEdit, props),
        createElement(InspectorControls, null,
          createElement(PanelBody, { title: 'Responsive image width', initialOpen: true },
            createElement(SelectControl, {
              label: previewDevice + ' width',
              help: 'This value applies only to the active Gutenberg preview viewport.',
              value: currentValue,
              options: widthOptions,
              onChange: function (nextSpan) {
                var update = {};
                update[setting.attribute] = normalizeSpan(nextSpan, setting.fallback);
                props.setAttributes(update);
              }
            })
          )
        )
      );
    };
  });

  wp.hooks.addFilter('editor.BlockListBlock', 'intercargo/media-tile-preview-layout', function (BlockListBlock) {
    return function MediaTilePreviewLayout(props) {
      if (props.name !== 'intercargo/media-tile') {
        return createElement(BlockListBlock, props);
      }

      var values = deviceSpans(props.attributes);
      var wrapperProps = Object.assign({}, props.wrapperProps || {});
      wrapperProps.style = Object.assign({}, wrapperProps.style || {}, {
        '--media-mosaic-span-mobile': values.spanMobile,
        '--media-mosaic-span-tablet': values.spanTablet,
        '--media-mosaic-span-desktop': values.spanDesktop
      });
      wrapperProps['data-media-mosaic-tile'] = 'true';
      return createElement(BlockListBlock, Object.assign({}, props, { wrapperProps: wrapperProps }));
    };
  });
})(window.wp);
