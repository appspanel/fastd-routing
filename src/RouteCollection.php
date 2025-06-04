<?php
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2016
 *
 * @link      https://www.github.com/janhuang
 * @link      http://www.fast-d.cn/
 */

namespace FastD\Routing;

use FastD\Routing\Exceptions\RouteNotFoundException;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Class RouteCollection
 *
 * @package FastD\Routing
 */
class RouteCollection
{
    public const ROUTES_CHUNK = 10;

    /**
     * @var array
     */
    protected $with = [];

    /**
     * @var array
     */
    protected $middleware = [];

    /**
     * @var Route
     */
    protected $activeRoute;

    /**
     * @var Route[]
     */
    public $staticRoutes = [];

    /**
     * @var Route[]
     */
    public $dynamicRoutes = [];

    /**
     * @var array
     */
    public $aliasMap = [];

    /**
     * @var int
     */
    protected $num = 1;

    /**
     * 路由分组计数器
     *
     * @var int
     */
    protected $index = 0;

    /**
     * @var array
     */
    protected $regexes = [];

    /**
     * @var string
     */
    protected $namespace;

    /**
     * RouteCollection constructor.
     * @param null $namespace
     */
    public function __construct($namespace = null)
    {
        $this->namespace = $namespace;
    }

    /**
     * @param          $path
     * @param callable $callback
     * @return RouteCollection
     */
    public function group($path, callable $callback)
    {
        $middleware = $this->middleware;

        if (is_array($path)) {
            $middlewareOptions = isset($path['middleware']) ? $path['middleware'] : [];

            if (is_array($middlewareOptions)) {
                $this->middleware = array_merge($this->middleware, $middlewareOptions);
            } else {
                $this->middleware[] = $middlewareOptions;
            }

            $path = isset($path['prefix']) ? $path['prefix'] : '';
        }

        array_push($this->with, $path);

        $callback($this);

        array_pop($this->with);
        $this->middleware = $middleware;

        return $this;
    }

    /**
     * @param $middleware
     * @param callable $callback
     * @return RouteCollection
     */
    public function middleware($middleware, callable $callback)
    {
        array_push($this->middleware, $middleware);

        $callback($this);

        array_pop($this->middleware);

        return $this;
    }

    /**
     * @param $callback
     * @return string
     */
    protected function concat($callback)
    {
        return !is_string($callback) ? $callback : $this->namespace . $callback;
    }

    /**
     * @param array{name: string, path: string}|string $path
     * @param $callback
     * @param array $defaults
     * @param string[]|string $hosts
     * @return Route
     */
    public function get($path, $callback, $hosts = [], array $defaults = [])
    {
        return $this->addRoute('GET', $path, $this->concat($callback), $hosts);
    }

    /**
     * @param array{name: string, path: string}|string $path
     * @param $callback
     * @param array $defaults
     * @param string[]|string $hosts
     * @return Route
     */
    public function post($path, $callback, $hosts = [], array $defaults = [])
    {
        return $this->addRoute('POST', $path, $this->concat($callback), $hosts);
    }

    /**
     * @param array{name: string, path: string}|string $path
     * @param $callback
     * @param array $defaults
     * @param string[]|string $hosts
     * @return Route
     */
    public function put($path, $callback, $hosts = [], array $defaults = [])
    {
        return $this->addRoute('PUT', $path, $this->concat($callback), $hosts);
    }

    /**
     * @param array{name: string, path: string}|string $path
     * @param $callback
     * @param array $defaults
     * @param string[]|string $hosts
     * @return Route
     */
    public function delete($path, $callback, $hosts = [], array $defaults = [])
    {
        return $this->addRoute('DELETE', $path, $this->concat($callback), $hosts);
    }

    /**
     * @param array{name: string, path: string}|string $path
     * @param $callback
     * @param array $defaults
     * @param string[]|string $hosts
     * @return Route
     */
    public function head($path, $callback, $hosts = [], array $defaults = [])
    {
        return $this->addRoute('HEAD', $path, $this->concat($callback), $hosts);
    }

    /**
     * @param array{name: string, path: string}|string $path
     * @param $callback
     * @param array $defaults
     * @param string[]|string $hosts
     * @return Route
     */
    public function options($path, $callback, $hosts = [], array $defaults = [])
    {
        return $this->addRoute('OPTIONS', $path, $this->concat($callback), $hosts);
    }

    /**
     * @param array{name: string, path: string}|string $path
     * @param $callback
     * @param array $defaults
     * @param string[]|string $hosts
     * @return Route
     */
    public function patch($path, $callback, $hosts = [], array $defaults = [])
    {
        return $this->addRoute('PATCH', $path, $this->concat($callback), $hosts);
    }

    /**
     * @param string $name
     * @param string $host
     * @return bool|Route
     */
    public function getRoute($name, $host = '')
    {
        if (!isset($this->aliasMap[$host])) {
            return false;
        }

        foreach ($this->aliasMap[$host] as $method => $routes) {
            if (isset($routes[$name])) {
                return $routes[$name];
            }
        }

        return false;
    }

    /**
     * @return Route
     */
    public function getActiveRoute()
    {
        return $this->activeRoute;
    }

    /**
     * @param string $method
     * @param array{name: string, path: string}|string $path
     * @param $callback
     * @param string[]|string $hosts
     * @return Route
     */
    public function createRoute($method, $path, $callback, $hosts)
    {
        /*
        if(!preg_match('#^(/sdk/.+$|.+/upload|/users?/(profile|privacy|register|login|logout|forgot|renewpassword|activation)|/policies)#', $path))
        {
            echo '['.posix_getpid().'] Adding route "'.$method.' '.$path.'", restricted to host(s): "'.implode('", "', (array)$hosts).'"'.PHP_EOL;
        }
        */

        return new Route($method, $path, $callback, $hosts);
    }

