<?php

use FastD\Http\ServerRequest;
use FastD\Http\Uri;
use FastD\Routing\Exceptions\RouteNotFoundException;
use FastD\Routing\RouteCollection;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2016
 *
 * @link      https://www.github.com/janhuang
 * @link      http://www.fast-d.cn/
 */
class RouteCollectionTest extends TestCase
{
    public function testNamespace(): void
    {
        $collection = new RouteCollection('\\Controller\\');
        $collection->get('/', 'IndexController@welcome');
        $route = $collection->getRoute('/');
        $this->assertEquals('\\Controller\\IndexController@welcome', $route->getCallback());
    }

    public function testMiddleware(): void
    {
        $collection = new RouteCollection();
        $collection->middleware('cors', function(RouteCollection $router)
        {
            $router->get('/', 'IndexController@welcome');
        });
        $this->assertEquals(['cors'], $collection->getRoute('/')->getMiddleware());

        $collection->get('/welcome', 'IndexController@welcome');
        $this->assertEmpty($collection->getRoute('/welcome')->getMiddleware());
    }

    public function testGroup(): void
    {
        $collection = new RouteCollection();
        $collection->group(
            [
                'middleware' => 'test1',
            ],
            function($router)
            {
                $router->get('/', 'Demo@Demo')->withAddMiddleware('test');
            }
        );

        $route = $collection->getRoute('/');
        $this->assertEqualsCanonicalizing(['test1', 'test'], $route->getMiddleware());
    }

    public function testRouteName(): void
    {
        $collection = new RouteCollection();
        $collection->get([
            'name' => 'demo',
            'path' => '/',
        ], 'IndexController@welcome');

        $route = $collection->getRoute('demo');
        $this->assertEquals('/', $route->getPath());
    }

    // == Static routes

