(function (wp) {
    'use strict';

    if (!wp || !wp.plugins || !wp.element || !wp.components || !wp.data || !wp.coreData) {
        return;
    }

    var editPost = wp.editPost || wp.editor;
    if (!editPost || !editPost.PluginDocumentSettingPanel) {
        return;
    }

    var el = wp.element.createElement;
    var PluginDocumentSettingPanel = editPost.PluginDocumentSettingPanel;
    var ToggleControl = wp.components.ToggleControl;
    var Notice = wp.components.Notice;
    var useEntityProp = wp.coreData.useEntityProp;
    var useSelect = wp.data.useSelect;
    var __ = wp.i18n && wp.i18n.__ ? wp.i18n.__ : function (value) { return value; };

    function PageShellSettings() {
        var postType = useSelect(function (select) {
            var editor = select('core/editor');
            return editor ? editor.getCurrentPostType() : null;
        }, []);

        if (postType !== 'page') {
            return null;
        }

        var entity = useEntityProp('postType', 'page', 'meta');
        var meta = entity[0] || {};
        var setMeta = entity[1];
        var transparent = !!meta._intercargo_transparent_header;
        var hideAnnouncement = !!meta._intercargo_hide_announcement;

        function update(key, value) {
            var next = Object.assign({}, meta);
            next[key] = !!value;
            setMeta(next);
        }

        return el(
            PluginDocumentSettingPanel,
            {
                name: 'intercargo-page-settings',
                title: __('Intercargo Page Settings', 'intercargo-vite'),
                className: 'intercargo-page-settings'
            },
            el(Notice, { status: 'info', isDismissible: false },
                transparent
                    ? __('This page uses the transparent overlay header.', 'intercargo-vite')
                    : __('This page uses the default white header.', 'intercargo-vite')
            ),
            el(ToggleControl, {
                label: __('Transparent header', 'intercargo-vite'),
                help: __('Overlay the header on page content and use the transparent-header treatment.', 'intercargo-vite'),
                checked: transparent,
                onChange: function (value) { update('_intercargo_transparent_header', value); }
            }),
            el(ToggleControl, {
                label: __('Hide announcement / world clocks', 'intercargo-vite'),
                help: __('Announcement is visible by default. Turn this on for pages such as the homepage.', 'intercargo-vite'),
                checked: hideAnnouncement,
                onChange: function (value) { update('_intercargo_hide_announcement', value); }
            })
        );
    }

    wp.plugins.registerPlugin('intercargo-page-settings', {
        render: PageShellSettings
    });
})(window.wp);
