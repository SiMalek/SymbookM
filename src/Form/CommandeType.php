<?php

namespace App\Form;

use App\Entity\Commande;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CommandeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('adresse', TextType::class, [
                'label' => 'Adresse de livraison',
                'attr' => ['class' => 'form-control']
            ])
            ->add('methodePaiement', ChoiceType::class, [
                'label' => 'Méthode de paiement',
                'choices' => [
                    'Carte bancaire' => 'Carte',
                    'Paiement à la livraison' => 'Paiement à la livraison',
                ],
                'attr' => ['class' => 'form-control']
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Commande::class,
        ]);
    }
}
