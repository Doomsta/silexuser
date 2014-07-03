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
class UserController implements ControllerProviderInterface
{

    /** @var UserManager */
    protected $userManager;

    protected $layoutTemplate = '@user/Layout/base.twig';


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

        // new

        $controllers->get('/login', array($this, 'loginAction'))
            ->bind('user.login');
        $controllers->match('/register', array($this, 'registerAction'))
            ->bind('user.register');
        $controllers->match('/checkLogin', '')
            ->bind('user.checkLogin');
        $controllers->match('/recovery', array($this, 'recoveryAction'))
            ->bind('user.recovery');

        $controllers->get('/', array($this, 'viewSelfAction'))
            ->bind('user')
            ->before(function(Request $request) use ($app) {
                if (!$app['user']) {
                    throw new AccessDeniedException();
                }
            });
        $controllers->get('/{id}', array($this, 'viewAction'))
            ->bind('user.view')
            ->assert('id', '\d+');
        $controllers->get('/list', array($this, 'listAction'))
            ->bind('user.list');

        $controllers->get('/logout', function() {})
            ->bind('user.logout');

        return $controllers;
    }

    /**
     * Login action.
     *
     * @param Application $app
     * @return Response
     */
    public function loginAction(Application $app)
    {
        if ($app['security']->isGranted('IS_AUTHENTICATED_FULLY')) {
            $path = $app['session']->get('_security.global.target_path')
                ?: $app['url_generator']->generate($app['user.login.redirect']);

            return $app->redirect($path);
        }

        return $app['twig']->render($app['user.templates']['login'], [
            'layoutTemplate' => $app['user.templates']['layoutTemplate'],
            'error' => $app['security.last_error']($app['request']),
            'last_username' => $app['session']->get('_security.last_username'),
        ]);
    }

    /**
     * Register action.
     *
     * @param Application $app
     * @param Request $request
     * @return Response
     */
    public function registerAction(Request $request, Application $app)
    {

        /** @var Form $form */
        $form = $app['user.form.registration']();
        /** @var EntityManager $em */
        $em = $app['user.em'];
        /** @var SecurityContext $security */
        $security = $app['security'];

        $form->handleRequest($request);
        if ($form->isValid()) {
            $em->getConnection()->beginTransaction();
            try {
                $user = $form->getData();
                $user->addRole($app['user.default_role']);
                $em->persist($user);
                $em->flush();
                $em->getConnection()->commit();
                $security->setToken(new UsernamePasswordToken($user, null, 'global', $user->getRoles()));
                $path = $app['session']->get('_security.global.target_path')
                    ? : $app['url_generator']->generate($app['user.login.redirect']);

                return $app->redirect($path);
            } catch (ConnectionException $e) {
                $em->getConnection()->rollback();
                $em->close();
                $form->addError(new FormError(sprintf('Registration failed %s', $app['debug'] ? $e->getMessage() : null)));
            } catch (Exception $e) {
                $form->addError(new FormError(sprintf('Registration failed %s', $app['debug'] ? $e->getMessage() : null)));
            }
        }
        return $app['twig']->render($app['user.templates']['register'], [
            'layoutTemplate' => $app['user.templates']['layoutTemplate'],
            'form' => $form->createView(),
        ]);
    }


    /**
     * View user action.
     *
     * @param Application $app
     * @param Request $request
     * @param int $id
     * @return Response
     * @throws NotFoundHttpException if no user is found with that ID.
     */
    public function viewAction(Application $app, Request $request, $id)
    {
        $user = $this->userManager->getUser($id);
        if (!$user) {
            throw new NotFoundHttpException('No user was found with that ID.');
        }
        return $app['twig']->render($app['user.templates']['view'], array(
            'layoutTemplate' => $app['user.templates']['layoutTemplate'],
            'user' => $user,
        ));

    }

    public function viewSelfAction(Application $app)
    {
        if (!$app['user']) {
            return $app->redirect($app['url_generator']->generate('user.login'));
        }
        return $app->redirect($app['url_generator']->generate('user.view', array('id' => $app['user']->getId())));
    }

    public function recoveryAction(Application $app)
    {
        return $app['twig']->render(
            $app['user.templates']['recovery'], array(
            'layoutTemplate' => $app['user.templates']['layoutTemplate'],
        ));
    }


    public function listAction(Application $app, Request $request)
    {
        return $app['twig']->render('@User/list.twig', array(
            'layoutTemplate' => $app['user.templates']['layoutTemplate'],
            'users' => $app['user.manager']->getAllUsers()
        ));
    }
}