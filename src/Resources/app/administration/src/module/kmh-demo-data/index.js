import './page/kmh-demo-data-index';

// Snippets are not imported here on purpose. Shopware picks them up from
// Resources/app/administration/src/snippet, and passing them to register()
// bloats the bundle — the admin ESLint ruleset rejects it outright.

Shopware.Module.register('kmh-demo-data', {
    type: 'plugin',
    name: 'kmh-demo-data',
    title: 'kmh-demo-data.general.mainMenuItemGeneral',
    description: 'kmh-demo-data.general.descriptionTextModule',
    color: '#9AA8B5',
    icon: 'regular-database',

    routes: {
        index: {
            component: 'kmh-demo-data-index',
            path: 'index',
            meta: { parentPath: 'sw.settings.index.plugins', privilege: 'kmh_demo_data.generate' },
        },
    },

    settingsItem: [{
        group: 'plugins',
        to: 'kmh.demo.data.index',
        icon: 'regular-database',
        name: 'kmh-demo-data',
        label: 'kmh-demo-data.general.mainMenuItemGeneral',
        privilege: 'kmh_demo_data.generate',
    }],
});
