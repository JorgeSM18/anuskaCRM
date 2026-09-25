<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<User>
 */
class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, [
                'label' => 'Nombre completo',
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
            ])
            ->add('isAdmin', CheckboxType::class, [
                'label' => 'Es administrador',
                'required' => false,
                'mapped' => false,
                'data' => $options['is_admin'],
                'help' => 'Los administradores pueden gestionar usuarios y catálogos.',
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => $options['require_password'] ? 'Contraseña' : 'Nueva contraseña (dejar en blanco para mantener)',
                'mapped' => false,
                'required' => $options['require_password'],
                // En edición se deja en blanco para mantener la contraseña actual;
                // por eso el mínimo de 6 solo se exige al crear.
                'constraints' => $options['require_password'] ? [
                    new NotBlank(message: 'Introduce una contraseña.'),
                    new Length(min: 6, minMessage: 'Mínimo {{ limit }} caracteres.'),
                ] : [],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'require_password' => false,
            'is_admin' => false,
        ]);
        $resolver->setAllowedTypes('require_password', 'bool');
        $resolver->setAllowedTypes('is_admin', 'bool');
    }
}
