<?php

namespace Doomsta\Silex\User\Form;

use Doomsta\Silex\User\Entity;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\Options;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;


class UserType extends AbstractType
{
    protected $passwordEncoder;

    public function __construct(PasswordEncoderInterface $passwordEncoder)
    {
        $this->passwordEncoder = $passwordEncoder;
    }

    public function buildForm(FormBuilderInterface $builder, array $options)
    {

        $builder->add('username', 'text', ['attr' => ['autocomplete' => 'off']]);
        $builder->add('email', 'text', [
            'attr' => ['autocomplete' => 'off'],
            'required' => false,
        ]);


        $builder->add('password', 'repeated', [
            'type' => 'password',
            #'invalid_message' => 'The password fields must match.', //TODO this should work
            'first_options'  => ['label' => 'Password'],
            'second_options' => ['label' => 'Retype'],
        ]);


        // encode password
        $builder->addEventSubscriber(new EncodePasswordSubscriber($this->passwordEncoder));
    }

    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults([
            'data_class' => Entity::$user,
            'email_as_identity' => false,
            'validation_groups' => function (Options $options) {
                    $groups[] = $options['email_as_identity'] ? 'RegisterEmail' : 'RegisterUsername';
                    $groups[] = 'Credentials';

                    return $groups;
                },
        ]);
    }

    public function getName()
    {
        return 'user';
    }
}
