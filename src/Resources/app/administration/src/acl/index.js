/**
 * Generating demo data writes across most of the catalogue, so it is its own
 * privilege rather than something bundled into a broader role.
 */
Shopware.Service('privileges').addPrivilegeMappingEntry({
    category: 'permissions',
    parent: 'settings',
    key: 'kmh_demo_data',
    roles: {
        generate: {
            privileges: ['kmh_demo_data:generate'],
            dependencies: [],
        },
    },
});
