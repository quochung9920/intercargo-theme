<?php
return [
    'dependencies' => ['intercargo-form-editor','wp-api-fetch','wp-blocks','wp-block-editor','wp-components','wp-core-data','wp-data','wp-element','wp-i18n','wp-server-side-render'],
    'version' => substr(hash_file('sha256', __DIR__ . '/editor.js') ?: '1', 0, 16),
];
