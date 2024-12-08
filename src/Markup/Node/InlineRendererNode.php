<?php

namespace App\Markup\Node;

use App\Markup\ContentNodeInterface;
use App\Markup\NodeInterface;
use App\Markup\Node\AbstractRendererNode;

class InlineRendererNode extends AbstractRendererNode implements ContentNodeInterface
{
    public function getPriority(): int
    {
        return NodeInterface::PRIORITY_INLINE;
    }

    public function addContent(string $content): void
    {
        $this->addNode(new ContentNode($content));
    }
}
