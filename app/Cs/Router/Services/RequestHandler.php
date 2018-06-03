<?php
namespace Cs\Router\Services;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Cs\Router\Exception\RouteException;
use Cs\Router\Util\Assert;

class RequestHandler extends Assert {
    protected $routes;
    protected $app;
    protected $containers;

    public function assignRoutesToService(): void 
    {
        $routes = $this->routes;
        foreach ($routes as $route) {
            $this->isValid($route);
            $map['url'] = $route['uri'];
            list($service, $func) = explode("->", $route['invoke']);
            $map['method'] = $route['method'];
            $map['service'] = $service;
            $map['func'] = $func;
            $this->assignService($map);
        }
    }

    private function isValid(Array $route): void 
    {
        $this->isHashArray($route, 'each.route.must.have.array');
        $this->isArrayKeyExist('invoke', $route, 'invoke.is.not.found.in.route');
        $this->isArrayKeyExist('uri', $route, 'uri.is.not.found');
        $this->isArrayKeyExist('method', $route, 'method.is.not.found');
        if (!preg_match('/[a-zA-Z]{3,15}(->)[a-zA-Z]{5,}/', $route['invoke'])) {
            throw new InvalidRoute('invoke.route.is.invalid');
        }
        
        list($service, $func) = explode("->", $route['invoke']);
        $this->isInvokeHasValidCallback($service, $func);
    }

    public function isInvokeHasValidCallback($class, $method): void 
    {
        $msg = sprintf('func.%s.is.not.found', $method);
        $this->hasMethod($this->containers[$class], $method, $msg);
        $msg = sprintf('func.%s.is.not.callable', $method);
        $this->isCallable($this->containers[$class], $method, $msg);
    }

    private function assignService(Array $map): void 
    {
        $instance = $this;
        $callable = function (
            Request $request, Response $response, $args
        ) use ($map, $instance) {
            $args = call_user_func([$instance, 'getPayload'], $request, $args);
            $result = call_user_func(
                [$instance->containers[$map['service']], $map['func']], $args
            );

            return call_user_func([$instance, 'sendResponse'], $response, $result);
        };

        $pattern = $map['url'];
        $this->app->map([$map['method']], $pattern, $callable);
    }

    public function getPayload($request, $args): string 
    {
        if ($request->isPost() === true) {
            return $request->getParsedBody();
        }

        if ($request->isGet() === true) {
            return $this->getGetPayload($request, $args);
        }

        throw new Exception("invalid.request.method");
    }

    public function getGetPayload($request, $args): string 
    {
        if (is_array($args) === true && count($args) > 0) {
            return $args;
        }

        return $request->getQueryParams();
    }

    public function sendResponse($response, $result): string 
    {
        $message = $result ?? 'found.no.response';
        $status = $result['status'] ?? 'success';
        $data = [
            'status' => $status,
            'message' => $message
        ];

        return $response->withJson($data);
    }
}
