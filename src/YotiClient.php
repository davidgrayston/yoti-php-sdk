<?php

namespace Yoti;

use Psr\Http\Client\ClientInterface;
use Yoti\Entity\Receipt;
use Yoti\Exception\YotiClientException;
use Yoti\Http\Payload;
use Yoti\Http\AmlResult;
use Yoti\Entity\AmlProfile;
use Yoti\Http\RequestBuilder;
use Yoti\Exception\AmlException;
use Yoti\Exception\ReceiptException;
use Yoti\Exception\ActivityDetailsException;
use Yoti\Exception\PemFileException;
use Yoti\Exception\ShareUrlException;
use Yoti\Http\Request;
use Yoti\Util\PemFile;
use Yoti\ShareUrl\DynamicScenario;
use Yoti\Http\ShareUrlResult;
use Yoti\Util\Validation;

/**
 * Class YotiClient
 *
 * @package Yoti
 * @author Yoti SDK <websdk@yoti.com>
 */
class YotiClient
{
    /** Request successful outcome */
    const OUTCOME_SUCCESS = 'SUCCESS';

    /** Default url for api (is passed in via constructor) */
    const DEFAULT_CONNECT_API = 'https://api.yoti.com/api/v1';

    /** Base url for connect page (user will be redirected to this page eg. baseurl/app-id) */
    const CONNECT_BASE_URL = 'https://www.yoti.com/connect';

    /** Yoti Hub login */
    const DASHBOARD_URL = 'https://hub.yoti.com';

    /** Aml check endpoint */
    const AML_CHECK_ENDPOINT = '/aml-check';

    /** Profile sharing endpoint */
    const PROFILE_REQUEST_ENDPOINT = '/profile/%s';

    /** Share URL endpoint */
    const SHARE_URL_ENDPOINT = '/qrcodes/apps/%s';

    /** Auth HTTP header key */
    const YOTI_AUTH_HEADER_KEY = 'X-Yoti-Auth-Key';

    /**
     * @var \Yoti\Util\PemFile
     */
    private $pemFile;

    /**
     * @var string
     */
    private $sdkId;

    /**
     * @var string
     */
    private $connectApi;

    /**
     * @var string
     */
    private $sdkIdentifier;

    /**
     * @var string
     */
    private $sdkVersion;

    /**
     * @var \Psr\Http\Client\ClientInterface
     */
    private $httpClient;

    /**
     * YotiClient constructor.
     *
     * @param string $sdkId
     *   The SDK identifier generated by Yoti Hub when you create your app.
     * @param string $pem
     *   PEM file path or string
     * @param string $connectApi (optional)
     *   Connect API address
     *
     * @throws \Yoti\Exception\RequestException
     * @throws \Yoti\Exception\YotiClientException
     */
    public function __construct(
        $sdkId,
        $pem,
        $connectApi = self::DEFAULT_CONNECT_API
    ) {
        $this->checkRequiredModules();
        $this->extractPemContent($pem);
        $this->setSdkId($sdkId);

        $this->connectApi = $connectApi;
    }

    /**
     * Get login url.
     *
     * @param string $appId
     *
     * @return string
     */
    public static function getLoginUrl($appId)
    {
        return self::CONNECT_BASE_URL . "/$appId";
    }

    /**
     * Return Yoti user profile.
     *
     * @param string $encryptedConnectToken
     *
     * @return \Yoti\ActivityDetails
     *
     * @throws \Yoti\Exception\ActivityDetailsException
     * @throws \Yoti\Exception\ReceiptException
     */
    public function getActivityDetails($encryptedConnectToken)
    {
        $receipt = $this->getReceipt($encryptedConnectToken);

        // Check response was successful
        if ($receipt->getSharingOutcome() !== self::OUTCOME_SUCCESS) {
            throw new ActivityDetailsException('Outcome was unsuccessful', 502);
        }

        return new ActivityDetails($receipt, $this->pemFile);
    }

    /**
     * Perform AML profile check.
     *
     * @param \Yoti\Entity\AmlProfile $amlProfile
     *
     * @return \Yoti\Http\AmlResult
     *
     * @throws \Yoti\Exception\AmlException
     * @throws \Yoti\Exception\RequestException
     */
    public function performAmlCheck(AmlProfile $amlProfile)
    {
        $response = $this->sendConnectRequest(
            self::AML_CHECK_ENDPOINT,
            Request::METHOD_POST,
            Payload::fromJsonData($amlProfile)
        );

        // Get response data array
        $result = $this->processJsonResponse($response->getBody());

        // Validate result
        $this->validateAmlResult($result, $response->getStatusCode());

        // Set and return result
        return new AmlResult($result);
    }

