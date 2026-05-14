<?php

namespace App\Form;

use App\Entity\Client;
use App\Entity\Invoice;
use App\Repository\ClientRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotNull;

class InvoiceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('client', EntityType::class, [
                'class' => Client::class,
                'choice_label' => 'name',
                'placeholder' => 'Choisir un client',
                'label' => false,
                'query_builder' => function (ClientRepository $cr) use ($options) {
                    return $cr->createQueryBuilder('c')
                        ->where('c.user = :user')
                        ->setParameter('user', $options['user']);
                },
                'constraints' => [
                    new NotNull(message: 'Veuillez choisir un client'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Invoice::class,
            'user' => null,
        ]);
    }
}