    /**
     * @param string $method
     * @param array{name: string, path: string}|string $path
     * @param $callback
     * @param string[]|string $hosts
     * @return Route
     */
    public function addRoute($method, $path, $callback, $hosts)
    {
        if (is_array($path)) {
            $name = $path['name'];
            $path = implode('/', $this->with) . $path['path'];
        } else {
            $name = $path = implode('/', $this->with) . $path;
        }

        $hosts = (array) $hosts;
        $hosts = [] === $hosts ? [''] : $hosts;
        /** @var string[] $hosts */

        foreach ($hosts as $host) {
            if (isset($this->aliasMap[$host][$method][$name])) {
                return $this->aliasMap[$host][$method][$name];
            }
        }

        $route = $this->createRoute($method, $path, $callback, $hosts);
        $route->withAddMiddleware($this->middleware);

        foreach ($hosts as $host) {
            if ($route->isStatic()) {
                $this->staticRoutes[$host][$method][$path] = $route;
            } else {
                $numVariables = count($route->getVariables());
                $numGroups = max($this->num, $numVariables);
                $this->regexes[$host][$method][] = $route->getRegex() . str_repeat('()', $numGroups - $numVariables);

                $this->dynamicRoutes[$host][$method][$this->index]['regex'] = '~^(?|' . implode('|', $this->regexes[$host][$method]) . ')$~';
                $this->dynamicRoutes[$host][$method][$this->index]['routes'][$numGroups + 1] = $route;

                ++$this->num;

                if (count($this->regexes[$host][$method]) >= static::ROUTES_CHUNK) {
                    ++$this->index;
                    $this->num = 1;
                    $this->regexes[$host][$method] = [];
                }

                unset($numGroups, $numVariables);
            }

            $this->aliasMap[$host][$method][$name] = $route;
        }

        return $route;
    }

    /**
     * @param ServerRequestInterface $serverRequest
     * @return Route
     * @throws RouteNotFoundException
     */
    public function match(ServerRequestInterface $serverRequest)
    {
        $method = $serverRequest->getMethod();
        $path = $serverRequest->getUri()->getPath();

        foreach ([$serverRequest->getUri()->getHost(), ''] as $host) {
            if (isset($this->staticRoutes[$host][$method][$path])) {
                return $this->activeRoute = $this->staticRoutes[$host][$method][$path];
            }
            else {
                $possiblePath = $path;

                if ('/' === substr($possiblePath, -1)) {
                    $possiblePath = rtrim($possiblePath, '/');
                } else {
                    $possiblePath .= '/';
                }

                if (isset($this->staticRoutes[$host][$method][$possiblePath])) {
                    return $this->activeRoute = $this->staticRoutes[$host][$method][$possiblePath];
                }

                unset($possiblePath);
            }

            if (isset($this->dynamicRoutes[$host][$method]) && false !== $route = $this->matchDynamicRoute($serverRequest, $method, $path, $host)) {
                return $this->activeRoute = $route;
            }
        }

        throw new RouteNotFoundException($path);
    }

    /**
     * @param ServerRequestInterface $serverRequest
     * @param string $method
     * @param string $path
     * @param string $host
     * @return bool|Route
     */
    protected function matchDynamicRoute(ServerRequestInterface $serverRequest, $method, $path, $host)
    {
        foreach ($this->dynamicRoutes[$host][$method] as $data) {
            /** @var array{regex: string, routes: \FastD\Routing\Route[]} $data */
            if (!preg_match($data['regex'], $path, $matches)) {
                continue;
            }

            $route = $data['routes'][count($matches)];

            preg_match('~^' . $route->getRegex() . '$~', $path, $match);

            $match = array_slice($match, 1, count($route->getVariables()));
            $attributes = array_combine($route->getVariables(), $match);
            $attributes = array_filter($attributes);
            $route->mergeParameters($attributes);

            foreach ($route->getParameters() as $key => $attribute) {
                $serverRequest->withAttribute($key, $attribute);
            }

            return $route;
        }

        return false;
    }

    /**
     * @param $name
     * @param array $parameters
     * @param string $format
     * @return string
     * @throws \Exception
     */
    public function generateUrl($name, array $parameters = [], $format = '')
    {
        if (false === ($route = $this->getRoute($name))) {
            throw new RouteNotFoundException($name);
        }

        if (!empty($format)) {
            $format = '.' . $format;
        } else {
            $format = '';
        }

        if ($route->isStaticRoute()) {
            return $route->getPath() . $format;
        }

        $parameters = array_merge($route->getParameters(), $parameters);
        $queryString = [];

        foreach ($parameters as $key => $parameter) {
            if (!in_array($key, $route->getVariables())) {
                $queryString[$key] = $parameter;
                unset($parameters[$key]);
            }
        }

        $search = array_map(function ($v) {
            return '{' . $v . '}';
        }, array_keys($parameters));

        $replace = $parameters;

        $path = str_replace($search, $replace, $route->getPath());

        if (false !== strpos($path, '[')) {
            $path = str_replace(['[', ']'], '', $path);
            $path = rtrim(preg_replace('~(({.*?}))~', '', $path), '/');
        }

        return $path . $format . ([] === $queryString ? '' : '?' . http_build_query($queryString));
    }
}