    /**
     * Get Share URL for provided dynamic scenario.
     *
     * @param \Yoti\ShareUrl\DynamicScenario $dynamicScenario
     *
     * @return \Yoti\Http\ShareUrlResult
     *
     * @throws \Yoti\Exception\ShareUrlException
     * @throws \Yoti\Exception\RequestException
     */
    public function createShareUrl(DynamicScenario $dynamicScenario)
    {
        $response = $this->sendConnectRequest(
            sprintf(self::SHARE_URL_ENDPOINT, $this->sdkId),
            Request::METHOD_POST,
            Payload::fromJsonData($dynamicScenario)
        );

        $httpCode = $response->getStatusCode();
        if (!$this->isResponseSuccess($httpCode)) {
            throw new ShareUrlException("Server responded with {$httpCode}");
        }

        $result = $this->processJsonResponse($response->getBody());

        return new ShareUrlResult($result);
    }

    /**
     * Set SDK identifier.
     *
     * Allows plugins to declare their identifier.
     *
     * @param string $sdkIdentifier
     *   SDK or Plugin identifier
     */
    public function setSdkIdentifier($sdkIdentifier)
    {
        $this->sdkIdentifier = $sdkIdentifier;
    }

    /**
     * Set SDK version.
     *
     * Allows plugins to declare their version.
     *
     * @param string $sdkVersion
     *   SDK or Plugin version
     */
    public function setSdkVersion($sdkVersion)
    {
        $this->sdkVersion = $sdkVersion;
    }

    /**
     * Set a custom request handler.
     *
     * @param \Psr\Http\Client\ClientInterface $httpClient
     */
    public function setHttpClient(ClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    /**
     * Make REST request to Connect API.
     * This method allows to stub the request call in test mode.
     *
     * @param string $endpoint
     * @param string $httpMethod
     * @param Payload|null $payload
     *
     * @return \Yoti\Http\Response
     *
     * @throws \Yoti\Exception\RequestException
     */
    private function sendConnectRequest($endpoint, $httpMethod, Payload $payload = null, $headers = [])
    {
        $requestBuilder = (new RequestBuilder())
            ->withBaseUrl($this->connectApi)
            ->withEndpoint($endpoint)
            ->withMethod($httpMethod)
            ->withPemString((string) $this->pemFile)
            ->withQueryParam('appId', $this->sdkId);

        if (isset($payload)) {
            $requestBuilder->withPayload($payload);
        }

        if (isset($this->sdkIdentifier)) {
            $requestBuilder->withSdkIdentifier($this->sdkIdentifier);
        }

        if (isset($this->sdkVersion)) {
            $requestBuilder->withSdkVersion($this->sdkVersion);
        }

        if (isset($this->httpClient)) {
            $requestBuilder->withClient($this->httpClient);
        }

        foreach ($headers as $name => $value) {
            $requestBuilder->withHeader($name, $value);
        }

        return $requestBuilder->build()->execute();
    }

    /**
     * Handle request result.
     *
     * @param array $responseArr
     * @param int $httpCode
     *
     * @throws \Yoti\Exception\AmlException
     */
    private function validateAmlResult(array $responseArr, $httpCode)
    {
        if ($this->isResponseSuccess((int) $httpCode)) {
            // The request is successful - nothing to do
            return;
        }

        $errorMessage = $this->getErrorMessage($responseArr);
        $errorCode = isset($responseArr['code']) ? $responseArr['code'] : 'Error';

        // Throw the error message that's included in the response
        if (!empty($errorMessage)) {
            throw new AmlException("$errorCode - {$errorMessage}", $httpCode);
        }

        // Throw a general error message
        throw new AmlException("{$errorCode} - Server responded with {$httpCode}", $httpCode);
    }

    /**
     * Get error message from the response array.
     *
     * @param array $result
     *
     * @return null|string
     */
    private function getErrorMessage(array $result)
    {
        $errorMessage = '';
        if (isset($result['errors'][0]['property']) && isset($result['errors'][0]['message'])) {
            $errorMessage = $result['errors'][0]['property'] . ': ' . $result['errors'][0]['message'];
        }
        return $errorMessage;
    }

    /**
     * Decrypt and return receipt data.
     *
     * @param string $encryptedConnectToken
     *
     * @return \Yoti\Entity\Receipt
     *
     * @throws \Yoti\Exception\ActivityDetailsException
     * @throws \Yoti\Exception\ReceiptException
     * @throws \Yoti\Exception\RequestException
     */
    private function getReceipt($encryptedConnectToken)
    {
        // Decrypt connect token
        $token = $this->decryptConnectToken($encryptedConnectToken);
        if (!$token) {
            throw new ActivityDetailsException('Could not decrypt connect token.', 401);
        }

        // Request endpoint
        $endpoint = sprintf(self::PROFILE_REQUEST_ENDPOINT, $token);
        $response = $this->sendConnectRequest(
            $endpoint,
            Request::METHOD_GET,
            null,
            [self::YOTI_AUTH_HEADER_KEY => $this->pemFile->getAuthKey()]
        );

        $httpCode = $response->getStatusCode();
        if (!$this->isResponseSuccess($httpCode)) {
            throw new ActivityDetailsException("Server responded with {$httpCode}", $httpCode);
        }

        $result = $this->processJsonResponse($response->getBody());
        $this->checkForReceipt($result);

        return new Receipt($result['receipt']);
    }

    /**
     * @param string $json
     *
     * @return mixed the decoded JSON result.
     *
     * @throws \Yoti\Exception\YotiClientException
     */
    private function processJsonResponse($json)
    {
        // Get decoded response data
        $result = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new YotiClientException('JSON response was invalid', 502);
        }

        return $result;
    }

