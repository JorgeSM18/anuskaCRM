<?php

namespace App\Form;

use App\Entity\Contact;
use App\Enum\ContactRole;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Contact>
 */
class ContactType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('firstName', TextType::class, ['label' => 'Nombre'])
            ->add('lastName', TextType::class, ['label' => 'Apellidos', 'required' => false])
            ->add('role', EnumType::class, [
                'label' => 'Tipo de contacto',
                'class' => ContactRole::class,
                'choice_label' => fn (ContactRole $r) => $r->label(),
            ])
            ->add('jobTitle', TextType::class, ['label' => 'Cargo', 'required' => false])
            ->add('phone', TextType::class, ['label' => 'Teléfono', 'required' => false])
            ->add('email', TextType::class, ['label' => 'Email', 'required' => false])
            ->add('notes', TextareaType::class, ['label' => 'Notas', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('isActive', CheckboxType::class, ['label' => 'Contacto activo', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Contact::class]);
    }
}
