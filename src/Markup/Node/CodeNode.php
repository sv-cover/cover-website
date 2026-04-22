<?php

namespace App\Markup\Node;

use App\Markup\ContentNodeInterface;
use App\Markup\NodeInterface;
use App\Markup\Node\BlockNode;
use App\Markup\Node\ParagraphNode;

class CodeNode extends AbstractNode implements ContentNodeInterface
{
    const TAG = 'code';
    protected string $tag = self::TAG;
    protected string $content = '';

    public function getPriority(): int
    {
        return NodeInterface::PRIORITY_BLOCK;
    }

    public function addContent(string $content): void
    {
        $this->content = $content;
    }

    public function addNode(NodeInterface $node): void
    {
        throw new \RuntimeException("CodeNode can't contain other nodes!");
    }

    protected function doRender(?string $content = null): string
    {
        $content = trim($content ?? $this->content);
        $content = \htmlspecialchars($content, \ENT_NOQUOTES);
        return '<pre class="code" title="Code">' . $content . '</pre>';
    }

    public function isEmpty(bool $trim = true): bool
    {
        if ($trim);
            return empty(trim($this->content));
        return empty($this->content);
    }
}
