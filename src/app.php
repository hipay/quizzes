<?php

use Himedia\QCM\Controller;
use Silex\Provider\FormServiceProvider;
use Silex\Provider\HttpCacheServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\TranslationServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\ValidatorServiceProvider;

require_once __DIR__ . '/inc/bootstrap.php';

$app = new Silex\Application();
$app['config'] = $aConfig;
$app['debug'] = true;
$app['cache.max_age'] = 0;
$app['cache.expires'] = 0;
// $app['cache.dir'] = __DIR__ . '/../cache';

// Registers Symfony Cache component extension
$app->register(new HttpCacheServiceProvider(), array(
//     'http_cache.cache_dir'  => $app['cache.dir'],
    'http_cache.options'    => array(
        'allow_reload'      => true,
        'allow_revalidate'  => true
    )));

// Default cache values
$app['cache.defaults'] = array(
    'Cache-Control' => sprintf(
        'no-cache, max-age=%d, s-maxage=%d, must-revalidate, proxy-revalidate',
        $app['cache.max_age'],
        $app['cache.max_age']
    ),
    'Expires'       => date('r', time() + $app['cache.expires'])
);

$app->register(new ValidatorServiceProvider());
$app->register(new FormServiceProvider());

$app->register(new TranslationServiceProvider(), array(
    'locale' => 'fr',
    'locale_fallback' => 'fr',
    'translation.class_path' =>  __DIR__ . '/../vendor/symfony/src',
    'translator.messages' => array()
)) ;

$app->register(new TwigServiceProvider(), array(
    'twig.path' => __DIR__ . '/../src/views',
    'twig.options' => array('debug' => true)
));

$app->register(new SessionServiceProvider());
$app['session']->start();
if (! $app['session']->has('state')) {
    $app['session']->set('state', 'need-quiz');
    $app['session']->set('seed', md5(microtime().rand()));
}

new Controller($app);

return $app;
