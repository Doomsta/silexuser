<?php
/**
 * Created by IntelliJ IDEA.
 * User: arandel
 * Date: 13.06.14
 * Time: 21:48
 */

namespace Doomsta\Silex\User;



use Doctrine\ORM\EntityManager;
use Doomsta\Silex\User\Model\Entity\User;
use Doomsta\Silex\User\Model\Repository\UserRepository;
use Silex\Application;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UsernameNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UserManager implements UserProviderInterface
{
    /**
     * @var \Silex\Application
     */
    private $app;

    /** @var  UserRepository */
    private $repo;
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $entityManager;
    protected $supportedClass = 'Doomsta\Silex\User\Model\Entiy\User';

    public function __construct(Application $app, EntityManager $entityManager)
    {
        $this->app = $app;
        $this->repo = $entityManager->getRepository('Doomsta\Silex\User\Model\Entity\User');
        $this->entityManager = $entityManager;

    }

    /**
     * @param array $criteria
     * @return null|User
     */
    public function getUserBy(array $criteria)
    {
        return $this->repo->findOneBy($criteria);
    }

    /**
     * @param array $criteria
     * @param array $orderBy
     * @param null $limit
     * @param null $offset
     * @return User[]
     */
    public function getUsersBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        return $this->repo->findBy($criteria, $orderBy, $limit, $offset);
    }

    public function getAllUsers()
    {
        return $this->repo->findAll();
    }

    public function countUser()
    {
        return (int) count($this->getAllUsers() );
    }

    /**
     * Loads the user for the given username.
     * This method must throw UsernameNotFoundException if the user is not
     * found.
     * @param string $username The username
     * @return UserInterface
     * @see UsernameNotFoundException
     * @throws UsernameNotFoundException if the user is not found
     */
    public function loadUserByUsername($username)
    {
        return $this->getUserBy(array('username' => $username));
    }

    /**
     * Insert a new User instance into the database.
     * @param User $user
     * @return bool
     */ //TODO
    public function insert(User $user)
    {
        if (!$this->isValidate($user)) {
            return false;
        }
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        return true;
    }

    /**
     * Refreshes the user for the account interface.
     * It is up to the implementation to decide if the user data should be
     * totally reloaded (e.g. from the database), or if the UserInterface
     * object can just be merged into some internal array of users / identity
     * map.
     * @param UserInterface $user
     * @return UserInterface
     * @throws UnsupportedUserException if the account is not supported
     */
    public function refreshUser(UserInterface $user)
    {
        return $this->getUserBy(
            array(
                'username' => $user->getUsername()
            )
        );
    }

    /**
     * Whether this provider supports the given user class
     * @param string $class
     * @return bool
     */
    public function supportsClass($class)
    {
        return $class === $this->supportedClass;
    }

    /**
     * Get a User instance by its ID.
     * @deprecated
     * @param int $id
     * @return User|null The User, or null if there is no User with that ID.
     */
    public function getUser($id)
    {
        return $this->getUserBy(array('id' => $id));
    }

    /**
     * Factory method for creating a new User instance.
     * @deprecated
     * @param string $name
     * @param string $plainPassword
     * @param string $email
     * @return User
     */
    public function createUser($name, $plainPassword, $email)
    {
        $user = new User($name);
        $user->setEmail($email);
        $user->setPassword(
            $this->getEncoder($user)->encodePassword(
                $plainPassword, $user->getSalt()
            )
        );
        return $user;
    }

    /**
     * Validate a user object.
     *
     * Invokes User::validate(), and additionally tests that the User's email address isn't associated with another User.
     *
     * @param User $user
     * @return array An array of error messages, or an empty array if the User is valid.
     */
    public function validate(User $user)
    {
        $errors = $user->validate();
        $duplicates = $this->getUsersBy(array('email' => $user->getEmail()));
        if (!empty($duplicates)) {
            foreach ($duplicates as $dup) {
                if ($user->getId() && $dup->getId() == $user->getId()) {
                    continue;
                }
                $errors['email'] = 'An account with that email address already exists.';
            }
        }
        return $errors;
    }

    /**
     * @param User $user
     * @return bool
     */
    public function isValidate(User $user)
    {
        if (count($this->validate($user)) === 0) {
            return true;
        }
        return false;
    }

    /**
     * @param UserInterface $user
     * @return PasswordEncoderInterface
     */
    protected function getEncoder(UserInterface $user)
    {
        return $this->app['security.encoder_factory']->getEncoder($user);
    }

    /**
     * Encode a plain text password for a given user. Hashes the password with the given user's salt.
     *
     * @param User $user
     * @param string $password A plain text password.
     * @return string An encoded password.
     */
    public function encodeUserPassword(User $user, $password)
    {
        $encoder = $this->getEncoder($user);
        return $encoder->encodePassword($password, $user->getSalt());
    }

    /**
     * Encode a plain text password and set it on the given User object.
     * @deprecated
     * @param User $user
     * @param string $password A plain text password.
     */
    public function setUserPassword(User $user, $password)
    {
        $user->setPassword($this->encodeUserPassword($user, $password));
    }

    /**
     * Test whether a given plain text password matches a given User's encoded password.
     * @deprecated
     * @param User $user
     * @param string $password
     * @return bool
     */
    public function checkUserPassword(User $user, $password)
    {
        return $user->getPassword() === $this->encodeUserPassword($user, $password);
    }

    /**
     * Get a User instance for the currently logged in User, if any.
     *
     * @return UserInterface|null
     */
    public function getCurrentUser()
    {
        if ($this->isLoggedIn()) {
            return $this->app['security']->getToken()->getUser();
        }
        return null;
    }

    /**
     * Test whether the current user is authenticated.
     *
     * @return boolean
     */
    function isLoggedIn()
    {
        if(!isset($this->app['security'])) {
            return false;

        }
        $token = $this->app['security']->getToken();
        if ($token === null) {
            return false;
        }
        return $this->app['security']->isGranted('IS_AUTHENTICATED_REMEMBERED');
    }
}