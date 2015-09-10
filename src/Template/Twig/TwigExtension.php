<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Template\Twig;

use Twig_Extension;
use Twig_SimpleFunction;
use Zend\Expressive\Router\RouterInterface;

/**
 * Twig extension for rendering URLs and assets URLs from Expressive.
 *
 * @author Geert Eltink (https://xtreamwayz.github.io)
 */
class TwigExtension extends Twig_Extension
{
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * @var string
     */
    private $assetsUrl;

    /**
     * @var string
     */
    private $assetsVersion;

    /**
     * @param RouterInterface $router
     * @param string $assetsUrl
     * @param string $assetsVersion
     */
    public function __construct(
        RouterInterface $router,
        $assetsUrl,
        $assetsVersion
    ) {
        $this->router        = $router;
        $this->assetsUrl     = $assetsUrl;
        $this->assetsVersion = $assetsVersion;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'zend-expressive';
    }

    /**
     * @return Twig_SimpleFunction[]
     */
    public function getFunctions()
    {
        return [
            new Twig_SimpleFunction('path', [$this, 'renderUri']),
            new Twig_SimpleFunction('asset', [$this, 'renderAssetUrl']),
        ];
    }

    /**
     * Usage: {{ path('name', parameters) }}
     *
     * @param $name
     * @param array $parameters
     * @param bool $relative
     * @return string
     */
    public function renderUri($name, $parameters = [], $relative = false)
    {
        return $this->router->generateUri($name, $parameters);
    }

    /**
     * Usage: {{ asset('path/to/asset/name.ext', version=3) }}
     *
     * @param $path
     * @param null $packageName
     * @param bool $absolute
     * @param null $version
     * @return string
     */
    public function renderAssetUrl($path, $packageName = null, $absolute = false, $version = null)
    {
        return sprintf(
            '%s%s?v=%s',
            $this->assetsUrl,
            $path,
            ($version) ? $version : $this->assetsVersion
        );
    }
}
