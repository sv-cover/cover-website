<?php

namespace App\Service;

class Markup
{
    public function parse(?string $markup = null, int $header_offset = 0): string
    {
        return \markup_parse($markup, $header_offset);
    }

    public function strip(string $markup): string
    {
        return \markup_strip($markup);
    }
}
