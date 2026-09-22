<?php
namespace App\Form\Type;

use App\DataModel\DataModelCommissie;
use App\Form\ChoiceList\CommitteeChoiceLoader;
use App\Legacy\Authentication\Authentication;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\ChoiceList\ChoiceList;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\OptionsResolver\Options;

class CommitteeIdType extends AbstractType
{
    public function __construct(
        private Authentication $auth,
        private DataModelCommissie $committeeModel,
    ) {
    }

    public function getParent(): string
    {
        return ChoiceType::class;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefault('show_all', false);
        $resolver->setDefault('show_own', true);
        $resolver->setDefault('show_all_types', true);

        $resolver->setDefaults([
            'label' => __('Committee'),
            'choice_loader' => function (Options $options) {
                return ChoiceList::loader(
                    $this,
                    new CommitteeChoiceLoader($this->auth, $this->committeeModel, $options['show_all'], $options['show_own'], $options['show_all_types']),
                    [$options['show_all'], $options['show_own'], $options['show_all_types']]
                );
            },
        ]);
    }
}
