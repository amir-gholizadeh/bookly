<?php

// src/Form/ReviewFormType.php

namespace App\Form;

use App\Entity\Review;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ReviewFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('rating', ChoiceType::class, [
                'label' => 'Rating',
                'choices' => [
                    '5 Stars' => 5,
                    '4 Stars' => 4,
                    '3 Stars' => 3,
                    '2 Stars' => 2,
                    '1 Star' => 1,
                ],
                'expanded' => true,
                'required' => true,
            ])
            ->add('reviewText', TextareaType::class, [
                'label' => 'Your Review',
                'attr' => [
                    'rows' => 5,
                    'placeholder' => 'Share your thoughts about this book...',
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Review::class,
        ]);
    }
}
