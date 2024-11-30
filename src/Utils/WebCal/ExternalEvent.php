<?php

namespace App\Utils\WebCal;

class ExternalEvent extends Event implements WebCalInterface
{
    public function __construct(
        public string $content,
    ) {
    }

    public function export(): string
    {
        return $this->content;
    }
}
