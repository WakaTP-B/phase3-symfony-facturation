<?php

namespace App\Form;

use App\Entity\Client;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;


class ClientType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'constraints' => [
                    new NotBlank(message: "Veuillez indiquer un nom pour le client"),
                    new Length(max: 255, maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères')
                ]
            ])
            ->add('email', EmailType::class, [
                'constraints' => [
                    new NotBlank(message: 'Email olbigatoire'),
                    new Email(message: 'Email invalide')
                ]
            ])
            ->add('phone', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Veuillez indiquer un numéro de téléphone'),
                    new Length(max: 20, maxMessage: 'Le numéro ne peut pas dépasser {{ limit }} caractères'),
                    new Regex(
                        pattern: '/^[\d\s\+\-\(\)\.]+$/',
                        message: 'Numéro de téléphone invalide'
                    ),
                ]
            ])
            ->add('address', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Veuillez indiquer une adresse'),
                    new Length(max: 255, maxMessage: 'L\'adresse ne peut pas dépasser {{ limit }} caractères'),
                ]
            ])
            ->add('siret', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Length(exactly: 14, exactMessage: 'Le SIRET doit contenir exactement {{ limit }} chiffres'),
                    new Regex(
                        pattern: '/^\d{14}$/',
                        message: 'Le SIRET doit contenir uniquement des chiffres'
                    ),
                ]
            ])
            ->add('rib', TextType::class, [
                'constraints' => [
                    new NotBlank(message: 'Veuillez indiquer un RIB'),
                    new Length(max: 34, maxMessage: 'Le RIB ne peut pas dépasser {{ limit }} caractères'),
                ]
            ])
        ;
    }
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Client::class,
        ]);
    }
}
