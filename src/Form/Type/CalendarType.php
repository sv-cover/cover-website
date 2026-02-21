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
            'startTime' => '09:00',
            'EndHour' => '17:00',
            'Days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'expanded' => true,
            'multiple' => true,
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
                $builder->add('slot' . $j . '-day'  . $i, CheckBoxType::class, [
                    'label' => __(' '),
                ]);
            }
        }
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {

        $nSlots = ($options['EndHour'] - $options['StartHour']) / ($options['TimePerSlot'] / 60);

        $times = [];
        for ($i = 0; $i <= $nSlots; $i++)
        {
            $hour = ($i != 0 ? $times[$i - 1][0] : $options['StartHour']);
            $minute = ($i != 0 ? $times[$i - 1][1] : 0);

            if ($i > 0)
            {
                $add = $options['TimePerSlot'];

                if (($minute + $add) >= 60)
                {
                    $hour += ($minute + $add) / 60;
                    $minute = ($minute + $add) % 60;
                } else 
                {
                    $minute += $add;
                }
            }

            $times[$i] = [$hour, $minute];
        }


        $view->vars['Days'] = $options['Days'];
        $view->vars['Rows'] = $nSlots;
        $view->vars['Times'] = $times;

        
        
    }

}