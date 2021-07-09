<?php


namespace App\Engine;



use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Log\Logger;


abstract class BaseRestAPI
{
    /**
     * @var array
     */
    protected $errors = [];

    /**
     * @var array
     */
    protected $methods = [];

    /**
     * @var Client $client
     */
    protected $client;

    /**
     * @var Logger $logger
     */
    protected $logger;

    /**
     * @var array $config
     */
    protected $config;

    protected $defaultRequestConfig;

    public function __construct(Client $client, Logger $logger)
    {
        $this->client = $client;
        $this->logger = $logger;
        $this->config = config("your.config");
        $this->defaultRequestConfig = [];
    }

    public function getMethodStructure(string $method)
    {
        $structure = !empty($this->methods[$method]['structure']) ? $this->methods[$method]['structure'] : null;
        return $structure;
    }

    public function request(string $method, array $params, array $pathParams = [], bool $async = false)
    {
        if (empty($this->methods[$method])) {
            $this->logger->channel('REST_API')->error($method.':error',
                [
                    'method' => $method,
                    'error' => 'Unknown method',
                    'logChannel' => 'REST_API',
                    'class' => __CLASS__,
                    'function' => __FUNCTION__,
                ]);
            throw new \Exception("REST API method {$method} not exists", 400);
        }
        $requestConfig = $this->defaultRequestConfig;
        $methodData = $this->methods[$method];

        if($methodData['type'] == 'POST') {
            if ($methodData['dataType'] == 'X-URLENCODED') {
                $requestConfig[RequestOptions::FORM_PARAMS] = $params;
            } else {
                $requestConfig[RequestOptions::BODY] = \json_encode($params);
            }
        } else if($methodData['type'] == 'GET') {
            $requestConfig[RequestOptions::QUERY] = $params;
        }

        if (!empty($pathParams)) {
            if (\count($pathParams) === substr_count($methodData['path'], '%s')) {
                $methodData['path'] = sprintf($methodData['path'], ...$pathParams);
            }
        }

        if (!empty($methodData['fullUrl'])) {
            $uri = $methodData['path'];
        } else {
            $uri = $this->config['host'].$methodData['path'];
        }

        try {
            // send or send async
            if ($async) {
                return $this->client->requestAsync($methodData['type'], $uri, $requestConfig);
            }

            $response = $this->client->request($methodData['type'], $uri, $requestConfig);
            if (env('API_ANSWERS_LOG') || !empty($methodData['log'])) {
                $this->logger->channel('API_ANSWERS')->info(get_called_class().':'.$method,
                    [
                        'method' => $method,
                        'params' => $params,
                        'pathParams' => $pathParams,
                        'answer' => (string)$response->getBody(),
                        'logChannel' => 'API_ANSWERS',
                        'class' => __CLASS__,
                        'function' => __FUNCTION__,
                    ]);
            }
        } catch(\Exception $e) {
            $this->errorHandler(
                $e,
                [
                    'method' => $method,
                    'params' => $params,
                    'type' => $methodData['type'],
                    'uri' => $uri
                ]
            );

        }

        $result = (string)$response->getBody();
        if ($methodData['answerType'] == 'json') {
            $result = \json_decode($result, true);
        }

        return $result;
    }

    abstract function errorHandler(\Exception $e, array $params);
}
