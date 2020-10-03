<?php

namespace MajidAlaeinia\Health\Checkers;

use GuzzleHttp\Client as Guzzle;
use GuzzleHttp\TransferStats;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Log;
use MajidAlaeinia\Health\Support\Result;

class Http extends Base
{
    /**
     * @return Result
     */
    protected $secure = false;

    /**
     * @var
     */
    protected $guzzle;

    /**
     * @var
     */
    private $totalTime;

    /**
     * @var
     */
    private $url;

    /**
     * HTTP Checker.
     *
     * @return Result
     */
    public function check()
    {
        try {
            foreach ($this->getResourceUrlArray() as $url) {
                if (Str::contains($url, ' ')) {
                    $token = Str::after($url, ' ');
                    $url   = Str::before($url, ' ');
                } else {
                    $token = '';
                }
                [$healthy, $message] = $this->checkWebPage(
                     $this->makeUrlWithScheme($url, $this->secure),
                     $token,
                     $this->secure
                );

                if (! $healthy) {
                    return $this->makeResult($healthy, $message);
                }
            }

            return $this->makeHealthyResult();
        } catch (\Exception $exception) {
            report($exception);

            return $this->makeResultFromException($exception);
        }
    }

    /**
     *  Get array of resource urls.
     *
     * @return array
     */
    private function getResourceUrlArray()
    {
        if (is_a($this->target->urls, Collection::class)) {
            return $this->target->urls->toArray();
        }

        return (array) $this->target->urls;
    }



    /**
     * Check web pages.
     *
     * @param      $url
     * @param      $token
     * @param bool $ssl
     *
     * @return array
     */
    private function checkWebPage($url, $token, $ssl = false)
    {
        $success = $this->requestSuccessful($url, $token, $ssl);

        return [$success, $success ? '' : $this->getErrorMessage()];
    }



    /**
     * Send an http request and fetch the response.
     *
     * @param $url
     * @param $token
     * @param $ssl
     *
     * @return mixed|\Psr\Http\Message\ResponseInterface
     */
    private function fetchResponse($url, $token, $ssl)
    {
        $this->url = $url;

        return (new Guzzle())->request(
             'GET',
             $this->url,
             $this->getConnectionOptions($token, $ssl)
        );
    }



    /**
     * Get http connection options.
     *
     * @param $token
     * @param $ssl
     *
     * @return array
     */
    private function getConnectionOptions($token, $ssl)
    {
        if ($token) {
            return [
                 'connect_timeout' => $this->getConnectionTimeout(),
                 'timeout' => $this->getConnectionTimeout(),
                 'verify' => $ssl,
                 'on_stats' => $this->onStatsCallback(),
                 'headers' => [
                      'Accept'        => 'application/json',
                      'Authorization' => 'Bearer ' . $token,
                 ],
            ];
        } else {
            return [
                 'connect_timeout' => $this->getConnectionTimeout(),
                 'timeout' => $this->getConnectionTimeout(),
                 'verify' => $ssl,
                 'on_stats' => $this->onStatsCallback(),
            ];
        }
    }



    /**
     * Get the error message.
     *
     * @return string
     */
    private function getErrorMessage()
    {
        $message = $this->target->resource->timeoutMessage;

        return sprintf(
             $message,
             $this->url,
             $this->totalTime,
             $this->getRoundtripTimeout()
        );
    }

    /**
     * The the connection timeout.
     *
     * @return int
     */
    private function getConnectionTimeout()
    {
        return $this->target->resource->connectionTimeout;
    }

    /**
     * The the roundtrip timeout.
     *
     * @return int
     */
    private function getRoundtripTimeout()
    {
        return $this->target->resource->roundtripTimeout;
    }

    /**
     * Make a url with a proper scheme.
     *
     * @param $url
     * @param $secure
     * @return mixed
     */
    private function makeUrlWithScheme($url, $secure)
    {
        return preg_replace(
             '|^((https?:)?\/\/)?(.*)|',
             'http'.($secure ? 's' : '').'://\\3',
             $url
        );
    }

    /**
     * Guzzle OnStats callback.
     *
     * @return \Closure
     */
    private function onStatsCallback()
    {
        return function (TransferStats $stats) {
            $this->totalTime = $stats->getTransferTime();
        };
    }



    /**
     * Send a request and get the result.
     *
     * @param $url
     * @param $token
     * @param $ssl
     *
     * @return bool
     * @internal param $response
     */
    private function requestSuccessful($url, $token, $ssl)
    {
        return
             $this->fetchResponse($url, $token, $ssl)->getStatusCode() == 200 &&
             ! $this->requestTimedout();
    }

    /**
     * Check if the request timed out.
     *
     * @return bool
     */
    private function requestTimedout()
    {
        return $this->totalTime > $this->getRoundtripTimeout();
    }
}
