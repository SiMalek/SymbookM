<?php

namespace App\Form;

use App\Entity\Categories;
use App\Entity\Livre;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LivreType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre')
            ->add('isbn')
            ->add('slug')
            ->add('image')
            ->add('resume')
            ->add('editeur')
            ->add('dateEdition', null, [
                'widget' => 'single_text',
            ])
            ->add('prix')
            ->add('categorie', EntityType::class, [
                'class' => Categories::class,
                'choice_label' => 'libeller',
                'placeholder' => 'choisie une  categorie',
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Livre::class,
        ]);
    }
}
