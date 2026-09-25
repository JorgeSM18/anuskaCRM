<?php

namespace App\Form;

use App\Entity\Contact;
use App\Entity\PurchaseOrder;
use App\Entity\Supplier;
use App\Enum\OrderStatus;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<PurchaseOrder>
 */
class PurchaseOrderType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $supplier = $options['supplier'];

        $builder
            ->add('number', TextType::class, ['label' => 'Número de pedido'])
            ->add('orderedAt', DateType::class, [
                'label' => 'Fecha del pedido',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('status', EnumType::class, [
                'label' => 'Estado',
                'class' => OrderStatus::class,
                'choice_label' => fn (OrderStatus $s) => $s->label(),
            ])
            ->add('season', TextType::class, ['label' => 'Temporada', 'required' => false, 'attr' => ['placeholder' => 'Ej. PV27']])
            ->add('campaign', TextType::class, ['label' => 'Campaña', 'required' => false])
            ->add('contact', EntityType::class, [
                'label' => 'Comercial que lo gestionó',
                'class' => Contact::class,
                'required' => false,
                'placeholder' => '—',
                'choice_label' => fn (Contact $c) => $c->getFullName(),
                'query_builder' => fn (EntityRepository $r) => $r->createQueryBuilder('c')
                    ->where('c.supplier = :s')->setParameter('s', $supplier)
                    ->orderBy('c.firstName', 'ASC'),
            ])
            ->add('estimatedAmount', MoneyType::class, ['label' => 'Importe estimado', 'currency' => 'EUR', 'required' => false])
            ->add('finalAmount', MoneyType::class, ['label' => 'Importe final', 'currency' => 'EUR', 'required' => false])
            ->add('expectedDeliveryAt', DateType::class, [
                'label' => 'Entrega prevista',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('receivedAt', DateType::class, [
                'label' => 'Fecha de recepción',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('content', TextareaType::class, ['label' => 'Qué se pidió (opcional)', 'required' => false, 'attr' => ['rows' => 2]])
            ->add('notes', TextareaType::class, ['label' => 'Notas', 'required' => false, 'attr' => ['rows' => 3]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => PurchaseOrder::class]);
        $resolver->setRequired('supplier');
        $resolver->setAllowedTypes('supplier', Supplier::class);
    }
}
