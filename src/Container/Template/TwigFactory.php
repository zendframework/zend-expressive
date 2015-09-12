<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       https://github.com/zendframework/zend-expressive for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-expressive/blob/master/LICENSE.md New BSD License
 */

namespace Zend\Expressive\Container\Template;

use Interop\Container\ContainerInterface;
use Twig_Environment as TwigEnvironment;
use Twig_Extension_Debug as TwigExtensionDebug;
use Twig_Loader_Filesystem as TwigLoader;
use Zend\Expressive\Router\RouterInterface;
use Zend\Expressive\Template\Twig;

/**
 * Create and return a Twig template instance.
 *
 * Optionally uses the service 'config', which should return an array. This
 * factory consumes the following structure:
 *
 * <code>
 * 'debug' => boolean,
 * 'templates' => [
 *     'cache_dir' => 'path to cached templates',
 *     'assets_url' => 'base URL for assets',
 *     'assets_version' => 'base version for assets',
 *     'extension' => 'file extension used by templates; defaults to html.twig',
 *     'paths' => [
 *         // namespace / path pairs
 *         //
 *         // Numeric namespaces imply the default/main namespace. Paths may be
 *         // strings or arrays of string paths to associate with the namespace.
 *     ],
 * ]
 * </code>
 */
class TwigFactory
{
    /**
     * @param ContainerInterface $container
     * @return Twig
     */
    public function __invoke(ContainerInterface $container)
    {
        $config   = $container->has('config') ? $container->get('config') : [];
        $debug    = array_key_exists('debug', $config) ? (bool) $config['debug'] : false;
        $config   = isset($config['templates']) ? $config['templates'] : [];
        $cacheDir = isset($config['cache_dir']) ? $config['cache_dir'] : false;

        // Create the engine instance
        $loader      = new TwigLoader();
        $environment = new TwigEnvironment($loader, [
            'cache'            => $debug ? false : $cacheDir,
            'debug'            => $debug,
            'strict_variables' => $debug,
            'auto_reload'      => $debug
        ]);

        // Add extensions
        if ($container->has(RouterInterface::class)) {
            $environment->addExtension(new Twig\TwigExtension(
                $container->get(RouterInterface::class),
                isset($config['assets_url']) ? $config['assets_url'] : '',
                isset($config['assets_version']) ? $config['assets_version'] : ''
            ));
        }

        if ($debug) {
            $environment->addExtension(new TwigExtensionDebug());
        }

        // Inject environment
        $twig = new Twig($environment, isset($config['extension']) ? $config['extension'] : 'html.twig');

        // Add template paths
        $allPaths = isset($config['paths']) && is_array($config['paths']) ? $config['paths'] : [];
        foreach ($allPaths as $namespace => $paths) {
            $namespace = is_numeric($namespace) ? null : $namespace;
            foreach ((array) $paths as $path) {
                $twig->addPath($path, $namespace);
            }
        }

        return $twig;
    }
}
