<?php

namespace Doomsta\Silex\User;

use Doomsta\Silex\User\Form\UserType;
use Silex\Application;
use Silex\ServiceProviderInterface;
use Symfony\Component\Security\Core\Encoder\BCryptPasswordEncoder;

require_once(__DIR__ . '/Model/Reposity/UserRepository.php'); //TODO


class UserServiceProvider implements ServiceProviderInterface
{

    private $defaultTemplates = array(
        'login' => '@User/login.twig',
        'view' => '@User/view.twig',
        'register' => '@User/register.twig',
        'recovery' => '@User/recovery.twig',
        'password' => '@User/password.twig',
       # 'layoutTemplate' => 'layouts/base.twig',
        'layoutTemplate' => '@User/Layout/base.twig',
        'mail_reset' => '@User/mail_reset.twig',
    );


    /**
     * Registers services on the given app.
     * This method should only be used to configure services and parameters.
     * It should not get services.
     * @param Application $app An Application instance
     */
    public function register(Application $app)
    {
        if(!isset($app['user.templates'] )) {
            $app['user.templates'] = array();
        }
        $app['user.templates'] = array_merge( $this->defaultTemplates, $app['user.templates']);

        $app['user.manager'] = $app->share(
            function ($app) {
                return new UserManager($app, $app['orm.em']);
            }
        );

        $app['user'] = $app::share(function ($app) {
            return ($app['user.manager']->getCurrentUser());
        });

        $app['user.em'] = $app::share(function () use ($app) {
            return $app['orm.em'];
        });

        $app['user.form.registration'] = $app->protect(function () use ($app) {
            #$encoder = $app['security.encoder_factory']->getEncoder(Entity::$user); //TODO use the generic getter
            $encoder = new BCryptPasswordEncoder(10);
            $type = new UserType($encoder);
            return $app['form.factory']->create($type);
        });

        $app['user.default_role'] = $app::share(function () use ($app) {
            return $app['user.em']->getRepository(Entity::$role)->findOneByRole('ROLE_USER');
        });
        $app['user.login.redirect'] = 'home';
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
            $app['twig.loader.filesystem']->addPath(__DIR__ . '/Views/', 'User');
        }
    }
}
