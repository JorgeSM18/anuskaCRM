<?php

namespace App\Form;

use App\Entity\Category;
use App\Entity\Supplier;
use App\Enum\SupplierStatus;
use Doctrine\ORM\EntityRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<Supplier>
 */
class SupplierType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('brandName', TextType::class, ['label' => 'Nombre de la marca'])
            ->add('status', EnumType::class, [
                'label' => 'Estado',
                'class' => SupplierStatus::class,
                'choice_label' => fn (SupplierStatus $s) => $s->label(),
            ])
            ->add('legalName', TextType::class, ['label' => 'Nombre fiscal', 'required' => false])
            ->add('taxId', TextType::class, ['label' => 'CIF / NIF', 'required' => false])
            ->add('phone', TextType::class, ['label' => 'Teléfono', 'required' => false])
            ->add('email', TextType::class, ['label' => 'Email general', 'required' => false])
            ->add('website', UrlType::class, ['label' => 'Web', 'required' => false, 'default_protocol' => 'https'])
            ->add('address', TextareaType::class, [
                'label' => 'Dirección',
                'required' => false,
                'attr' => ['rows' => 2],
            ])
            ->add('notes', TextareaType::class, [
                'label' => 'Notas',
                'required' => false,
                'attr' => ['rows' => 4],
            ])
            ->add('categories', EntityType::class, [
                'label' => 'Categorías',
                'class' => Category::class,
                'multiple' => true,
                'expanded' => true, // casillas de verificación
                'required' => false,
                'choice_label' => 'name',
                'query_builder' => fn (EntityRepository $r) => $r->createQueryBuilder('c')->orderBy('c.name', 'ASC'),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Supplier::class]);
    }
}
