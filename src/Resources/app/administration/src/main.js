import deDE from './snippet/de-DE.json';
import enGB from './snippet/en-GB.json';

// Registered against the locale rather than passed to Module.register: the
// admin ESLint ruleset rejects a `snippets` key on a module because it bloats
// the bundle, and this is the sanctioned way to ship them.
Shopware.Locale.extend('de-DE', deDE);
Shopware.Locale.extend('en-GB', enGB);

import './acl';
import './service/kmh-demo-data.api.service';
import './module/kmh-demo-data';
