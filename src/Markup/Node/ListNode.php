<?php

namespace App\Markup\Node;

use App\Markup\NodeInterface;
use App\Markup\Node\BlockNode;
use App\Markup\Node\ListItemNode;

class ListNode extends BlockNode
{
    protected function doRender(?string $content = null): string
    {
        if (empty($content))
            return '';
        elseif ($this->tag === 'ol')
            return "<ol>$content</ol>";
        return "<ul>$content</ul>";
    }

    public function addNode(NodeInterface $node): void
    {
        if (!($node instanceof ListItemNode) && $node->isEmpty())
            return;

        if (!($node instanceof ListItemNode))
            $node = new ListItemNode(children: [$node]);

        parent::addNode($node);
    }
}
