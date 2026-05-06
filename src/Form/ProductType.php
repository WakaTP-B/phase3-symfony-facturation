<?php

namespace App\Form;

use App\Entity\Product;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Positive;
use Symfony\Component\Validator\Constraints\Length;

class ProductType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Veuillez indiquer un nom pour le produit'),
                    new Length(max: 255, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères'),
                ],
            ])
            ->add('description', TextareaType::class, [
                'constraints' => [
                    new NotBlank(message: 'Veuillez indiquer une description'),
                ],
            ])
            ->add('price', MoneyType::class, [
                'currency' => false,
                'scale' => 2,
                'attr' => [
                    'type' => 'number',
                    'step' => '0.01',
                    'min' => '0',
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez indiquer un prix'),
                    new Positive(message: 'Le prix doit être un nombre positif'),
                ],
            ])
            ->add('unit', ChoiceType::class, [
                'choices' => [
                    'Heure' => 'heure',
                    'Jour' => 'jour',
                    'Pièce' => 'pièce',
                    'Mois' => 'mois'
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez choisir une unité'),
                ],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Product::class,
        ]);
    }
}
