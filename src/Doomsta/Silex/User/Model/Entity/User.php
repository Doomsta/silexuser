<?php

namespace Doomsta\Silex\User\Model\Entity;

use Symfony\Component\Security\Core\Encoder\EncoderAwareInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\Events;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Entity;
use Doctrine\Common\Collections\ArrayCollection;


/**
 * A simple User model.
 * Doomsta\Silex\User\Model\Entity\User
 * @ORM\Entity(repositoryClass="Doomsta\Silex\User\Model\Repository\UserRepository")
 * @ORM\HasLifecycleCallbacks
 * @ORM\Table(name="users")
 */

class User implements UserInterface
{

    /**
     * @ORM\Column(type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    protected $id;
    /**
     * @ORM\Column(type="string", length=60, unique=true)
     */
    protected $email;
    /**
     * @ORM\Column(type="string", length=64)
     */
    protected $password;
    /**
     * @ORM\Column(type="string", length=64)
     */
    protected $salt;
    /**
     * @ORM\Column(type="string", length=64, unique=true)
     */
    protected $username = 'anonymous';
    /**
     * @ORM\Column(type="bigint")
     */
    protected $timeCreated;
    /** @ORM\ManyToMany(targetEntity="Role", inversedBy="users")
     */
    private $roles;

    /**
     * Constructor.
     * @param string $username
     */
    public function __construct($username)
    {
        $this->timeCreated = time();
        $this->salt = base_convert(sha1(uniqid(mt_rand(), true)), 16, 36);
        $this->username = $username;
    }

    /**
     * Returns the roles granted to the user. Note that all users have the ROLE_USER role.
     *
     * @return array A list of the user's roles.
     */
    public function getRoles()
    {
        #$roles = $this->roles; //TODO
        $roles[] = 'ROLE_USER';
        return $roles;
    }

    /**
     * Set the user's roles to the given list.
     *
     * @param array $roles
     */
    public function setRoles(array $roles)
    {
        $this->roles = array();

        foreach ($roles as $role) {
            $this->addRole($role);
        }
    }

    /**
     * Test whether the user has the given role.
     *
     * @param string $role
     * @return bool
     */
    public function hasRole($role)
    {
        return in_array(strtoupper($role), $this->getRoles(), true);
    }

    /**
     * Add the given role to the user.
     *
     * @param string $role
     */
    public function addRole($role)
    {
        $role = strtoupper($role);

        if (!$this->hasRole($role)) {
            $this->roles[] = $role;
        }
    }

    /**
     * Remove the given role from the user.
     *
     * @param string $role
     */
    public function removeRole($role)
    {
        if (false !== $key = array_search(strtoupper($role), $this->roles, true)) {
            unset($this->roles[$key]);
            $this->roles = array_values($this->roles);
        }
    }

    /**
     * Set the user ID.
     *
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * Get the user ID.
     *
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get the encoded password used to authenticate the user.
     *
     * On authentication, a plain-text password will be salted,
     * encoded, and then compared to this value.
     *
     * @return string The encoded password.
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Set the encoded password.
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password = $password;
    }

    /**
     * Set the salt that should be used to encode the password.
     *
     * @param string $salt
     */
    public function setSalt($salt)
    {
        $this->salt = $salt;
    }

    /**
     * Returns the salt that was originally used to encode the password.
     *
     * This can return null if the password was not encoded using a salt.
     *
     * @return string The salt
     */
    public function getSalt()
    {
        return $this->salt;
    }

    /**
     * Returns the email address, which serves as the username used to authenticate the user.
     *
     * This method is required by the UserInterface.
     *
     * @return string The username
     */
    public function getUsername()
    {
        return $this->username;
    }

    /**
     * @return string The user's email address.
     */
    public function getEmail()
    {
        return $this->email;
    }

    /**
     * @param string $email
     */
    public function setEmail($email)
    {
        $this->email = $email;
    }

    /**
     * @param string $username
     */
    public function setUsername($username)
    {
        $this->username = $username;
    }

    /**
     * Set the time the user was originally created.
     *
     * @param int $timeCreated A timestamp value.
     */
    public function setTimeCreated($timeCreated)
    {
        $this->timeCreated = $timeCreated;
    }

    /**
     * Set the time the user was originally created.
     * @return int
     */
    public function getTimeCreated()
    {
        return $this->timeCreated;
    }

    /**
     * Removes sensitive data from the user.
     * @return void
     */
    public function eraseCredentials()
    {
    }

    /**
     * Validate the user object.
     * @return array An array of error messages, or an empty array if there were no errors.
     */
    public function validate()
    {
        $errors = array();

        if (!$this->getEmail()) {
            $errors['email'] = 'Email address is required.';
        } else if (!strpos($this->getEmail(), '@')) {
            // Basic email format sanity check. Real validation comes from sending them an email with a link they have to click.
            $errors['email'] = 'Email address appears to be invalid.';
        } else if (strlen($this->getEmail()) > 100) {
            $errors['email'] = 'Email address can\'t be longer than 100 characters.';
        }
        if (!$this->getPassword()) {
            $errors['password'] = 'Password is required.';
        } else if (strlen($this->getPassword()) > 255) {
            $errors['password'] = 'Password can\'t be longer than 255 characters.';
        }

        if (strlen($this->getUsername()) > 100) {
            $errors['name'] = 'Name can\'t be longer than 100 characters.';
        }

        return $errors;
    }

    public function getGravatarUrl($size = 85)
    {
        // See https://en.gravatar.com/site/implement/images/ for available options.
        return '//www.gravatar.com/avatar/' . md5(strtolower(trim($this->getEmail()))) . '?s=' . $size . '&d=identicon';
    }
}