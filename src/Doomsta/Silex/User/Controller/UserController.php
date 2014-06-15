<?php

namespace Doomsta\Silex\User\Controller;

use Doomsta\Silex\User\Model\Entity\User;
use Doomsta\Silex\User\UserManager;
use LogicException;
use Silex\Application;
use Silex\ControllerCollection;
use Silex\ControllerProviderInterface;
use Silex\ServiceControllerResolver;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use InvalidArgumentException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

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

        $controllers->method('GET|POST')->match('/register', array($this, 'registerAction'))
            ->bind('user.register');

        $controllers->get('/login', array($this, 'loginAction'))
            ->bind('user.login');

        $controllers->method('GET|POST')->match('/login_check', function() {})
            ->bind('user.login_check');
        $controllers->get('/logout', function() {})
            ->bind('user.logout');

        return $controllers;
    }

    /**
     * @param array $options
     */
    public function setOptions(array $options)
    {

        if (array_key_exists('layout_template', $options)) {
            $this->layoutTemplate = $options['layout_template'];
        }
    }

    /**
     * @param string $layoutTemplate
     */
    public function setLayoutTemplate($layoutTemplate)
    {
        $this->layoutTemplate = $layoutTemplate;
    }

    /**
     * Login action.
     *
     * @param Application $app
     * @param Request $request
     * @return Response
     */
    public function loginAction(Application $app, Request $request)
    {
        return $app['twig']->render('@user/login.twig', array(
            'layout_template' => $this->layoutTemplate,
            'error' => $app['security.last_error']($request),
            'last_username' => $app['session']->get('_security.last_username'),
            'allowRememberMe' => isset($app['security.remember_me.response_listener']),
        ));
    }

    /**
     * Register action.
     *
     * @param Application $app
     * @param Request $request
     * @return Response
     */
    public function registerAction(Application $app, Request $request)
    {

        if ($request->isMethod('POST')) {
            try {
                $user = $this->createUserFromRequest($request);
                $this->userManager->insert($user);
                $app['session']->getFlashBag()->set('alert', 'Account created.');

                // Log the user in to the new account.
                if (null !== ($current_token = $app['security']->getToken())) {
                    $providerKey = method_exists($current_token, 'getProviderKey') ? $current_token->getProviderKey() : $current_token->getKey();
                    $token = new UsernamePasswordToken($user, null, $providerKey);
                    $app['security']->setToken($token);
                }

                return $app->redirect($app['url_generator']->generate('user.view', array('id' => $user->getId())));

            } catch (InvalidArgumentException $e) {
                $error = $e->getMessage();
            }
        }

        return $app['twig']->render('@user/register.twig', array(
            'layout_template' => $this->layoutTemplate,
            'error' => isset($error) ? $error : null,
            'name' => $request->request->get('name'),
            'email' => $request->request->get('email'),
        ));
    }

    /**
     * @param Request $request
     * @return User
     * @throws InvalidArgumentException
     */
    protected function createUserFromRequest(Request $request)
    {
        if ($request->request->get('password') != $request->request->get('confirm_password')) {
            throw new InvalidArgumentException('Passwords don\'t match.');
        }
        $user = new User($request->get('name'));
        $user->setEmail($request->get('email'));
        $user->setPassword($request->get('password'));
        $user = $this->userManager->createUser($request->get('name'), $request->get('password'), $request->get('email'));
        $errors = $this->userManager->validate($user);
        if (!empty($errors)) {
            throw new InvalidArgumentException(implode("\n", $errors));
        }
        return $user;
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
        return $app['twig']->render('@user/view.twig', array(
            'layout_template' => $this->layoutTemplate,
            'user' => $user,
        ));

    }

    public function viewSelfAction(Application $app) {
        if (!$app['user']) {
            return $app->redirect($app['url_generator']->generate('user.login'));
        }
        return $app->redirect($app['url_generator']->generate('user.view', array('id' => $app['user']->getId())));
    }


    public function listAction(Application $app, Request $request)
    {
        return $app['twig']->render('@user/list.twig', array(
            'layout_template' => $this->layoutTemplate,
            'users' => $app['user.manager']->getAllUsers()
        ));
    }
}