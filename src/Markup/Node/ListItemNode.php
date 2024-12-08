<?php

namespace App\Markup\Node;

use App\Markup\Node\BlockNode;

class ListItemNode extends BlockNode
{
    const TAG = 'li';
    protected string $tag = self::TAG;

    public function __construct(
        array $children = [],
    ) {
        $this->children = $children;
    }

    protected function doRender(?string $content = null): string
    {
        if (isset($content))
            return "<li>$content</li>";
        return '';
    }
}
