<?php

namespace App\Markup\Node;

use App\Markup\ContentNodeInterface;
use App\Markup\NodeInterface;
use App\Markup\Node\BlockNode;
use App\Markup\Node\ContentNode;

class ParagraphNode extends BlockNode implements ContentNodeInterface
{
    public function getPriority(): int
    {
        return NodeInterface::PRIORITY_PARAGRAPH;
    }

    public function addContent(string $content): void
    {
        $this->addNode(new ContentNode($content));
    }

    protected function doRender(?string $content = null): string
    {
        // Remove any <br>'s from the start or end.
        $pattern = '\s*(<br>)+\s*';
        $content = preg_replace("/^$pattern/", '', $content);
        $content = preg_replace("/$pattern$/", '', $content);

        if (!empty(trim($content)))
            return "<p>$content</p>";
        return '';
    }

    public function canContain(NodeInterface $other): bool
    {
        if ($other instanceof self)
            return false;
        return parent::canContain($other);
    }
}
