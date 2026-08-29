/**
 * The intercargo/accent rich-text format.
 *
 * Accent phrases are part of the copy, so they must survive editing. RichText keeps
 * only registered format types; an unregistered <span class="accent"> is dropped the
 * first time its paragraph is focused and re-serialised.
 */
export function registerAccentFormat(wp) {
  if (!wp || !wp.richText || !wp.blockEditor || !wp.element) {
    return;
  }

  var registerFormatType = wp.richText.registerFormatType;
  var toggleFormat = wp.richText.toggleFormat;
  var RichTextToolbarButton = wp.blockEditor.RichTextToolbarButton;
  var createElement = wp.element.createElement;
  var __ = wp.i18n ? wp.i18n.__ : function (text) { return text; };
  var select = wp.data ? wp.data.select : null;

  var FORMAT_NAME = 'intercargo/accent';
  var SECTION_PREFIXES = ['acf/intercargo-', 'intercargo/'];

  /**
   * Accent is a section-level typographic device, not a general writing tool. Keeping
   * the control out of unrelated blocks is part of "no arbitrary markup": it exists so
   * editors never need raw HTML inside an approved section.
   */
  function isInsideIntercargoSection() {
    if (!select) {
      return true;
    }
    var store = select('core/block-editor');
    if (!store) {
      return true;
    }
    var clientId = store.getSelectedBlockClientId();
    if (!clientId) {
      return false;
    }
    return store.getBlockParents(clientId).concat(clientId).some(function (id) {
      var name = store.getBlockName(id);
      return !!name && SECTION_PREFIXES.some(function (prefix) {
        return name.indexOf(prefix) === 0;
      });
    });
  }

  registerFormatType(FORMAT_NAME, {
    title: __('Accent', 'intercargo-vite'),
    tagName: 'span',
    className: 'accent',
    edit: function (props) {
      if (!isInsideIntercargoSection()) {
        return null;
      }
      return createElement(RichTextToolbarButton, {
        icon: 'editor-textcolor',
        title: __('Accent', 'intercargo-vite'),
        isActive: props.isActive,
        onClick: function () {
          props.onChange(toggleFormat(props.value, { type: FORMAT_NAME }));
        }
      });
    }
  });
}
