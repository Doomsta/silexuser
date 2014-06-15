<?php

namespace Doomsta\Silex\User;

require_once(__DIR__ . '/Model/Reposity/UserRepository.php'); //TODO

use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\ORM\Mapping\Driver\AnnotationDriver;
use Doomsta\Silex\User\Controller\UserController;
use Doomsta\Silex\User\Model\Repository\UserRepository;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Silex\ControllerProviderInterface;
use Silex\ControllerCollection;
use Silex\ServiceControllerResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\Voter\RoleHierarchyVoter;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class UserServiceProvider implements ServiceProviderInterface
{
    /**
     * Registers services on the given app.
     * This method should only be used to configure services and parameters.
     * It should not get services.
     * @param Application $app An Application instance
     */
    public function register(Application $app)
    {
        $app['user.manager'] = $app->share(
            function($app) {
                return new UserManager($app, $app['orm.em']);
            }
        );

        $app['user'] = $app->share(function($app) {
            return ($app['user.manager']->getCurrentUser());
        });

    }

    /**
     * Bootstraps the application.
     * This method is called after all services are registers
     * and should be used for "dynamic" configuration (whenever
     * a service must be requested).
     */
    public function boot(Application $app)
    {
        if ($app->offsetExists('twig.loader.filesystem')) {
            $app['twig.loader.filesystem']->addPath(__DIR__ . '/Views/', 'user');
        }
    }
}
