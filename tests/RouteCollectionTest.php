<?php

use FastD\Routing\RouteCollection;
use PHPUnit\Framework\TestCase;

/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2016
 *
 * @link      https://www.github.com/janhuang
 * @link      http://www.fast-d.cn/
 */
class RouteCollectionTest extends TestCase
{
    public function testNamespace()
    {
        $collection = new RouteCollection('\\Controller\\');
        $collection->get('/', 'IndexController@welcome');

        foreach($collection->getRoute('/') as $route) {
            $this->assertEquals('\\Controller\\IndexController@welcome', $route->getCallback());
        }
    }

    public function testMiddleware()
    {
        $collection = new RouteCollection();
        $collection->middleware('cors', function (RouteCollection $router) {
            $router->get('/', 'IndexController@welcome');
        });

        foreach($collection->getRoute('/') as $route) {
            $this->assertEquals(['cors'], $route->getMiddleware());
        }

        $collection->get('/welcome', 'IndexController@welcome');

        foreach($collection->getRoute('/welcome') as $route) {
            $this->assertEmpty($route->getMiddleware());
        }
    }

    public function testGroup()
    {
        $collection = new RouteCollection();
        $collection->group(['middleware' => 'test1'], function ($router) {
            $router->get('/', 'Demo@Demo')->withAddMiddleware('test');
        });

        foreach($collection->getRoute('/') as $route) {
            $this->assertEquals(['test1', 'test'], $route->getMiddleware());
        }
    }

    public function testRouteName()
    {
        $collection = new RouteCollection();
        $collection->get([
            'name' => 'demo',
            'path' => '/',
        ], 'IndexController@welcome');

        foreach($collection->getRoute('demo') as $route) {
            $this->assertEquals('/', $route->getPath());
        }
    }
}
