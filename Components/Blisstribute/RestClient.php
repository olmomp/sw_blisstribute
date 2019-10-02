<?php

use GuzzleHttp\Client;
use Psr\Http\Message\ResponseInterface;

/**
 * HTTP client for the Blisstribute REST API.
 *
 * @package Shopware\Components\Blisstribute\RestClient
 * @since   1.0.0
 */
class Shopware_Components_Blisstribute_RestClient
{
    private $httpClient;
    private const API_VERSION = 'v1';

    /**
     * Construct a new Blisstribute REST client.
     *
     * @param string $baseUrl
     */
    public function __construct(string $baseUrl)
    {
        $this->httpClient = new Client([
            'base_url' => sprintf('%s/%s/', $baseUrl, self::API_VERSION),
            'defaults' => [
                'timeout' => 5,
                'headers' => [
                    'Accept'       => 'application/json',
                    'Content-Type' => 'application/json',
                ],
            ]
        ]);
    }

    /**
     * Create a new resource.
     *
     * @param $path
     * @param array $data
     * @param array $query
     * @return mixed|ResponseInterface
     */
    public function post($path, $data = [], $query = [])
    {
        return $this->httpClient
                    ->post($path, ['json' => $data, 'query' => $query]);
    }

    /**
     * Authenticate using an API Key. Stores a JWT token internally, which is valid for 8 hours and must be refreshed
     * afterwards.
     *
     * @param string $apiKey
     * @return string
     * @throws Exception
     */
    public function authenticateWithApiKey(string $client, string $apiKey): string
    {
        return $this->authenticate(['client' => $client, 'apiKey' => $apiKey]);
    }

    /**
     * Authenticate using an API Key. Stores a JWT token internally, which is valid for 8 hours and must be refreshed
     * afterwards.
     *
     * @param string $client
     * @param string $user
     * @param string $password
     * @return string
     * @throws Exception
     */
    public function authenticateWithClientUserPassword(string $client, string $user, string $password): string
    {
        return $this->authenticate([
            'client'   => $client,
            'user'     => $user,
            'password' => $password,
        ]);
    }

    /**
     * @param array $credentials
     * @return string
     * @throws Exception
     */
    private function authenticate(array $credentials): string
    {
        // If the client is already authenticated return the token without reauthenticating.
        // WARNING: This means the client is not yet designed to be used in long-running processes as the token will
        //          expire after 8 hours and would cause an 401 error upon request of a protected endpoint.
        // TODO: Handle expiration cases / exceptions by refreshing the token automatically.
        if (!empty($this->httpClient->getDefaultOption('headers/Authorization'))) {
            return $this->httpClient->getDefaultOption('headers/Authorization');
        }

        $response = $this->post('login/authenticate', $credentials)->json();
        $token    = $response['response']['jwt'] ?? false;

        if (!$token) {
            throw new Exception('Response missing token');
        }

        $this->httpClient->setDefaultOption('headers/Authorization', 'Bearer ' . $token);

        return $token;
    }

    /**
     * Creates or updates one or multiple products, depending on whether a product contains a field 'vhsArticleNumber'
     * with a unique VHS identifier or not.
     *
     * @param array $products
     * @return mixed|ResponseInterface
     */
    public function createOrUpdateProduct(array $products)
    {
        return $this->post('product/createOrUpdate', ['productData' => $products]);
    }

    /**
     * Creates a single order.
     *
     * @param array $order
     * @return mixed|ResponseInterface
     */
    public function createOrder(array $order)
    {
        return $this->post('order/create', ['orderData' => $order]);
    }
}
