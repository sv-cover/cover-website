<?php

namespace App\Markup\Node;

use App\Markup\NodeInterface;
use App\Markup\Node\AbstractNode;

class RootNode extends AbstractNode
{
    public function getPriority(): int
    {
        return PHP_INT_MAX;
    }

    protected function doRender(?string $content = null): string
    {
        return $content ?? '';
    }

    public function canContain(NodeInterface $other): bool
    {
        return true;
    }
}
