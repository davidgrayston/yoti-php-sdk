<?php

namespace Yoti\Service\Profile;

use Psr\Http\Client\ClientInterface;
use Yoti\Entity\Receipt;
use Yoti\Exception\ActivityDetailsException;
use Yoti\Exception\ReceiptException;
use Yoti\Http\RequestBuilder;
use Yoti\Util\Config;
use Yoti\Util\Constants;
use Yoti\Util\Json;
use Yoti\Util\PemFile;

class ProfileService
{
    /** Request successful outcome */
    const OUTCOME_SUCCESS = 'SUCCESS';

    /** Auth HTTP header key */
    const YOTI_AUTH_HEADER_KEY = 'X-Yoti-Auth-Key';

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
     * Return Yoti user profile.
     *
     * @param string $encryptedConnectToken
     *
     * @return \Yoti\Service\Profile\ActivityDetails
     *
     * @throws \Yoti\Exception\ActivityDetailsException
     * @throws \Yoti\Exception\ReceiptException
     */
    public function getActivityDetails($encryptedConnectToken): ActivityDetails
    {
        $receipt = $this->getReceipt($encryptedConnectToken);

        // Check response was successful
        if ($receipt->getSharingOutcome() !== self::OUTCOME_SUCCESS) {
            throw new ActivityDetailsException('Outcome was unsuccessful');
        }

        return new ActivityDetails($receipt, $this->pemFile);
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
            throw new ActivityDetailsException('Could not decrypt connect token.');
        }

        // Request endpoint
        $response = (new RequestBuilder())
            ->withBaseUrl(Config::get(Constants::CONNECT_API_URL_KEY, Constants::CONNECT_API_URL))
            ->withEndpoint(sprintf('/profile/%s', $token))
            ->withQueryParam('appId', $this->sdkId)
            ->withHeader(self::YOTI_AUTH_HEADER_KEY, $this->pemFile->getAuthKey())
            ->withGet()
            ->withPemFile($this->pemFile)
            ->withClient($this->httpClient)
            ->build()
            ->execute();

        $httpCode = $response->getStatusCode();
        if ($httpCode < 200 || $httpCode > 299) {
            throw new ActivityDetailsException("Server responded with {$httpCode}");
        }

        $result = Json::decode($response->getBody());

        $this->checkForReceipt($result);

        return new Receipt($result['receipt']);
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
     * @param array $response
     *
     * @throws \Yoti\Exception\ReceiptException
     */
    private function checkForReceipt(array $responseArr)
    {
        // Check receipt is in response
        if (!array_key_exists('receipt', $responseArr)) {
            throw new ReceiptException('Receipt not found in response');
        }
    }
}
