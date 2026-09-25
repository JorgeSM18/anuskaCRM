<?php

namespace App\Form;

use App\Entity\Fair;
use App\Enum\FairStatus;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Fair>
 */
class FairType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, ['label' => 'Nombre de la feria'])
            ->add('status', EnumType::class, [
                'label' => 'Estado',
                'class' => FairStatus::class,
                'choice_label' => fn (FairStatus $s) => $s->label(),
            ])
            ->add('city', TextType::class, ['label' => 'Ciudad', 'required' => false])
            ->add('location', TextType::class, ['label' => 'Lugar / recinto', 'required' => false])
            ->add('startsAt', DateType::class, ['label' => 'Fecha de inicio', 'widget' => 'single_text', 'input' => 'datetime_immutable'])
            ->add('endsAt', DateType::class, ['label' => 'Fecha de fin', 'widget' => 'single_text', 'input' => 'datetime_immutable', 'required' => false])
            ->add('edition', TextType::class, ['label' => 'Edición / año', 'required' => false, 'attr' => ['placeholder' => 'Ej. 2027']])
            ->add('website', UrlType::class, ['label' => 'Web', 'required' => false, 'default_protocol' => 'https'])
            ->add('notes', TextareaType::class, ['label' => 'Notas', 'required' => false, 'attr' => ['rows' => 3]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Fair::class]);
    }
}
