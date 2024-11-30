<?php
// require_once 'src/framework/router.php';

use App\Legacy\Database\DataIter;
use App\Legacy\Database\DataModel;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\ByteString;

class DataIterPasswordResetToken extends DataIter
{
    static public function fields()
    {
        return [
            'key',
            'member_id',
            'created_on'
        ];
    }

    public function get_member()
    {
        return get_model('DataModelMember')->get_iter($this['member_id']);
    }
}

class DataModelPasswordResetToken extends DataModel
{
    public $dataiter = 'DataIterPasswordResetToken';

    public function __construct($db)
    {
        parent::__construct($db, 'password_reset_tokens', 'key');
    }

    public function create_token_for_member(DataIterMember $member)
    {
        $token = $this->new_iter([
            'key' => ByteString::fromRandom(40)->toString(),
            'member_id' => $member['id'],
            'created_on' => new DateTime()
        ]);

        $this->insert($token);

        return $token;
    }

    public function invalidate_all(DataIterMember $member)
    {
        $this->db->query("DELETE FROM {$this->table} WHERE member_id = :member_id", false, [':member_id' => $member['id']]);
    }
}