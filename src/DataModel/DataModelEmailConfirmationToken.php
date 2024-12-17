<?php

namespace App\DataModel;

use App\DataIter\DataIterEmailConfirmationToken;
use App\DataIter\DataIterMember;
use App\DataModel\DataModelMember;
use App\Legacy\Database\DataModel;
use Symfony\Component\String\ByteString;

class DataModelEmailConfirmationToken extends DataModel
{
    public string $dataiter = DataIterEmailConfirmationToken::class;
    public string $table = 'email_confirmation_tokens';
    public string $id = 'key';

    public function __construct(
        private DataModelMember $memberModel,
    ) {
    }

    public function create_token(DataIterMember $member, $email)
    {
        $token = $this->new_iter([
            'key' => ByteString::fromRandom(40)->toString(),
            'member_id' => $member['id'],
            'email' => $email,
            'created_on' => new DateTime()
        ]);

        $this->insert($token);

        return $token;
    }

    public function invalidate_all(DataIterMember $member)
    {
        $this->db->query("DELETE FROM {$this->table} WHERE member_id = :member_id", false, [':member_id' => $member['id']]);
    }

    public function get_member_for_iter(DataIterEmailConfirmationToken $iter)
    {
        return $this->memberModel->get_iter($iter['member_id']);
    }
}
