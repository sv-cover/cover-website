<?php

namespace App\Legacy\Authentication;

use App\Legacy\Authentication\MemberIdentityProvider;

class ImpersonatingIdentityProvider extends MemberIdentityProvider
{
    protected $override_member;

    protected $override_committees;

    public function member()
    {
        if (!$this->session_provider->logged_in())
            return null;

        if ($this->get_override_member() !== null)
            $member = $this->get_override_member();
        else
            $member = parent::member();

        if ($this->get_override_committees() !== null)
            $member = new \DataIterMember($member->model(), $member->get_id(),
                array_merge($member->data, ['committees' => $this->get_override_committees()]));

        return $member;
    }

    public function member_in_committee($committee = null)
    {
        if ($this->get_override_committees() === null)
            return parent::member_in_committee($committee);

        return $committee !== null
            ? in_array($committee, $this->get_override_committees())
            : count($this->get_override_committees());
    }

    public function get_override_member()
    {
        $session = $this->session_provider->get_session();

        if (!$session || $session['override_member_id'] === null)
            return null;

        if (!$this->override_member)
            $this->override_member = \get_model('DataModelMember')->get_iter($session['override_member_id']);
        
        return $this->override_member;
    }

    public function override_member(DataIterMember $member)
    {
        $this->override_member = $member;

        $session = $this->session_provider->get_session();
        $session['override_member_id'] = $member->get_id();
        $session->update();
    }

    public function reset_member()
    {
        $this->override_member = null;
        
        $session = $this->session_provider->get_session();
        $session['override_member_id'] = null;
        $session->update();
    }

    public function get_override_committees()
    {
        $session = $this->session_provider->get_session();

        if (!$session || $session['override_committees'] === null)
            return null;

        if (!$this->override_committees)
            $this->override_committees = array_map('intval', $session['override_committees'] !== ''
                ? explode(';', $session['override_committees'])
                : []);

        return $this->override_committees;
    }

    public function override_committees(array $committee_ids)
    {
        $this->override_committees = array_map('intval', $committee_ids);

        $session = $this->session_provider->get_session();
        $session['override_committees'] = implode(';', $committee_ids);
        $session->update();
    }

    public function reset_committees()
    {
        $this->override_committees = null;

        $session = $this->session_provider->get_session();
        $session['override_committees'] = null;
        $session->update();
    }

    public function is_impersonating()
    {
        return $this->session_provider->logged_in()
            && ($this->get_override_member() !== null || $this->get_override_committees() !== null);
    }

    public function can_impersonate()
    {
        return true;
    }
}