    /**
     * @param array $response
     *
     * @throws \Yoti\Exception\ReceiptException
     */
    private function checkForReceipt(array $responseArr)
    {
        // Check receipt is in response
        if (!array_key_exists('receipt', $responseArr)) {
            throw new ReceiptException('Receipt not found in response', 502);
        }
    }

    /**
     * @param int $httpCode
     *
     * @return boolean
     */
    private function isResponseSuccess($httpCode)
    {
        Validation::isInteger($httpCode, 'httpCode');
        return $httpCode >= 200 && $httpCode < 300;
    }

    /**
     * Decrypt connect token.
     *
     * @param string $encryptedConnectToken
     *
     * @return mixed
     */
    private function decryptConnectToken($encryptedConnectToken)
    {
        $tok = base64_decode(strtr($encryptedConnectToken, '-_,', '+/='));
        openssl_private_decrypt($tok, $token, (string) $this->pemFile);

        return $token;
    }

    /**
     * Validate and set PEM file content.
     *
     * @deprecated 3.0.0 this will be replaced by \Yoti\Util\PemFile.
     *
     * @param string $pem
     *   PEM file path or string
     *
     * @throws \Yoti\Exception\YotiClientException
     */
    private function extractPemContent($pem)
    {
        // Check PEM passed
        if (!$pem) {
            throw new YotiClientException('PEM file is required', 400);
        }

        // Assert file exists if user passed PEM file path using file:// stream wrapper.
        if (strpos($pem, 'file://') === 0 && !is_file($pem)) {
            throw new YotiClientException('PEM file was not found.', 400);
        }

        try {
            if (is_file($pem)) {
                $this->pemFile = PemFile::fromFilePath($pem);
            } else {
                $this->pemFile = PemFile::fromString($pem);
            }
        } catch (PemFileException $e) {
            throw new YotiClientException('PEM file path or content is invalid', 400);
        }
    }

    /**
     * Validate and set SDK ID.
     *
     * @param string $sdkId
     *
     * @throws \Yoti\Exception\YotiClientException
     */
    private function setSdkId($sdkId)
    {
        // Check SDK ID passed
        if (!$sdkId) {
            throw new YotiClientException('SDK ID is required', 400);
        }
        $this->sdkId = $sdkId;
    }

    /**
     * Check PHP required modules.
     *
     * @throws \Yoti\Exception\YotiClientException
     */
    private function checkRequiredModules()
    {
        $requiredModules = ['curl', 'json'];
        foreach ($requiredModules as $mod) {
            if (!extension_loaded($mod)) {
                throw new YotiClientException("PHP module '$mod' not installed", 501);
            }
        }
    }
}
