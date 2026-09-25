<?php

namespace App\Form;

use App\Entity\Appointment;
use App\Entity\Contact;
use App\Entity\Supplier;
use App\Enum\AppointmentStatus;
use App\Enum\AppointmentType as ApptType;
use App\Enum\SupplierStatus;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Appointment>
 */
class AppointmentType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $supplier = $options['supplier'];

        $builder
            ->add('title', TextType::class, ['label' => 'Título'])
            ->add('type', EnumType::class, [
                'label' => 'Tipo',
                'class' => ApptType::class,
                'choice_label' => fn (ApptType $t) => $t->label(),
            ])
            ->add('startsAt', DateTimeType::class, [
                'label' => 'Fecha y hora',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('durationMinutes', IntegerType::class, ['label' => 'Duración (min)', 'required' => false])
            ->add('location', TextType::class, ['label' => 'Ubicación', 'required' => false])
            ->add('status', EnumType::class, [
                'label' => 'Estado',
                'class' => AppointmentStatus::class,
                'choice_label' => fn (AppointmentStatus $s) => $s->label(),
            ])
            ->add('reminderAt', DateTimeType::class, [
                'label' => 'Recordatorio',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
                'help' => 'Opcional. Aparecerá en el panel cuando llegue la fecha.',
            ])
            ->add('notes', TextareaType::class, ['label' => 'Notas', 'required' => false, 'attr' => ['rows' => 3]]);

        if ($supplier instanceof Supplier) {
            // Creada desde la ficha: proveedor fijo, contacto limitado a los suyos.
            $builder->add('contact', EntityType::class, [
                'label' => 'Contacto',
                'class' => Contact::class,
                'required' => false,
                'placeholder' => '—',
                'choice_label' => fn (Contact $c) => $c->getFullName(),
                'query_builder' => fn (EntityRepository $r) => $r->createQueryBuilder('c')
                    ->where('c.supplier = :s')->setParameter('s', $supplier)
                    ->orderBy('c.firstName', 'ASC'),
            ]);
        } else {
            // Creada de forma global: se elige el proveedor (opcional).
            $builder->add('supplier', EntityType::class, [
                'label' => 'Proveedor',
                'class' => Supplier::class,
                'required' => false,
                'placeholder' => 'Sin proveedor',
                'choice_label' => fn (Supplier $s) => $s->getBrandName(),
                'query_builder' => fn (EntityRepository $r) => $r->createQueryBuilder('s')
                    ->where('s.status = :active')->setParameter('active', SupplierStatus::ACTIVE)
                    ->orderBy('s.brandName', 'ASC'),
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Appointment::class, 'supplier' => null]);
        $resolver->setAllowedTypes('supplier', [Supplier::class, 'null']);
    }
}
