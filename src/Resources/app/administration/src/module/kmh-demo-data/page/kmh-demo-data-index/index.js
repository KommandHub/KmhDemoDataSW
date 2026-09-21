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

    inject: ['kmhDemoDataApiService'],

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
        };
    },

    computed: {
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
