<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extenstion\Core\Type\FormType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;

class CalendarType extends AbstractType
{

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'TimePerSlot' => 30,
            'StartHour' => 9,
            'EndHour' => 17,
            'Days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
        ]);
    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $nslots = ($options['EndHour'] - $options['StartHour']) / ($options['TimePerSlot'] / 60);
        $numDays = count($options['Days']);

        for ($i = 0; $i < $numDays; $i++)
        {
            for ($j = 0; $j < $nslots; $j++)
            {
                $builder->add('test' . $j . '-day'  . $i, CheckBoxType::class, [
                    'label' => __(' '),
                ]);
            }
        }
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['Days'] = $options['Days'];
        $view->vars['Rows'] = (($options['EndHour'] - $options['StartHour']) / ($options['TimePerSlot'] / 60)) - 1;

    }

}