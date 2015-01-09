<?php

namespace Playbloom\Bundle\GuzzleBundle\DataCollector;

use GuzzleHttp\Subscriber\History;

use GuzzleHttp\Message\RequestInterface as GuzzleRequestInterface;
use GuzzleHttp\Message\ResponseInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * GuzzleDataCollector.
 *
 * @author Ludovic Fleury <ludo.fleury@gmail.com>
 */
class GuzzleDataCollector extends DataCollector
{
    private $profiler;
    private $storage;

    public function __construct(History $profiler, \SplObjectStorage $storage)
    {
        $this->profiler = $profiler;
        $this->storage = $storage;
    }

    /**
     * {@inheritdoc}
     */
    public function collect(Request $request, Response $response, \Exception $exception = null)
    {
        $data = array(
            'calls'       => array(),
            'error_count' => 0,
            'methods'     => array(),
            'total_time'  => 0,
        );

        /**
         * Aggregates global metrics about Guzzle usage
         *
         * @param array $request
         * @param array $response
         * @param array $time
         * @param bool  $error
         */
        $aggregate = function ($request, $response, $time, $error) use (&$data) {

            $method = $request['method'];
            if (!isset($data['methods'][$method])) {
                $data['methods'][$method] = 0;
            }

            $data['methods'][$method]++;
            $data['total_time'] += $time['total'];
            $data['error_count'] += (int) $error;
        };

        foreach ($this->profiler as $call) {
            $request = $this->collectRequest($call['request']);
            $response = $this->collectResponse($call['response']);
            $time = $this->collectTime($call['response']);
            $error = $this->isError($call['response']);

            $aggregate($request, $response, $time, $error);

            $data['calls'][] = array(
                'request' => $request,
                'response' => $response,
                'time' => $time,
                'error' => $error
            );
        }

        $this->data = $data;
    }

    /**
     * @return array
     */
    public function getCalls()
    {
        return isset($this->data['calls']) ? $this->data['calls'] : array();
    }

    /**
     * @return int
     */
    public function countErrors()
    {
        return isset($this->data['error_count']) ? $this->data['error_count'] : 0;
    }

    /**
     * @return array
     */
    public function getMethods()
    {
        return isset($this->data['methods']) ? $this->data['methods'] : array();
    }

    /**
     * @return int
     */
    public function getTotalTime()
    {
        return isset($this->data['total_time']) ? $this->data['total_time'] : 0;
    }

    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'guzzle';
    }

    /**
     * Collect & sanitize data about a Guzzle request
     *
     * @param \GuzzleHttp\Message\RequestInterface $request
     *
     * @return array
     */
    private function collectRequest(GuzzleRequestInterface $request)
    {
        $body = (string) $request->getBody();

        return array(
            'headers' => $request->getHeaders(),
            'method'  => $request->getMethod(),
            'scheme'  => $request->getScheme(),
            'host'    => $request->getHost(),
            'path'    => $request->getPath(),
            'query'   => (string) $request->getQuery(),
            'body'    => $body
        );
    }

    /**
     * Collect & sanitize data about a Guzzle response
     *
     * @param ResponseInterface $response
     *
     * @return array
     */
    private function collectResponse(ResponseInterface $response)
    {
        $body = (string) $response->getBody();

        return array(
            'statusCode'   => $response->getStatusCode(),
            'reasonPhrase' => $response->getReasonPhrase(),
            'headers'      => $response->getHeaders(),
            'body'         => $body
        );
    }

    /**
     * Collect time for a Guzzle request
     *
     * @param \GuzzleHttp\Message\ResponseInterface $response
     *
     * @return array
     */
    private function collectTime(ResponseInterface $response)
    {
        return $this->storage->offsetGet($response);
    }

    /**
     * Checks if HTTP Status code is a Client Error (4xx)
     *
     * @param \GuzzleHttp\Message\ResponseInterface $response
     *
     * @return bool
     */
    private function isClientError(ResponseInterface $response)
    {
        return $response->getStatusCode() >= 400 && $response->getStatusCode() < 500;
    }

    /**
     * Checks if HTTP Status code is Server OR Client Error (4xx or 5xx)
     *
     * @param \GuzzleHttp\Message\ResponseInterface $response
     *
     * @return boolean
     */
    private function isError(ResponseInterface $response)
    {
        return $this->isClientError($response) || $this->isServerError($response);
    }

    /**
     * Checks if HTTP Status code is Server Error (5xx)
     *
     * @param \GuzzleHttp\Message\ResponseInterface $response
     *
     * @return bool
     */
    private function isServerError(ResponseInterface $response)
    {
        return $response->getStatusCode() >= 500 && $response->getStatusCode() < 600;
    }
}
