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
    ){
    }

    public function loadChoiceList(callable $value = null): ChoiceListInterface
    {
        $startTime = new \DateTime($this->startTime);
        $endTime = new \DateTime($this->endTime);

        $choices = [
            'Monday' => [],
            'Tuesday' => [],
            'Wednesday' => [],
            'Thursday' => [],
            'Friday' => []
        ];

        $startTime = new \DateTime($this->startTime);
        while ($startTime <= $endTime)
        {
            $choices['Monday'][$startTime->format('H:i')] = "Monday: " . $startTime->format('H:i');
            $choices['Tuesday'][$startTime->format('H:i')] = "Tuesday: " . $startTime->format('H:i');
            $choices['Wednesday'][$startTime->format('H:i')] = "Wednesday: " . $startTime->format('H:i');
            $choices['Thursday'][$startTime->format('H:i')] = "Thursday: " . $startTime->format('H:i');
            $choices['Friday'][$startTime->format('H:i')] = "Friday: " . $startTime->format('H:i');
            $startTime->modify("+{$this->timePerSlot} minutes");
        }

        $factory = new DefaultChoiceListFactory();

        return $factory->createListFromChoices($choices, $value, [$this, 'filter']);
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

        return $this->loadChoiceList()->getStructuredValues();
    }

    public function filter($value)
    {
        return true;
    }
}