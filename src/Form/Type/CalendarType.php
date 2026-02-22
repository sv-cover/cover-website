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
            'StartTime' => '09:00',
            'EndTime' => '17:00',
            'Days' => ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
            'expanded' => true,
            'multiple' => true,
        ]);

    }

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        

        $nslots = 0;
        $timePerSlot = $options['TimePerSlot'];
        $startTime = new \DateTime($options['StartTime']);
        $endTime = new \DateTime($options['EndTime']);
        $currentTime = new \DateTime($options['StartTime']);
        while ($currentTime < $endTime)
        {
            $nslots++;
            $currentTime->modify("+$timePerSlot minutes");
        }

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

        $nslots = 0;
        $timePerSlot = $options['TimePerSlot'];
        $startTime = new \DateTime($options['StartTime']);
        $endTime = new \DateTime($options['EndTime']);
        $currentTime = new \DateTime($options['StartTime']);
        $currentSlot = 0;
        $times = [];
        while ($currentTime <= $endTime)
        {
            $times[$nslots] = $currentTime->format('H:i');
            $nslots++;
            $currentTime->modify("+$timePerSlot minutes");
        }

        $view->vars['Days'] = $options['Days'];
        $view->vars['Rows'] = $nslots - 1;
        $view->vars['Times'] = $times;

        
    }

}