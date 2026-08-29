(() => {
(function(wp){
'use strict';
if(!wp||!wp.richText||!wp.blockEditor||!wp.element)return;
var registerFormatType=wp.richText.registerFormatType,toggleFormat=wp.richText.toggleFormat,Button=wp.blockEditor.RichTextToolbarButton,ce=wp.element.createElement,__=wp.i18n?wp.i18n.__:function(t){return t;},select=wp.data?wp.data.select:null,FORMAT='intercargo/accent';
function inside(){if(!select)return true;var store=select('core/block-editor');if(!store)return true;var id=store.getSelectedBlockClientId();if(!id)return false;return store.getBlockParents(id).concat(id).some(function(cid){var n=store.getBlockName(cid);return !!n&&(n.indexOf('intercargo/')===0||n.indexOf('acf/intercargo-')===0);});}
if(wp.richText.getFormatType&&wp.richText.getFormatType(FORMAT))return;
registerFormatType(FORMAT,{title:__('Accent','intercargo-vite'),tagName:'span',className:'accent',edit:function(p){if(!inside())return null;return ce(Button,{icon:'editor-textcolor',title:__('Accent','intercargo-vite'),isActive:p.isActive,onClick:function(){p.onChange(toggleFormat(p.value,{type:FORMAT}));}});}});
})(window.wp);
})();
