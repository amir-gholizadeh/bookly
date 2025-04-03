<?php

// src/Form/RegistrationFormType.php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class RegistrationFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('email', EmailType::class)
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'first_options' => ['label' => 'Password'],
                'second_options' => ['label' => 'Repeat Password'],
            ])
            ->add('name', TextType::class)
            ->add('profilePicturePath', FileType::class, [
                'label' => 'Profile Picture',
                'required' => false,
                'mapped' => false,
            ])
            ->add('isHuman', CheckboxType::class, [
                'label' => 'I am not a robot',
                'mapped' => false,
                'required' => true,
            ])
            ->add('roles', ChoiceType::class, [
                'mapped' => false,
                'required' => false,
                'label' => 'Role',
                'choices' => [
                    'User' => 'ROLE_USER',
                    'Manager' => 'ROLE_MANAGER',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
