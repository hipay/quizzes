<?php

/*
 * This file is part of Hi-Media Quizzes.
 *
 * Hi-Media Quizzes is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Hi-Media Quizzes is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Hi-Media Quizzes. If not, see <http://www.gnu.org/licenses/>.
 */

use Himedia\QCM\Controllers\User;
use Himedia\QCM\Controllers\Admin;
use Himedia\QCM\Tools;
use Himedia\QCM\UserProvider;
use Silex\Provider\FormServiceProvider;
use Silex\Provider\HttpCacheServiceProvider;
use Silex\Provider\SecurityServiceProvider;
use Silex\Provider\SessionServiceProvider;
use Silex\Provider\TranslationServiceProvider;
use Silex\Provider\TwigServiceProvider;
use Silex\Provider\ValidatorServiceProvider;
use Silex\Provider\UrlGeneratorServiceProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Encoder\MessageDigestPasswordEncoder;

use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;

require_once __DIR__ . '/inc/bootstrap.php';

$app = new Silex\Application();
$app['config'] = $aConfig;
$app['debug'] = true;
$app['cache.max_age'] = 0;
$app['cache.expires'] = 0;

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
$app->register(new UrlGeneratorServiceProvider());

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
$oFilter = new Twig_SimpleFilter('score_format', function (Twig_Environment $oEnv, $mNumber, $iDecimal=null) {
    $aDefaults = $oEnv->getExtension('core')->getNumberFormat();
    if (null === $iDecimal) {
        $iDecimal = $aDefaults[0];
    }
    $sDecimalPoint = $aDefaults[1];
    $sThousandSep = $aDefaults[2];
    $sFormatted = number_format((float)$mNumber, $iDecimal, $sDecimalPoint, $sThousandSep);
    return rtrim(rtrim($sFormatted, '0'), $sDecimalPoint);
}, array('needs_environment' => true));
$app['twig']->addFilter($oFilter);
$oFilter = new Twig_SimpleFilter('values', function (array $array) {
    return array_values($array);
});
$app['twig']->addFilter($oFilter);

// Workaround à cause d'un bug Silex/Twig :
// The function "is_granted" does not exist…
$function = new Twig_SimpleFunction('is_granted', function($role) use ($app) {
    return $app['security']->isGranted($role);
});
$app['twig']->addFunction($function);

$app->register(new SessionServiceProvider());
if (! $app['session']->has('state')) {
    $app['session']->set('state', 'need-quiz');
    $sIp = Tools::getIP();
    $app['session']->set('ip', $sIp);
    $app['session']->set('host_name', gethostbyaddr($sIp));
    $app['session']->set('seed', md5(microtime().rand()));
}

$app->register(new SecurityServiceProvider(), array(
    'security.encoder.digest' => new MessageDigestPasswordEncoder('md5', false, 1),
    'security.firewalls' => array(
        'admin' => array(
            'pattern' => '^/admin/',
            'form' => array(
                'login_path' => '/login',
                'check_path' => '/admin/login_check',
                'default_target_path' => '/admin/sessions'
            ),
            'logout' => array('logout_path' => '/admin/logout'),
            'users' => $app->share(function () use ($app) {
                return new UserProvider($app, $app['config']['Himedia\QCM']['admin_accounts']);
            }),
        ),
        'main' => array(
            'pattern' => '^.*$',
            'anonymous' => true,
        )
    ),
    'security.access_rules' => array(
        array('^/admin', 'ROLE_ADMIN'),
        array('^.*$', 'IS_AUTHENTICATED_ANONYMOUSLY'),
    )
));

Request::enableHttpMethodParameterOverride();
$app->mount('/',      new User());
$app->mount('/admin', new Admin());
return $app;
