<?php

namespace App\Markup;

use App\Markup\ContentNodeInterface;
use App\Markup\NodeInterface;
use App\Markup\Node\RootNode;
use App\Markup\Node\ParagraphNode;

/**
 * Stack of NodeInterface instances to support parsing tokens into nodes
 * representing valid HTML structure.
 *
 * i.e. inline nodes and paragraphs can only contain inline nodes and no block
 * nodes, but block nodes can contain anything.
 *
 * To facilitate this, it does two things:
 * - wrap content in paragraphs, but only when necessary.
 * - when adding a node to the top node, only do so if the top can contain it.
 *   If not, pop the top node, and add the new node as sibling.
 */
class NodeStack
{
    private array $stack;

    public function __construct()
    {
        $this->stack = [new RootNode()];
    }

    public function top(): NodeInterface
    {
        return $this->stack[count($this->stack) - 1];
    }

    public function push(NodeInterface $node): void
    {
        $this->stack[] = $node;
    }

    public function addContentToTop(string $content): void
    {
        if (!($this->top() instanceof ContentNodeInterface))
            $this->push(new ParagraphNode());

        // No need to split if this isn't a paragraph.
        if (!($this->top() instanceof ParagraphNode)) {
            $this->top()->addContent($content);
            return;
        }

        $paragraphs = preg_split("/(\n|\r\n){2,}/", $content);

        // everything except the last one
        for ($idx = 0; $idx < count($paragraphs) - 1; $idx ++) {
            $this->top()->addContent($paragraphs[$idx]);
            $this->addNodeToTop(array_pop($this->stack));
            $this->push(new ParagraphNode());
        }

        // and now the last one
        $this->top()->addContent(end($paragraphs));
    }

    public function addNodeToTop(?NodeInterface $node = null): void
    {
        if (
            $node->getPriority() < NodeInterface::PRIORITY_PARAGRAPH
            && !($this->top() instanceof ContentNodeInterface)
        )
            $this->push(new ParagraphNode());

        // Ensure block nodes always end inline nodes, regardless of whether
        // we've seen their end tag.
        while (!$this->top()->canContain($node)) {
            $n = array_pop($this->stack);
            $this->addNodeToTop($n);
        }

        $this->top()->addNode($node);
    }

    public function hasTag(string $tag): bool
    {
        $tags = array_map(fn($n) => $n->getTag(), $this->stack);
        return array_search($tag, $tags) !== false;
    }

    public function closeTag(string $tag): void
    {
        // Close tag and append to new top. NodeStack::addNodeToTop ensures we
        // won't add children to nodes which can't contain them.
        do {
            $node = array_pop($this->stack);
            $this->addNodeToTop($node);
        } while ($node->getTag() != $tag);
    }

    public function closeAllTags(): NodeInterface
    {
        // Close tag and append to new top until nothing is left.
        // NodeStack::addNodeToTop ensures we won't add children to nodes which
        // can't contain them.
        while (count($this->stack) > 1) {
            $node = array_pop($this->stack);
            $this->addNodeToTop($node);
        }
        return $this->top();
    }
}
