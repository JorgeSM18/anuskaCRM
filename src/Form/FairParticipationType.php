<?php

namespace App\Form;

use App\Entity\FairParticipation;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<FairParticipation>
 */
class FairParticipationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('contactsMet', TextareaType::class, ['label' => 'Contactos que se conocieron', 'required' => false, 'attr' => ['rows' => 2]])
            ->add('meetingNotes', TextareaType::class, ['label' => 'Notas de la reunión', 'required' => false, 'attr' => ['rows' => 3]])
            ->add('nextAction', TextType::class, ['label' => 'Próxima acción', 'required' => false])
            ->add('followUp', CheckboxType::class, ['label' => 'Interesa contactar / hacer seguimiento', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => FairParticipation::class]);
    }
}
