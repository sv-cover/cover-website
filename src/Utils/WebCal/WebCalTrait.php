<?php

namespace App\Utils\WebCal;

trait WebCalTrait
{
    protected function encode(string $text): string
    {
        $encoding = [
            "\r" => '',
            "\n" => '\n',
            "\\" => '\\\\',
             ";" => '\\;',
             "," => '\\,'
        ];

        return \strtr($text, $encoding);
    }
}
