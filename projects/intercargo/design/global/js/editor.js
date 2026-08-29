/** Global Gutenberg editor extensions. Section editors live inside their packages. */
import { registerAccentFormat } from './editor/accent.js';

(function (wp) {
  'use strict';
  if (!wp) return;
  registerAccentFormat(wp);
})(window.wp);
