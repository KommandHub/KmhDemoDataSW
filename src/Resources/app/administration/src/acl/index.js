/**
 * Generating demo data writes across most of the catalogue, so it is its own
 * privilege rather than something bundled into a broader role. Minting an
 * administration login is a second one again: it is the only thing here that
 * hands somebody outside the business a way in.
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
        manage_user: {
            privileges: ['kmh_demo_data:manage_user'],
            dependencies: ['kmh_demo_data.generate'],
        },
    },
});
