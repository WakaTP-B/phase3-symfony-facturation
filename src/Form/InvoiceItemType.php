<?php

namespace App\Form;

use App\Entity\InvoiceItem;
use App\Entity\Product;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;

class InvoiceItemType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('product', EntityType::class, [
                'class' => Product::class,
                'choice_label' => 'name',
                'placeholder' => 'Choisir un produit',
                'choice_attr' => function (Product $product) {
                    return ['data-price' => $product->getPrice()];
                },
                'constraints' => [
                    new NotBlank(message: 'Veuillez choisir un produit'),
                ],
                "label" => false
            ])
            ->add('quantity', IntegerType::class, [
                'constraints' => [
                    new NotBlank(message: 'Veuillez indiquer une quantité'),
                    new Positive(message: 'La quantité doit être positive'),
                ],
                "label" => false
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => InvoiceItem::class,
        ]);
    }
}
