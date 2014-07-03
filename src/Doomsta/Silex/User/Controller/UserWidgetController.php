<?php

namespace Doomsta\Silex\User\Controller;

use Doctrine\DBAL\ConnectionException;
use Doctrine\ORM\EntityManager;
use Doomsta\Silex\User\Model\Entity\User;
use Doomsta\Silex\User\UserManager;
use Exception;
use LogicException;
use Silex\Application;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;
use Silex\ServiceControllerResolver;
use Symfony\Component\Form\Form;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use InvalidArgumentException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\SecurityContext;

/**
 * Controller with actions for handling form-based authentication and user management.
 *
 * @package Doomsta\Silex\User
 */
class UserWidgetController implements ControllerProviderInterface
{

    /** @var UserManager */
    protected $userManager;



    /**
     * Returns routes to connect to the given application.
     * @param Application $app An Application instance
     * @return ControllerCollection A ControllerCollection instance
     * @throws LogicException if ServiceController service provider is not registered.
     */
    public function connect(Application $app)
    {
        if (!$app['resolver'] instanceof ServiceControllerResolver) {
            throw new LogicException('You must enable the ServiceController service provider to be able to use these routes.');
        }

        $this->userManager = $app['user.manager'];

        /** @var ControllerCollection $controllers */
        $controllers = $app['controllers_factory'];

        $controllers->get('/widget/loggedin', array($this, 'loggedinWidgetAction'))
            ->bind('user.widget.loggedin');
        $controllers->get('/widget/user', array($this, 'userWidgetAction'))
            ->bind('user.widget.user');
        $controllers->get('/widget/register', array($this, 'registerWidgetAction'))
            ->bind('user.widget.register');
        $controllers->get('/widget/login', array($this, 'loginWidgetAction'))
            ->bind('user.widget.login');
        return $controllers;
    }

    public function loggedinWidgetAction(Application $app)
    {
        return $app['twig']->render('@User/Widget/loggedinWidget.twig',
            array('user' => $app['user'])
        );
    }

    public function viewWidgetAction(Application $app)
    {
        return $app['twig']->render('@User/Widget/viewWidget.twig',
            array('user' => $app['user'])
        );
    }

    public function passwordWidgetAction(Application $app)
    {
        return $app['twig']->render('@User/Widget/passwordWidget.twig',
            array()
        );
    }

    public function loginWidgetAction(Application $app)
    {
        return $app['twig']->render('@User/Widget/loginWidget.twig',
            array()
        );
    }

    public function registerWidgetAction(Application $app)
    {
        return $app['twig']->render('@User/Widget/registerWidget.twig',
            array(
                'form' => $app['user.form.registration']()->createView()
            )
        );
    }
}
