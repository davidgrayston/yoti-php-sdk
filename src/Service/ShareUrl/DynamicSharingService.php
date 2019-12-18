<?php

namespace Yoti\Service\ShareUrl;

use Psr\Http\Client\ClientInterface;
use Yoti\Exception\ShareUrlException;
use Yoti\Http\Payload;
use Yoti\Util\PemFile;
use Yoti\Http\RequestBuilder;
use Yoti\ShareUrl\DynamicScenario;
use Yoti\Util\Config;
use Yoti\Util\Constants;
use Yoti\Util\Json;

class DynamicSharingService
{
    /**
     * @param string $sdkId
     * @param \Yoti\Util\PemFile $pemFile
     * @param \Psr\Http\Client\ClientInterface $httpClient
     */
    public function __construct(
        string $sdkId,
        PemFile $pemFile,
        ClientInterface $httpClient
    ) {
        $this->sdkId = $sdkId;
        $this->pemFile = $pemFile;
        $this->httpClient = $httpClient;
    }

    /**
     * @param \Yoti\ShareUrl\DynamicScenario $dynamicScenario
     *
     * @return \Yoti\Service\ShareUrl\ShareUrlResult
     *
     * @throws \Yoti\Exception\ShareUrlException
     */
    public function createShareUrl(DynamicScenario $dynamicScenario): ShareUrlResult
    {
        $response = (new RequestBuilder())
            ->withBaseUrl(Config::get(Constants::CONNECT_API_URL_KEY, Constants::CONNECT_API_URL))
            ->withEndpoint(sprintf('/qrcodes/apps/%s', $this->sdkId))
            ->withQueryParam('appId', $this->sdkId)
            ->withPost()
            ->withPayload(Payload::fromJsonData($dynamicScenario))
            ->withPemFile($this->pemFile)
            ->withClient($this->httpClient)
            ->build()
            ->execute();

        $httpCode = $response->getStatusCode();
        if ($httpCode < 200 || $httpCode > 299) {
            throw new ShareUrlException("Server responded with {$httpCode}");
        }

        return new ShareUrlResult(Json::decode($response->getBody()));
    }
}
