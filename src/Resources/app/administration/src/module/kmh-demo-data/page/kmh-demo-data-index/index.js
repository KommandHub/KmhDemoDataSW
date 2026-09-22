import template from './kmh-demo-data-index.html.twig';
import './index.scss';

const { Component, Mixin } = Shopware;

/**
 * The demo data page.
 *
 * Generation is queued, so the page's job after pressing the button is to poll
 * until the handler records a result. Polling stops on leaving the page, and
 * restarts on arriving at one where a run is already going — reloading the
 * browser mid-run must not lose sight of it.
 */
Component.register('kmh-demo-data-index', {
    template,

    inject: ['kmhDemoDataApiService', 'acl'],

    mixins: [Mixin.getByName('notification')],

    data() {
        return {
            isLoading: true,
            status: { state: 'idle' },
            channels: [],
            selectedChannels: [],
            perCategory: null,
            withMedia: true,
            withOrders: true,
            pollHandle: null,
            demoUser: { exists: false },
            demoUserEmail: '',
            demoUserUsername: '',
            demoUserPassword: null,
            isCreatingUser: false,
        };
    },

    computed: {
        canManageUser() {
            return this.acl.can('kmh_demo_data.manage_user');
        },

        isRunning() {
            return this.status.state === 'running';
        },

        hasReport() {
            return Array.isArray(this.status.rows) && this.status.rows.length > 0;
        },

        reportColumns() {
            return (this.status.headers || []).map((label, index) => ({
                property: `col${index}`,
                label,
                rawData: true,
                sortable: false,
            }));
        },

        reportRows() {
            return (this.status.rows || []).map((row, rowIndex) => {
                const mapped = { id: `row-${rowIndex}` };
                row.forEach((value, index) => { mapped[`col${index}`] = value; });

                return mapped;
            });
        },
    },

    created() {
        this.loadStatus();
    },

    beforeUnmount() {
        this.stopPolling();
    },

    methods: {
        loadStatus() {
            return this.kmhDemoDataApiService.status()
                .then((response) => {
                    this.status = response.status || { state: 'idle' };
                    this.channels = response.channels || [];
                    this.demoUser = response.demoUser || { exists: false };

                    if (this.perCategory === null) {
                        this.perCategory = response.defaultPerCategory ?? null;
                    }

                    // Survives a reload: a run started before the page loaded is
                    // picked back up rather than looking finished.
                    if (this.isRunning) {
                        this.startPolling();
                    } else {
                        this.stopPolling();
                    }
                })
                .catch(() => {
                    this.createNotificationError({
                        message: this.$tc('kmh-demo-data.notification.statusFailed'),
                    });
                })
                .finally(() => { this.isLoading = false; });
        },

        onGenerate() {
            this.isLoading = true;

            this.kmhDemoDataApiService.generate({
                channels: this.selectedChannels,
                perCategory: this.perCategory,
                withMedia: this.withMedia,
                withOrders: this.withOrders,
            })
                .then(() => {
                    this.createNotificationInfo({
                        message: this.$tc('kmh-demo-data.notification.queued'),
                    });

                    return this.loadStatus();
                })
                .catch(() => {
                    this.isLoading = false;
                    this.createNotificationError({
                        message: this.$tc('kmh-demo-data.notification.generateFailed'),
                    });
                });
        },

        onCreateDemoUser(rotatePassword = false) {
            this.isCreatingUser = true;
            this.demoUserPassword = null;

            this.kmhDemoDataApiService.createDemoUser({
                email: this.demoUserEmail,
                username: this.demoUserUsername,
                rotatePassword,
            })
                .then((response) => {
                    // Shown once: what the database keeps is a hash, so losing
                    // this means rotating rather than looking it up.
                    this.demoUserPassword = response.password || null;

                    this.createNotificationSuccess({
                        message: this.$tc(response.created
                            ? 'kmh-demo-data.notification.userCreated'
                            : 'kmh-demo-data.notification.userUpdated'),
                    });

                    return this.loadStatus();
                })
                .catch((error) => {
                    // 409 means an account with that login exists and this
                    // plugin did not create it, which is a different problem
                    // from the request failing.
                    const refused = error?.response?.status === 409;

                    this.createNotificationError({
                        message: this.$tc(refused
                            ? 'kmh-demo-data.notification.userRefused'
                            : 'kmh-demo-data.notification.userFailed'),
                    });
                })
                .finally(() => { this.isCreatingUser = false; });
        },

        startPolling() {
            if (this.pollHandle !== null) {
                return;
            }

            this.pollHandle = window.setInterval(() => { this.loadStatus(); }, 3000);
        },

        stopPolling() {
            if (this.pollHandle === null) {
                return;
            }

            window.clearInterval(this.pollHandle);
            this.pollHandle = null;
        },
    },
});
