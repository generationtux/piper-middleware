<?php
namespace GenTux\Piper\Middleware;

use Closure;
use Exception;
use GuzzleHttp\Client;
use Illuminate\Http\Request;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Collection;
use GuzzleHttp\Exception\RequestException;

class NotifyPiper
{
    public function handle(Request $req, Closure $next)
    {
        $resp = $next($req);
        if ($this->environmentIsExcluded(env('APP_ENV')) ||
            $this->urlIsExcluded($req) ||
            is_null(env('PIPER_URL', null)) ||
            is_null(env('PIPER_NAME', null))
        ) {
            return $resp;
        }
        try {
            (new Client())->post(env('PIPER_URL') . '/api/v1/request', [
                RequestOptions::TIMEOUT => .4,
                RequestOptions::HEADERS => ['Content-Type' => 'application/json'],
                RequestOptions::JSON => $this->buildRequestBody($req),
            ]);
        } catch (RequestException $e) {
            app('log')->info($e->getMessage());
        } catch (Exception $e) {
            app('log')->info($e->getMessage());
        }
        return $resp;
    }

    private function environmentIsExcluded($env)
    {
        switch (strtolower($env)) {
            case 'production':
            case 'qa':
                return false;
            default:
                return true;
        }
    }

    private function urlIsExcluded(Request $req)
    {
        return substr_count($req->fullUrl(), 'healthz') > 0;
    }

    private function buildRequestBody(Request $req)
    {
        $body = collect(['destination' => [
            'name' => env('PIPER_NAME'),
            'url' =>  $req->fullUrl(),
        ]]);
        return $this->setOriginBodyData(
            $body,
            null,
            $req->headers->get('referer', null)
        )->toArray();
    }

    private function setOriginBodyData(Collection $body, $name, $url)
    {
        if (is_null($name) && is_null($url)) {
            return $body;
        } else {
            $body['origin'] = [];
        }
        if (!is_null($name)) {
            $origin = $body['origin'];
            $origin['name'] = $name;
            $body['origin'] = $origin;
        }
        if (!is_null($url) && is_string($url)) {
            $origin = $body['origin'];
            $origin['url'] = $url;
            $body['origin'] = $origin;
        }
        return $body;
    }
}
