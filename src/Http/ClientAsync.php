<?php

declare(strict_types=1);

namespace Yoti\Http;

use GuzzleHttp\RequestOptions;

/**
 * Handle async HTTP requests.
 *
 * Extends \GuzzleHttp\Client until an async HTTP client PSR is available.
 */
class ClientAsync extends \GuzzleHttp\Client
{
    /**
     * @param array<string, mixed> $config
     *   Configuration provided to \GuzzleHttp\Client::__construct
     */
    public function __construct(array $config = [])
    {
        parent::__construct(array_merge(
            [
                RequestOptions::TIMEOUT => 30,
                RequestOptions::HTTP_ERRORS => false,
            ],
            $config
        ));
    }
}
