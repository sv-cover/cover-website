<?php
namespace App\Form\ChoiceList;

use App\Service\Authentication;
use App\Service\Database;
use Symfony\Component\Form\ChoiceList\ChoiceListInterface;
use Symfony\Component\Form\ChoiceList\Factory\DefaultChoiceListFactory;
use Symfony\Component\Form\ChoiceList\Loader\ChoiceLoaderInterface;

class CommitteeChoiceLoader implements ChoiceLoaderInterface
{
    public function __construct(
        private Authentication $auth,
        private Database $db,
        private bool $showAll = false,
        private bool $showOwn = true,
    ) {
    }

    public function loadChoiceList(callable $value = null): ChoiceListInterface
    {
        $choices = $this->db->getModel('DataModelCommissie')->get_committee_choices($this->showOwn);

        $factory = new DefaultChoiceListFactory();
        return $factory->createListFromChoices($choices, $value, [$this, 'filterCommittees']);
    }

    public function loadChoicesForValues(array $values, callable $value = null): array
    {
        // Adapted from Symfony's AbstractChoiceLoader

        if (!$values) {
            return [];
        }

        return $this->loadChoiceList($value)->getChoicesForValues($values);
    }

    public function loadValuesForChoices(array $choices, callable $value = null): array
    {
        // Adapted from Symfony's AbstractChoiceLoader

        if (!$choices) {
            return [];
        }

        if ($value) {
            // if a value callback exists, use it
            return array_map(fn ($item) => (string) $value($item), $choices);
        }

        return $this->loadChoiceList()->getValuesForChoices($choices);
    }

    public function filterCommittees($value) {
        if (
            $this->auth->identity->member_in_committee(COMMISSIE_BESTUUR)
            || $this->auth->identity->member_in_committee(COMMISSIE_KANDIBESTUUR)
            || $this->auth->identity->member_in_committee(COMMISSIE_EASY)
        )
            return true;

        return $this->showAll || $this->auth->identity->member_in_committee($value);
    }
}
