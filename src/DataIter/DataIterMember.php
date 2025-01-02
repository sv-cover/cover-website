<?php

namespace App\DataIter;

use App\DataModel\DataModelMember;
use App\Legacy\Database\DataIter;
use App\Legacy\Database\SearchResultInterface;
use App\Legacy\Database\DataIterNotFoundException;
use App\Utils\SearchUtils;

class DataIterMember extends DataIter implements SearchResultInterface
{
    private $_secretary_data;
    private $_secretary_changes = [];

    static public function fields()
    {
        return [
            'id',
            'voornaam',
            'tussenvoegsel',
            'achternaam',
            'adres',
            'postcode',
            'woonplaats',
            'email',
            'geboortedatum',
            'geslacht',
            'telefoonnummer',
            'privacy',
            'machtiging',
            'beginjaar',
            'lidid',
            'onderschrift',
            'avatar',
            'homepage',
            'nick',
            'taal',
            // 'type',
            'member_from',
            'member_till',
            'donor_from',
            'donor_till',
        ];
    }

    public function set_member_from($date)
    {
        $this->_set_date_field('member_from', $date);
    }

    public function set_member_till($date)
    {
        $this->_set_date_field('member_till', $date);
    }

    public function set_donor_from($date)
    {
        $this->_set_date_field('donor_from', $date);
    }

    public function set_donor_till($date)
    {
        $this->_set_date_field('donor_till', $date);
    }

    private function _set_date_field($field, $value) {
        if (!$value)
            $value = null;
        else
            $value = (new \DateTime($value))->format('Y-m-d');

        $this->data[$field] = $value;
        $this->mark_changed($field);
    }

    public function get_type()
    {
        $now = new \DateTime();

        if ($this->is_member())
            return DataModelMember::STATUS_LID;

        else if ($this->is_donor())
            return DataModelMember::STATUS_DONATEUR;

        else if ($this->has_been_member())
            return DataModelMember::STATUS_LID_AF;

        else if (!$this->has_been_donor())
            return DataModelMember::STATUS_PENDING;
    }

    public function is_member()
    {
        return $this->is_member_on(new \DateTime());
    }

    public function is_donor()
    {
        return $this->is_donor_on(new \DateTime());
    }

    public function is_pending()
    {
        return !$this->is_member() && !$this->is_donor() && !$this->has_been_member() && !$this->has_been_donor();
    }

    public function is_member_on(\DateTime $moment)
    {
        return $this['member_from'] && new \DateTime($this['member_from']) <= $moment
            && (!$this['member_till'] || new \DateTime($this['member_till']) >= $moment);
    }

    public function is_donor_on(\DateTime $moment)
    {
        return $this['donor_from'] && new \DateTime($this['donor_from']) <= $moment
            && (!$this['donor_till'] || new \DateTime($this['donor_till']) >= $moment);
    }

    public function has_been_member()
    {
        return $this['member_till'] && new \DateTime($this['member_till']) < new \DateTime();
    }

    public function has_been_donor()
    {
        return $this['donor_till'] && new \DateTime($this['donor_till']) < new \DateTime();
    }

    public function get_naam()
    {
        trigger_error('Use DataIterMember::full_name instead of DataIterMember::naam', E_USER_NOTICE);

        return $this->get_full_name();
    }

    /**
     * Get a clean version of the member's name, based on parameters. To be used
     * in conjunction with the null coalescing operator (??).
     */
    private function _get_clean_name($ignorePrivacy, $bePersonal): ?string
    {
        $identity = $this->model->auth->identity;
        $isSelf = $identity->get('id') == $this->get_id();

        if ($bePersonal && $isSelf)
            return __('You!');

        if (
            !$ignorePrivacy
            && !$isSelf
            && !$identity->member_in_committee(COMMISSIE_BESTUUR)
            && !$identity->member_in_committee(COMMISSIE_KANDIBESTUUR)
            && $this->is_private('naam')
        )
            return __('Anonymous');

        return null;
    }

