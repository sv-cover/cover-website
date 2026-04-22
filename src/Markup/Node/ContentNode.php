<?php

namespace App\Markup\Node;

use App\Markup\ContentNodeInterface;
use App\Markup\NodeInterface;
use App\Markup\Node\AbstractNode;

class ContentNode extends AbstractNode implements ContentNodeInterface
{
    public function __construct(
        protected string $content,
    ) {
    }

    public function getPriority(): int
    {
        return PHP_INT_MIN;
    }

    public function addContent(string $content): void
    {
        $this->content = $content;
    }

    public function addNode(NodeInterface $node): void
    {
        throw new \RuntimeException("TextNode can't contain other nodes!");
    }

    protected function doRender(?string $content = null): string
    {
        $content = $content ?? $this->content;

        // Replace the scary stuff
        $content = htmlspecialchars($content, ENT_NOQUOTES);

        // We're not scared of ampersands, they're useful. (Allows $nbsp;)
        $content = str_replace('&amp;', '&', $content);

        // Replace some other fun stuff!
        $content = preg_replace("/(\n|\r\n)/", "<br>\n", $content);
        $content = str_replace('$', '&#36;', $content);
        $content = str_replace('\\', '&#92;', $content);
        $content = str_replace('{', '&#123;', $content);

        if (!empty($content))
            return $content;

        return '';
    }

    public function isEmpty(bool $trim = true): bool
    {
        if ($trim)
            return empty(trim($this->content));
        return empty($this->content);
    }

    public function __debugInfo() {
        return [
            'tag' => $this->tag,
            'content' => $this->content,
        ];
    }
}
