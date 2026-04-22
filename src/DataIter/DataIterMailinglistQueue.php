<?php

namespace App\DataIter;

use App\Legacy\Database\DataIter;

class DataIterMailinglistQueue extends DataIter
{
    static public function fields()
    {
        return [
            'id',
            'destination',
            'destination_type',
            'mailinglist_id',
            'message',
            'status',
            'queued_on',
            'processing_on'
        ];
    }

    public function get_mailinglist()
    {
        return $this->model->get_mailinglist_for_iter($this);
    }

    public function header($name)
    {
        $end_header = strpos($this->get('message'), "\n\n");

        // If that didn't work, try \r\n, which may occur if the system configuration changed
        if ($end_header === false)
            $end_header = strpos($this->get('message'), "\r\n\r\n");

        // Still false? Give up.
        if ($end_header === false)
            return null;

        return preg_match('/^' . preg_quote($name) . ': (.+?)$/im', substr($this->get('message'), 0, $end_header), $match)
            ? $this->_convert_header_encoding($match[1])
            : null;
    }

    public function get_subject()
    {
        return $this->header('Subject');
    }

    public function get_sender()
    {
        // This works because the mail server adds a 'from real@email.com wed 20 aug' to the
        // beginning of the message. Alternatively, we could use the From header.
        // return substr($this->get('bericht'), 5, strpos($this->get('bericht'), ' ', 5) - 5);
        return $this->header('From');
    }

    protected function _convert_header_encoding($data)
    {
        $decode = function($match) {
            switch ($match[2])
            {
                case 'Q':
                    $data = quoted_printable_decode($match[3]);
                    break;

                case 'B':
                    $data = base64_decode($match[3]);
                    break;
            }

            if (strcasecmp($match[1], 'utf-8') !== 0)
                $data = iconv($match[1], 'UTF-8//TRANSLIT', $data);

            return $data;
        };

        return preg_replace_callback('/=\?([a-zA-Z0-9_-]+)\?(Q|B)\?(.+?)\?=/', $decode, $data);
    }
}