    public function get_full_name($ignorePrivacy = false, $bePersonal = false)
    {
        $fullName = $this['voornaam'];

        if (!empty($this['tussenvoegsel']))
            $fullName .= ' ' . $this['tussenvoegsel'];

        $fullName .= ' ' . $this['achternaam'];

        return $this->_get_clean_name($ignorePrivacy, $bePersonal) ?? $fullName;
    }

    public function get_first_name($ignorePrivacy = false, $bePersonal = false)
    {
        return $this->_get_clean_name($ignorePrivacy, $bePersonal) ?? $this['voornaam'];
    }

    public function is_private($field, $unless_self = false)
    {
        return $this->model->is_private($this, $field, $unless_self);
    }

    public function get_search_relevance(): float
    {
        return 0.5 + SearchUtils::normalizeRank($this['number_of_committees']);
    }

    public function get_search_type(): string
    {
        return 'member';
    }

    public function get_profile_picture()
    {
        try {
            return $this->model->get_profile_picture($this);
        } catch (DataIterNotFoundException $e) {
            return null;
        }
    }

    private function _get_secretary_data()
    {
        // Intentionally private
        if (!isset($this->_secretary_data)) {
            try {
                $result = current($this->model->secretary->findPerson($this->get_id()));
                $this->_secretary_data = (array) $result;
            } catch (\Exception|\Error $exception) {
                \Sentry\captureException($exception);
                $this->_secretary_data = [];
            }
        }
        return $this->_secretary_data;
    }

    public function get_iban()
    {
        // May trigger API request (performance penalty). Only use when absolutely necessary.
        $data = $this->_get_secretary_data();
        return $data['iban'] ?? null;
    }

    public function set_iban(string $iban = null)
    {
        // May trigger API request (performance penalty). Only use when absolutely necessary.
        $this->_get_secretary_data(); // make sure $this->_secretary_data is initiated
        $this->_secretary_data['iban'] = $iban;
    }

    public function get_bic()
    {
        // May trigger API request (performance penalty). Only use when absolutely necessary.
        $data = $this->_get_secretary_data();
        return $data['bic'] ?? null;
    }

    public function set_bic(string $bic = null)
    {
        // May trigger API request (performance penalty). Only use when absolutely necessary.
        $this->_get_secretary_data(); // make sure $this->_secretary_data is initiated
        $this->_secretary_data['bic'] = $bic;
    }

    protected function mark_changed($field) {
        if (in_array($field, ['bic', 'iban']) && !in_array($field, $this->_secretary_changes))
            $this->_secretary_changes[] = $field;
        else
            parent::mark_changed($field);
    }

    public function has_secretary_changes() {
        // Are there any changes that should be reflected in secretary?
        return (count($this->_secretary_changes) != 0) || $this->has_changes();
    }

    public function secretary_changed_fields() {
        // Which fields that should be reflected in secretary have changed?
        return array_merge($this->_secretary_changes, $this->changed_fields());
    }

    public function secretary_changed_values() {
        // Which fields + values that should be reflected in secretary have changed?
        return array_merge(
            array_combine(
                $this->_secretary_changes,
                array_map(function($key) {
                    return $this->_secretary_data[$key];
                }, $this->_secretary_changes)
            ),
            $this->changed_values(),
        );
    }

    /**
     * Note: this getter returns a list of committee id's, not actual DataIterCommittee[]
     */
    public function get_committees()
    {
        if (!empty($this->data['committees']))
            return $this->data['committees'];
        return $this->model->get_commissies($this->get_id());
    }

    public function get_status()
    {
        switch ($this['type'])
        {
            case DataModelMember::STATUS_LID:
                return __('Member');

            case DataModelMember::STATUS_LID_AF:
                return __('Previously a member');

            case DataModelMember::STATUS_ERELID:
                return __('Honorary Member');

            case DataModelMember::STATUS_DONATEUR:
                return __('Donor');

            case DataModelMember::STATUS_PENDING:
                return __('No status');

            default:
                return __('Unknown');
        }
    }
}
