<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;

class DataIterPollCommentLike extends DataIter
{
    static public function fields()
    {
        return [
            'id',
            'poll_comment_id',
            'member_id',
            'created_on',
        ];

    }
}
