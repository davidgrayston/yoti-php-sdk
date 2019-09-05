<?php

namespace Yoti\Demo\Http\Guzzle;

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request as GuzzleHttpRequest;
use Yoti\Http\RequestHandlerInterface;
use Yoti\Http\Request;
use Yoti\Http\Response;

/**
 * Handle HTTP requests.
 */
class RequestHandler implements RequestHandlerInterface
{
    /**
     * Execute HTTP request.
     *
     * @param Request $request
     *
     * @return \Yoti\Http\Response
     */
    public function execute(Request $request)
    {
        $body = null;
        if ($request->getPayload()) {
            $body = $request->getPayload()->getPayloadJSON();
        }

        $client = new Client();
        $response = $client->send(new GuzzleHttpRequest(
            $request->getMethod(),
            $request->getUrl(),
            $request->getHeaders(),
            $body
        ));

        return new Response(
            $response->getBody(),
            $response->getStatusCode()
        );
    }
}
