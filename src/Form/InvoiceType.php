<?php

namespace App\Form;

use App\Entity\Invoice;
use App\Entity\PurchaseOrder;
use App\Entity\Supplier;
use App\Enum\InvoiceStatus;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Invoice>
 */
class InvoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $supplier = $options['supplier'];

        $builder
            ->add('number', TextType::class, ['label' => 'Número de factura'])
            ->add('issuedAt', DateType::class, [
                'label' => 'Fecha de emisión',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('dueAt', DateType::class, [
                'label' => 'Vencimiento',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('baseAmount', MoneyType::class, ['label' => 'Base imponible', 'currency' => 'EUR'])
            ->add('vatRate', NumberType::class, ['label' => 'IVA (%)', 'scale' => 2, 'html5' => true])
            ->add('status', EnumType::class, [
                'label' => 'Estado',
                'class' => InvoiceStatus::class,
                'choice_label' => fn (InvoiceStatus $s) => $s->label(),
            ])
            ->add('paidAt', DateType::class, [
                'label' => 'Fecha de pago',
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'required' => false,
            ])
            ->add('purchaseOrder', EntityType::class, [
                'label' => 'Pedido relacionado',
                'class' => PurchaseOrder::class,
                'required' => false,
                'placeholder' => '—',
                'choice_label' => fn (PurchaseOrder $o) => $o->getNumber().($o->getSeason() ? ' · '.$o->getSeason() : ''),
                'query_builder' => fn (EntityRepository $r) => $r->createQueryBuilder('o')
                    ->where('o.supplier = :s')->setParameter('s', $supplier)
                    ->orderBy('o.orderedAt', 'DESC'),
            ])
            ->add('notes', TextareaType::class, ['label' => 'Notas', 'required' => false, 'attr' => ['rows' => 3]]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Invoice::class]);
        $resolver->setRequired('supplier');
        $resolver->setAllowedTypes('supplier', Supplier::class);
    }
}
