<?php

declare(strict_types=1);

namespace Yoti\DocScan;

use GuzzleHttp\Promise\PromiseInterface;
use Psr\Http\Message\ResponseInterface;
use Yoti\Constants;
use Yoti\DocScan\Exception\DocScanException;
use Yoti\DocScan\Session\Create\CreateSessionResult;
use Yoti\DocScan\Session\Create\SessionSpecification;
use Yoti\DocScan\Session\Retrieve\GetSessionResult;
use Yoti\DocScan\Support\SupportedDocumentsResponse;
use Yoti\Http\Payload;
use Yoti\Http\Request;
use Yoti\Http\RequestBuilder;
use Yoti\Media\Media;
use Yoti\Util\Config;
use Yoti\Util\Json;
use Yoti\Util\PemFile;

class Service
{

    /**
     * @var string
     */
    private $sdkId;

    /**
     * @var PemFile
     */
    private $pemFile;

    /**
     * @var Config
     */
    private $config;

    /**
     * @var string
     */
    private $apiUrl;

    /**
     * @param string $sdkId
     * @param PemFile $pemFile
     * @param Config $config
     */
    public function __construct(string $sdkId, PemFile $pemFile, Config $config)
    {
        $this->sdkId = $sdkId;
        $this->pemFile = $pemFile;
        $this->config = $config;
        $this->apiUrl = $config->getApiUrl() ?? Constants::DOC_SCAN_API_URL;
    }

    /**
     * @return \GuzzleHttp\ClientInterface
     */
    private function asyncHttpClient(): \GuzzleHttp\ClientInterface
    {
        return $this->config->getHttpClientAsync();
    }

    /**
     * Creates a Yoti Doc Scan session using the supplied
     * specification.
     *
     * @param SessionSpecification $sessionSpec
     *
     * @return CreateSessionResult
     *
     * @throws DocScanException
     */
    public function createSession(SessionSpecification $sessionSpec): CreateSessionResult
    {
        $response = (new RequestBuilder($this->config))
            ->withBaseUrl($this->apiUrl)
            ->withEndpoint('/sessions')
            ->withQueryParam('sdkId', $this->sdkId)
            ->withPayload(Payload::fromJsonData($sessionSpec))
            ->withHeader('Content-Type', 'application/json')
            ->withPemFile($this->pemFile)
            ->withPost()
            ->build()
            ->execute();

        self::assertResponseIsSuccess($response);

        $result = Json::decode((string) $response->getBody());

        return new CreateSessionResult($result);
    }

    /**
     * Gets a session from the Yoti Doc Scan system.
     *
     * @param string $sessionId
     * @return GetSessionResult
     * @throws DocScanException
     */
    public function retrieveSession(string $sessionId): GetSessionResult
    {
        $response = (new RequestBuilder($this->config))
            ->withBaseUrl($this->apiUrl)
            ->withEndpoint(sprintf('/sessions/%s', $sessionId))
            ->withQueryParam('sdkId', $this->sdkId)
            ->withPemFile($this->pemFile)
            ->withGet()
            ->build()
            ->execute();

        self::assertResponseIsSuccess($response);

        $result = Json::decode((string) $response->getBody());

        return new GetSessionResult($result);
    }

    /**
     * Deletes a session from the Yoti Doc Scan system.
     *
     * @param string $sessionId
     * @throws DocScanException
     */
    public function deleteSession(string $sessionId): void
    {
        $response = (new RequestBuilder($this->config))
            ->withBaseUrl($this->apiUrl)
            ->withEndpoint(sprintf('/sessions/%s', $sessionId))
            ->withQueryParam('sdkId', $this->sdkId)
            ->withPemFile($this->pemFile)
            ->withMethod(Request::METHOD_DELETE)
            ->build()
            ->execute();

        self::assertResponseIsSuccess($response);
    }

    /**
     * @param string $sessionId
     * @param string $mediaId
     * @return \Yoti\Http\Request
     */
    private function mediaContentRequest(string $sessionId, string $mediaId): Request
    {
        return (new RequestBuilder($this->config))
            ->withBaseUrl($this->apiUrl)
            ->withEndpoint(sprintf('/sessions/%s/media/%s/content', $sessionId, $mediaId))
            ->withQueryParam('sdkId', $this->sdkId)
            ->withPemFile($this->pemFile)
            ->withGet()
            ->build();
    }

    /**
     * @param \Psr\Http\Message\ResponseInterface $response
     * @return \Yoti\Media\Media
     */
    private function mediaContentResponse(ResponseInterface $response): Media
    {
        self::assertResponseIsSuccess($response);

        $content = (string) $response->getBody();
        $mimeType = $response->getHeader("Content-Type")[0] ?? '';

        return new Media($mimeType, $content);
    }

    /**
     * Retrieves media content from a Doc Scan session using supplied
     * media ID.
     *
     * @param string $sessionId
     * @param string $mediaId
     * @return Media
     * @throws DocScanException
     */
    public function getMediaContent(string $sessionId, string $mediaId): Media
    {
        $response = $this
            ->mediaContentRequest($sessionId, $mediaId)
            ->execute();

        return $this->mediaContentResponse($response);
    }

    /**
     * Retrieves media content asynchronously from a Doc Scan session using supplied
     * media ID.
     *
     * @param string $sessionId
     * @param string $mediaId
     * @return PromiseInterface
     * @throws DocScanException
     */
    public function getMediaContentAsync(string $sessionId, string $mediaId): PromiseInterface
    {
        $request = $this
            ->mediaContentRequest($sessionId, $mediaId)
            ->getMessage();

        return $this
            ->asyncHttpClient()
            ->sendAsync($request)
            ->then(
                function (ResponseInterface $response): Media {
                    return $this->mediaContentResponse($response);
                }
            );
    }

    /**
     * Deletes media from a Yoti Doc Scan session using
     * supplied media ID.
     *
     * @param string $sessionId
     * @param string $mediaId
     * @throws DocScanException
     */
    public function deleteMediaContent(string $sessionId, string $mediaId): void
    {
        $response = (new RequestBuilder($this->config))
            ->withBaseUrl($this->apiUrl)
            ->withEndpoint(sprintf('/sessions/%s/media/%s/content', $sessionId, $mediaId))
            ->withQueryParam('sdkId', $this->sdkId)
            ->withPemFile($this->pemFile)
            ->withMethod(Request::METHOD_DELETE)
            ->build()
            ->execute();

        self::assertResponseIsSuccess($response);
    }

    /**
     * Gets a list of supported documents.
     *
     * @return SupportedDocumentsResponse
     */
    public function getSupportedDocuments(): SupportedDocumentsResponse
    {
        $response = (new RequestBuilder($this->config))
            ->withBaseUrl($this->apiUrl)
            ->withEndpoint('/supported-documents')
            ->withPemFile($this->pemFile)
            ->withGet()
            ->build()
            ->execute();

        self::assertResponseIsSuccess($response);

        $result = Json::decode((string) $response->getBody());

        return new SupportedDocumentsResponse($result);
    }

    /**
     * @param ResponseInterface $response
     *
     * @throws DocScanException
     */
    private static function assertResponseIsSuccess(ResponseInterface $response): void
    {
        $httpCode = $response->getStatusCode();
        if ($httpCode < 200 || $httpCode > 299) {
            throw new DocScanException("Server responded with {$httpCode}", $response);
        }
    }
}
