<?php
return [
    'dependencies' => ['intercargo-service-navigation-editor-config','wp-blocks','wp-block-editor','wp-components','wp-core-data','wp-data','wp-element','wp-i18n'],
    'version' => substr(hash_file('sha256', __DIR__ . '/editor.js') ?: '1', 0, 16),
];