    public function testStaticRouteWithoutHost(): void
    {
        $collection = new RouteCollection();
        /** @var array<string, \FastD\Routing\Route> $routes */
        $routes = [
            'head'    => $collection->head('/news', 'NewsController@head'),
            'get'     => $collection->get('/news', 'NewsController@list'),
            'post'    => $collection->post('/news', 'NewsController@add'),
            'put'     => $collection->put('/news', 'NewsController@edit'),
            'patch'   => $collection->patch('/news', 'NewsController@partialEdit'),
            'delete'  => $collection->delete('/news', 'NewsController@delete'),
            'options' => $collection->options('/news', 'NewsController@options'),
        ];

        foreach(['HEAD', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method)
        {
            $expectedRoute = $routes[strtolower($method)];
            $this->assertEquals($expectedRoute, $collection->match($this->createMockServerRequest($method, '/news', 'example.com')));
        }
    }

    /**
     * @todo Tests with multiple hosts
     */
    public function testStaticRoutesWithHost(): void
    {
        $collection = new RouteCollection();
        /** @var array<string, \FastD\Routing\Route> $routes */
        $routes = [
            'head'    => $collection->head('/news', 'NewsController@head', 'example1.com'),
            'get'     => $collection->get('/news', 'NewsController@list', 'example1.com'),
            'post'    => $collection->post('/news', 'NewsController@add', 'example1.com'),
            'put'     => $collection->put('/news', 'NewsController@edit', 'example1.com'),
            'patch'   => $collection->patch('/news', 'NewsController@partialEdit', 'example1.com'),
            'delete'  => $collection->delete('/news', 'NewsController@delete', 'example1.com'),
            'options' => $collection->options('/news', 'NewsController@options', 'example1.com'),
        ];

        foreach(['HEAD', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method)
        {
            // Match is expected with the same host
            $expectedRoute = $routes[strtolower($method)];
            $this->assertEquals($expectedRoute, $collection->match($this->createMockServerRequest($method, '/news', 'example1.com')));

            // Mismatch is expected with a different host
            try
            {
                $collection->match($this->createMockServerRequest($method, '/news', 'example2.com'));
                $this->fail('Expected '.RouteNotFoundException::class.' for host mismatch.');
            }
            catch(RouteNotFoundException $exception)
            {
            }
        }
    }

    /**
     * @todo Tests with multiple hosts
     */
    public function testStaticRoutesWithAndWithoutHost(): void
    {
        $collection = new RouteCollection();
        /** @var array<string, \FastD\Routing\Route> $routes */
        $routes = [
            'head_without'    => $collection->head('/news', 'NewsController@head'),
            'get_without'     => $collection->get('/news', 'NewsController@list'),
            'post_without'    => $collection->post('/news', 'NewsController@add'),
            'put_without'     => $collection->put('/news', 'NewsController@edit'),
            'patch_without'   => $collection->patch('/news', 'NewsController@partialEdit'),
            'delete_without'  => $collection->delete('/news', 'NewsController@delete'),
            'options_without' => $collection->options('/news', 'NewsController@options'),
            'head_with'       => $collection->head('/news', 'NewsController@head', 'example1.com'),
            'get_with'        => $collection->get('/news', 'NewsController@list', 'example1.com'),
            'post_with'       => $collection->post('/news', 'NewsController@add', 'example1.com'),
            'put_with'        => $collection->put('/news', 'NewsController@edit', 'example1.com'),
            'patch_with'      => $collection->patch('/news', 'NewsController@partialEdit', 'example1.com'),
            'delete_with'     => $collection->delete('/news', 'NewsController@delete', 'example1.com'),
            'options_with'    => $collection->options('/news', 'NewsController@options', 'example1.com'),
        ];

        foreach(
            ['HEAD', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS']
            as $method
        )
        {
            // Specific route should match
            $expectedRoute = $routes[strtolower($method).'_with'];
            $this->assertEquals($expectedRoute, $collection->match($this->createMockServerRequest($method, '/news', 'example1.com')));

            // Generic route should match
            $expectedRoute = $routes[strtolower($method).'_without'];
            $this->assertEquals($expectedRoute, $collection->match($this->createMockServerRequest($method, '/news', 'example2.com')));
        }
    }

    // == Dynamic routes

    public function testDynamicRoutesWithoutRegexesAndWithoutHost(): void
    {
        $collection = new RouteCollection();
        /** @var array<string, \FastD\Routing\Route> $routes */
        $routes = [
            'head'    => $collection->head('/news/{id}', 'NewsController@head'),
            'get'     => $collection->get('/news/{id}', 'NewsController@list'),
            'post'    => $collection->post('/news/{id}', 'NewsController@add'),
            'put'     => $collection->put('/news/{id}', 'NewsController@edit'),
            'patch'   => $collection->patch('/news/{id}', 'NewsController@partialEdit'),
            'delete'  => $collection->delete('/news/{id}', 'NewsController@delete'),
            'options' => $collection->options('/news/{id}', 'NewsController@options'),
        ];

        foreach(['HEAD', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method)
        {
            $expectedRoute = $routes[strtolower($method)];
            $this->assertEquals($expectedRoute, $collection->match($this->createMockServerRequest($method, '/news/1', 'example.com')));
        }
    }

    public function testDynamicRoutesWithRegexesAndWithoutHost(): void
    {
        $collection = new RouteCollection();
        /** @var array<string, \FastD\Routing\Route> $routes */
        $routes = [
            'head'    => $collection->head('/news/{id:\d+}', 'NewsController@head'),
            'get'     => $collection->get('/news/{id:\d+}', 'NewsController@list'),
            'post'    => $collection->post('/news/{id:\d+}', 'NewsController@add'),
            'put'     => $collection->put('/news/{id:\d+}', 'NewsController@edit'),
            'patch'   => $collection->patch('/news/{id:\d+}', 'NewsController@partialEdit'),
            'delete'  => $collection->delete('/news/{id:\d+}', 'NewsController@delete'),
            'options' => $collection->options('/news/{id:\d+}', 'NewsController@options'),
        ];

        foreach(['HEAD', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method)
        {
            // Match is expected with an integer parameter
            $expectedRoute = $routes[strtolower($method)];
            $this->assertEquals($expectedRoute, $collection->match($this->createMockServerRequest($method, '/news/1', 'example.com')));

            // Mismatch is expected with a non-integer parameter
            try
            {
                $collection->match($this->createMockServerRequest($method, '/news/a', 'example.com'));
                $this->fail('Expected '.RouteNotFoundException::class.' for regex mismatch.');
            }
            catch(RouteNotFoundException $exception)
            {
            }
        }
    }

    /**
     * @todo Tests with multiple hosts
     */
    public function testDynamicRoutesWithoutRegexesAndWithHost(): void
    {
        $collection = new RouteCollection();
        /** @var array<string, \FastD\Routing\Route> $routes */
        $routes = [
            'head'    => $collection->head('/news/{id}', 'NewsController@head', 'example1.com'),
            'get'     => $collection->get('/news/{id}', 'NewsController@list', 'example1.com'),
            'post'    => $collection->post('/news/{id}', 'NewsController@add', 'example1.com'),
            'put'     => $collection->put('/news/{id}', 'NewsController@edit', 'example1.com'),
            'patch'   => $collection->patch('/news/{id}', 'NewsController@partialEdit', 'example1.com'),
            'delete'  => $collection->delete('/news/{id}', 'NewsController@delete', 'example1.com'),
            'options' => $collection->options('/news/{id}', 'NewsController@options', 'example1.com'),
        ];

        foreach(['HEAD', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method)
        {
            $expectedRoute = $routes[strtolower($method)];
            $this->assertEquals($expectedRoute, $collection->match($this->createMockServerRequest($method, '/news/1', 'example1.com')));
        }
    }

    /**
     * @todo Tests with multiple hosts
     */
    public function testDynamicRoutesWithRegexesAndWithHost(): void
    {
        $collection = new RouteCollection();
        /** @var array<string, \FastD\Routing\Route> $routes */
        $routes = [
            'head'    => $collection->head('/news/{id:\d+}', 'NewsController@head', 'example1.com'),
            'get'     => $collection->get('/news/{id:\d+}', 'NewsController@list', 'example1.com'),
            'post'    => $collection->post('/news/{id:\d+}', 'NewsController@add', 'example1.com'),
            'put'     => $collection->put('/news/{id:\d+}', 'NewsController@edit', 'example1.com'),
            'patch'   => $collection->patch('/news/{id:\d+}', 'NewsController@partialEdit', 'example1.com'),
            'delete'  => $collection->delete('/news/{id:\d+}', 'NewsController@delete', 'example1.com'),
            'options' => $collection->options('/news/{id:\d+}', 'NewsController@options', 'example1.com'),
        ];

        foreach(['HEAD', 'GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method)
        {
            // Match is expected with an integer parameter
            $expectedRoute = $routes[strtolower($method)];
            $this->assertEquals($expectedRoute, $collection->match($this->createMockServerRequest($method, '/news/1', 'example1.com')));

            // Mismatch is expected with a non-integer parameter
            try
            {
                $collection->match($this->createMockServerRequest($method, '/news/a', 'example1.com'));
                $this->fail('Expected '.RouteNotFoundException::class.' for regex mismatch.');
            }
            catch(RouteNotFoundException $exception)
            {
            }

            // Mismatch is expected with a different host
            try
            {
                $collection->match($this->createMockServerRequest($method, '/news/1', 'example2.com'));
                $this->fail('Expected '.RouteNotFoundException::class.' for host mismatch.');
            }
            catch(RouteNotFoundException $exception)
            {
            }
        }
    }

    // == Static and dynamic routes

    /**
     * @todo Tests with multiple hosts
     */
    public function testStaticAndDynamicRoutes(): void
    {
        $collection = new RouteCollection();
        /** @var array<string, array<string, \FastD\Routing\Route>> $routes */
        $routes = [
            ''             => [
                'upload_file'  => $collection->post('/upload-file', 'UploadController@uploadFile'),
                'upload_image' => $collection->post('/upload-image', 'UploadController@uploadImage'),
                'upload_video' => $collection->post('/upload-video', 'UploadController@uploadVideo'),
            ],
            'example1.com' => [
                'list'        => $collection->get('/news', 'NewsController@list', 'example1.com'),
                'fetch'       => $collection->get('/news/{id}', 'NewsController@fetch', 'example1.com'),
                'add'         => $collection->post('/news', 'NewsController@add', 'example1.com'),
                'edit'        => $collection->put('/news/{id:\d+}', 'NewsController@edit', 'example1.com'),
                'partialEdit' => $collection->patch('/news/{id:\d+}', 'NewsController@partialEdit', 'example1.com'),
                'delete'      => $collection->delete('/news/{id:\d+}', 'NewsController@delete', 'example1.com'),
            ],
            'example2.com' => [
                'list'        => $collection->get('/news', 'NewsController@list', 'example2.com'),
                'fetch'       => $collection->get('/news/{id}', 'NewsController@fetch', 'example2.com'),
                'add'         => $collection->post('/news', 'NewsController@add', 'example2.com'),
                'edit'        => $collection->put('/news/{id:\d+}', 'NewsController@edit', 'example2.com'),
                'partialEdit' => $collection->patch('/news/{id:\d+}', 'NewsController@partialEdit', 'example2.com'),
                'delete'      => $collection->delete('/news/{id:\d+}', 'NewsController@delete', 'example2.com'),
            ],
        ];

        foreach(['example1.com', 'example2.com'] as $host)
        {
            // Test specific routes
            $expectedRoute = $routes[$host]['list'];
            $this->assertEquals($expectedRoute, $collection->match($this->createMockServerRequest('GET', '/news', $host)));

            $expectedRoute = $routes[$host]['fetch'];
            $this->assertEquals($expectedRoute, $collection->match($this->createMockServerRequest('GET', '/news/1', $host)));

            $expectedRoute = $routes[$host]['add'];
            $this->assertEquals($expectedRoute, $collection->match($this->createMockServerRequest('POST', '/news', $host)));

            $expectedRoute = $routes[$host]['edit'];
            $this->assertEquals($expectedRoute, $collection->match($this->createMockServerRequest('PUT', '/news/1', $host)));

            try
            {
                $collection->match($this->createMockServerRequest('PUT', '/news/a', $host));
                $this->fail('Expected '.RouteNotFoundException::class.' for regex mismatch.');
            }
            catch(RouteNotFoundException $exception)
            {
            }

            $expectedRoute = $routes[$host]['partialEdit'];
            $this->assertEquals($expectedRoute, $collection->match($this->createMockServerRequest('PATCH', '/news/1', $host)));

            try
            {
                $collection->match($this->createMockServerRequest('PATCH', '/news/a', $host));
                $this->fail('Expected '.RouteNotFoundException::class.' for regex mismatch.');
            }
            catch(RouteNotFoundException $exception)
            {
            }

            $expectedRoute = $routes[$host]['delete'];
            $this->assertEquals($expectedRoute, $collection->match($this->createMockServerRequest('DELETE', '/news/1', $host)));

            try
            {
                $collection->match($this->createMockServerRequest('DELETE', '/news/a', $host));
                $this->fail('Expected '.RouteNotFoundException::class.' for regex mismatch.');
            }
            catch(RouteNotFoundException $exception)
            {
            }

            // Test common routes, match is expected only for POST method whatever the host is
            $expectedRoute = $routes['']['upload_file'];
            $this->assertEquals($expectedRoute, $collection->match($this->createMockServerRequest('POST', '/upload-file', $host)));

            foreach(['HEAD', 'GET', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method)
            {
                try
                {
                    $collection->match($this->createMockServerRequest($method, '/upload-file', $host));
                    $this->fail('Expected '.RouteNotFoundException::class.' for method mismatch.');
                }
                catch(RouteNotFoundException $exception)
                {
                }
            }

            $expectedRoute = $routes['']['upload_image'];
            $this->assertEquals($expectedRoute, $collection->match($this->createMockServerRequest('POST', '/upload-image', $host)));

            foreach(['HEAD', 'GET', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method)
            {
                try
                {
                    $collection->match($this->createMockServerRequest($method, '/upload-image', $host));
                    $this->fail('Expected '.RouteNotFoundException::class.' for method mismatch.');
                }
                catch(RouteNotFoundException $exception)
                {
                }
            }

            $expectedRoute = $routes['']['upload_video'];
            $this->assertEquals($expectedRoute, $collection->match($this->createMockServerRequest('POST', '/upload-video', $host)));

            foreach(['HEAD', 'GET', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'] as $method)
            {
                try
                {
                    $collection->match($this->createMockServerRequest($method, '/upload-video', $host));
                    $this->fail('Expected '.RouteNotFoundException::class.' for method mismatch.');
                }
                catch(RouteNotFoundException $exception)
                {
                }
            }
        }
    }

    /**
     * Mocks a server request.
     *
     * @param string $method The request's method (e.g., 'GET', 'POST').
     * @param string $path The request's path (e.g., '/news').
     * @param string $host The request's host (e.g., 'example.com').
     * @return \Psr\Http\Message\ServerRequestInterface The mocked request.
     */
    protected function createMockServerRequest(string $method, string $path, string $host): ServerRequestInterface
    {
        $uri = $this->createMock(Uri::class);
        $uri->method('getPath')->willReturn($path);
        $uri->method('getHost')->willReturn($host);

        $request = $this->createMock(ServerRequest::class);
        $request->method('getMethod')->willReturn($method);
        $request->method('getUri')->willReturn($uri);

        return $request;
    }
}
