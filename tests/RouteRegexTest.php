<?php
/**
 * @author    jan huang <bboyjanhuang@gmail.com>
 * @copyright 2016
 *
 * @link      https://www.github.com/janhuang
 * @link      http://www.fast-d.cn/
 */

use FastD\Routing\RouteRegex;
use PHPUnit\Framework\TestCase;

class RouteRegexTest extends TestCase
{
    public function testRegex()
    {
        $regex = new RouteRegex('/test/{name:\d+}/[{age}]');
        $this->assertMatchesRegularExpression('~^('.$regex->getRegex().')$~', '/test/18');
        $this->assertEquals(['name', 'age'], $regex->getVariables());
        $this->assertEquals([
            'name' => '\d+',
            'age' => '[^/]+'
        ], $regex->getRequirements());
    }

    public function testMatchingLastCharset()
    {
        $regex = new RouteRegex('/[{name}]/');
        $this->assertMatchesRegularExpression('~^' . $regex->getRegex() . '$~', '/foo');
        $this->assertMatchesRegularExpression('~^'.$regex->getRegex().'$~', '/foo');

        $regex = new RouteRegex('/{name}');
        $this->assertMatchesRegularExpression('~^' . $regex->getRegex() . '$~', '/foo');
        $this->assertMatchesRegularExpression('~^' . $regex->getRegex() . '$~', '/foo/');
    }

    public function testFuzzyMatchingRoute()
    {
        $regex = new RouteRegex('/*');
        $this->assertMatchesRegularExpression('~^' . $regex->getRegex() . '$~', '/test/18');

        $regex = new RouteRegex('/abc/*');
        $this->assertMatchesRegularExpression('~^' . $regex->getRegex() . '$~', '/abc/foo/bar');

        $regex = new RouteRegex('/foo/*');
        $this->assertMatchesRegularExpression('~^' . $regex->getRegex() . '$~', '/foo/foo/bar');
    }

    public function testRouteStaticOrDynamic()
    {
        $regex = new RouteRegex('/test/{name:\d+}/[{age}]');
        $this->assertFalse($regex->isStatic());

        $regex = new RouteRegex('/foo');
        $this->assertTrue($regex->isStatic());
    }

    public function testRegexVariables()
    {
        $regex = new RouteRegex('/test/{name:\d+}/[{age}]');
        $this->assertEquals([
            'name', 'age'
        ], $regex->getVariables());
    }

    public function testRegexRequirements()
    {
        $regex = new RouteRegex('/test/{name:\d+}/[{age}]');
        $this->assertEquals([
            'name' => '\d+',
            'age' => '[^/]+'
        ], $regex->getRequirements());
    }
}
