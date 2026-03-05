<?php

namespace App\Form\Type;

use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use App\Form\ChoiceList\CalendarChoiceLoader;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\Options;


class CalendarType extends AbstractType
{

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'TimePerSlot' => 30,
            'StartTime' => '09:00',
            'EndTime' => '17:00',
            'Days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'expanded' => true,
            'multiple' => true,
            'choices_as_values' => true,
            'choice_loader' => function(Options $options) {
                return ChoiceList::loader(
                    $this,
                    new CalendarChoiceLoader($options['TimePerSlot'], $options['StartTime'], $options['EndTime'], $options['Days']),
                    [$options['TimePerSlot'], $options['StartTime'], $options['EndTime'], $options['Days']]
                );
            },
        ]);

    }

    public function getParent(): ?string
    {
        return ChoiceType::class;
    }

}