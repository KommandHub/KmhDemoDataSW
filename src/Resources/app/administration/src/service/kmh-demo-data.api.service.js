const { Classes: { ApiService } } = Shopware;

/**
 * Thin wrapper over the plugin's two admin endpoints.
 *
 * Generation is dispatched rather than awaited: the request returns as soon as
 * the job is queued, and the page polls `status` for the outcome.
 */
class KmhDemoDataApiService extends ApiService {
    constructor(httpClient, loginService, apiEndpoint = '_action/kmh-demo-data') {
        super(httpClient, loginService, apiEndpoint);
        this.name = 'kmhDemoDataApiService';
    }

    generate(options = {}) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/generate`, options, { headers: this.getBasicHeaders() })
            .then(ApiService.handleResponse.bind(this));
    }

    /**
     * Returns the generated password in the response body and nowhere else —
     * the stored value is a hash, so the page has one chance to show it.
     */
    createDemoUser(options = {}) {
        return this.httpClient
            .post(`${this.getApiBasePath()}/demo-user`, options, { headers: this.getBasicHeaders() })
            .then(ApiService.handleResponse.bind(this));
    }

    status() {
        return this.httpClient
            .get(`${this.getApiBasePath()}/status`, { headers: this.getBasicHeaders() })
            .then(ApiService.handleResponse.bind(this));
    }
}

// httpClient lives in the *init* container, not the service container the
// factory is handed. Reading it off the wrong one yields undefined, and the
// failure only surfaces later as "cannot read property get of undefined" at the
// first request rather than at registration.
Shopware.Service().register('kmhDemoDataApiService', (container) => new KmhDemoDataApiService(
    Shopware.Application.getContainer('init').httpClient,
    container.loginService,
));

export default KmhDemoDataApiService;
