<?php

namespace Yoti\Util;

class Constants
{
    const CONNECT_API_URL_KEY = 'connect.api.url';
    const CONNECT_API_URL = 'https://api.yoti.com/api/v1';

    const SDK_IDENTIFIER_KEY = 'sdk.identifier';
    const SDK_IDENTIFIER = 'PHP';

    const SDK_VERSION_KEY = 'sdk.version';
    const SDK_VERSION = '3.0.0';

    /** Base url for connect page (user will be redirected to this page eg. baseurl/app-id) */
    const CONNECT_BASE_URL = 'https://www.yoti.com/connect';

    /** Yoti Hub login */
    const DASHBOARD_URL = 'https://hub.yoti.com';

    /**
     * RFC3339 format with microseconds.
     *
     * This will be replaced by \DateTime::RFC3339_EXTENDED
     * once PHP 5.6 is no longer supported.
     */
    const DATE_FORMAT_RFC3339 = 'Y-m-d\TH:i:s.uP';
}
