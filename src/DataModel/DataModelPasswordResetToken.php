<?php

namespace App\DataModel;

use App\DataIter\DataIterPasswordResetToken;
use App\DataIter\DataIterMember;
use App\DataModel\DataModelMember;
use App\Legacy\Database\DataModel;
use Symfony\Component\String\ByteString;

class DataModelPasswordResetToken extends DataModel
{
    public string $dataiter = DataIterPasswordResetToken::class;
    public string $table = 'password_reset_tokens';
    public string $id = 'key';

    public function __construct(
        private DataModelMember $memberModel,
    ) {
    }

    public function create_token_for_member(DataIterMember $member)
    {
        $token = $this->new_iter([
            'key' => ByteString::fromRandom(40)->toString(),
            'member_id' => $member['id'],
            'created_on' => new \DateTime()
        ]);

        $this->insert($token);

        return $token;
    }

    public function invalidate_all(DataIterMember $member)
    {
        $this->db->query("DELETE FROM {$this->table} WHERE member_id = :member_id", false, [':member_id' => $member['id']]);
    }

    public function get_member_for_iter(DataIterPasswordResetToken $iter)
    {
        return $this->memberModel->get_iter($iter['member_id']);
    }
}
