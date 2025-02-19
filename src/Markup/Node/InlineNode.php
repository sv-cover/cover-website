<?php

namespace App\Markup\Node;

use App\Markup\ContentNodeInterface;
use App\Markup\NodeInterface;
use App\Markup\Node\AbstractNode;

class InlineNode extends AbstractNode implements ContentNodeInterface
{
    public function getPriority(): int
    {
        return NodeInterface::PRIORITY_INLINE;
    }

    public function addContent(string $content): void
    {
        $this->addNode(new ContentNode($content));
    }

    protected function doRender(?string $content = null): string
    {
        return $content ?? '';
    }
}
