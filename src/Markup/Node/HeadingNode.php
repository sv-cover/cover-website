<?php

namespace App\Markup\Node;

use App\Markup\ContentNodeInterface;
use App\Markup\NodeInterface;
use App\Markup\Node\BlockNode;
use App\Markup\Node\ParagraphNode;

class HeadingNode extends BlockNode implements ContentNodeInterface
{
    const MIN_LEVEL = 1; // Even though [h1] tags are hidden, we should still be able to render <h1> in case a negative offset is provided.
    const MAX_LEVEL = 6;

    private array $options = [];

    public function __construct(
        protected string $token,
        string $tag,
    ) {
        parent::__construct($tag);
    }

    public function getPriority(): int
    {
        return NodeInterface::PRIORITY_BLOCK;
    }

    public function addContent(string $content): void
    {
        $this->addNode(new ContentNode($content));
    }

    protected function doRender(?string $content = null): string
    {
        preg_match('/\[h(?P<level>\d)(?<class>(\.[a-z-\d]+)*)\]/i', $this->token, $match);
        $class = str_replace('.', ' ', $match['class']);

        $offset = $this->options['heading_offset'] ?? 0;
        $level = max(min(intval($match['level']) + $offset, self::MAX_LEVEL), self::MIN_LEVEL);

        $tag = 'h' . $level;
        if (!empty($class))
            return "<$tag class=\"$class\">$content</$tag>";
        return "<$tag>$content</$tag>";
    }

    public function render(array $options = []): string
    {
        $this->options = $options;
        return parent::render($options);
    }

    public function canContain(NodeInterface $other): bool
    {
        if ($other instanceof self)
            return false;
        return parent::canContain($other);
    }
}
