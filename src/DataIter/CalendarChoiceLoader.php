<?php
namespace App\Form\ChoiceList;

use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

class CalendarChoiceLoader implements ChoiceLoaderInterface
{

    public function __construct(
        private int $timePerSlot = 30,
        private string $startTime = '09:00',
        private string $endTime = '17:00',
        private array $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday'],
    ){
    }

    public function loadChoiceList(callable $value = null): ChoiceListInterface
    {

        $choices = [];
        $times = [];
        $currentTime = 0;
        $startTime = new \DateTime($this->startTime);
        $endTime = new \DateTime($this->endTime);
        while ($startTime < $endTime) {
            $times[$currentTime] = [$startTime->format('H'), $startTime->format('i')];
            $currentTime++;
            $startTime->modify("+{$this->timePerSlot} minutes");
        }

        dump($times);

        foreach ($this->days as $day)
        {
            $choices[$day] = $times;
        }

        $factory = new DefaultChoiceListFactory();

        return $factory->createListFromChoices($choices, $value);
    }

    public function loadChoicesForValues(array $values, callable $value = null): array
    {
        if (!$values)
            return [];

        return $this->loadChoiceList($value)->getChoicesForValues($values);
    }

    public function loadValuesForChoices(array $choices, callable $value = null): array
    {
        if (!$choices)
            return [];

        if ($value)
            return array_map(fn ($item) => (string) $value($item), $choices);

        return $this->loadChoiceList()->getValuesForChoices($choices);
    }
}