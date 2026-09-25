<?php

namespace App\Form;

use App\Entity\Communication;
use App\Entity\Contact;
use App\Entity\Supplier;
use App\Enum\CommunicationType as CommType;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Communication>
 */
class CommunicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $supplier = $options['supplier'];

        $builder
            ->add('type', EnumType::class, [
                'label' => 'Tipo',
                'class' => CommType::class,
                'choice_label' => fn (CommType $t) => $t->label(),
            ])
            ->add('occurredAt', DateTimeType::class, [
                'label' => 'Fecha y hora',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('subject', TextType::class, ['label' => 'Asunto'])
            ->add('contact', EntityType::class, [
                'label' => 'Persona de contacto',
                'class' => Contact::class,
                'required' => false,
                'placeholder' => '—',
                'choice_label' => fn (Contact $c) => $c->getFullName(),
                'query_builder' => fn (EntityRepository $r) => $r->createQueryBuilder('c')
                    ->where('c.supplier = :s')->setParameter('s', $supplier)
                    ->orderBy('c.firstName', 'ASC'),
            ])
            ->add('body', TextareaType::class, ['label' => 'Resumen / contenido', 'required' => false, 'attr' => ['rows' => 4]])
            ->add('isImportant', CheckboxType::class, ['label' => 'Importante', 'required' => false])
            ->add('pendingReply', CheckboxType::class, ['label' => 'Pendiente de respuesta', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Communication::class]);
        $resolver->setRequired('supplier');
        $resolver->setAllowedTypes('supplier', Supplier::class);
    }
}
