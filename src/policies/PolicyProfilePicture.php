<?php

class PolicyProfilePicture implements Policy
{
	public function user_can_create(DataIter $iter)
	{
		if (!get_auth()->logged_in())
			return false;

		return $iter['member_id'] == get_identity()->get('id')
		 	|| get_identity()->member_in_committee(COMMISSIE_EASY);
	}

	public function user_can_read(DataIter $iter)
	{
		// You can see all your profile pictures
		if ($iter['member_id'] == get_identity()->get('id'))
			return true;

		// Admins always get to see profile pictures
		if (get_identity()->member_in_committee(COMMISSIE_BESTUUR)
			|| get_identity()->member_in_committee(COMMISSIE_KANDIBESTUUR)
			|| get_identity()->member_in_committee(COMMISSIE_EASY)
		)
			return true;

		// Everyone else has to obey your privacy settings. Only show photo if member still exists.
		return $iter['member']
			&& !get_model('DataModelMember')->is_private($iter['member'], 'foto');
	}

	public function user_can_update(DataIter $iter)
	{
		return false;
	}

	public function user_can_delete(DataIter $iter)
	{
		// Members can delete their own profile pictures
		if ($iter['member_id'] == get_identity()->get('id'))
			return true;

		return get_identity()->member_in_committee(COMMISSIE_BESTUUR)
			|| get_identity()->member_in_committee(COMMISSIE_KANDIBESTUUR)
			|| get_identity()->member_in_committee(COMMISSIE_EASY);
	}

	public function user_can_review(DataIter $iter)
	{
		// Only unreviewed items can be reviewed
		if ($iter['reviewed'])
			return false;

		return get_identity()->member_in_committee(COMMISSIE_BESTUUR)
			|| get_identity()->member_in_committee(COMMISSIE_KANDIBESTUUR)
			|| get_identity()->member_in_committee(COMMISSIE_EASY);
	}
}
