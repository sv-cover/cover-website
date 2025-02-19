<?php

namespace App\Markup\Node;

use App\Markup\NodeInterface;
use App\Markup\TagParserInterface;

/**
 * Abstract node to provide a shared/base implementation for most functions
 * required by NodeInterface.
 */
abstract class AbstractNode implements NodeInterface
{
    protected string $tag = '';
    protected array $children = [];
    protected bool $_isVoid = false;

    public function __construct(
        ?string $tag = null,
        bool $isVoid = false,
    ) {
        if (isset($tag))
            $this->tag = $tag;

        $this->_isVoid = $isVoid;
    }

    /**
     * See NodeInterface::getPriority
     */
    abstract public function getPriority(): int;

    /**
     * The actual implementation for NodeInterface::render.
     * May be provided with a string of the rendered children.
     */
    abstract protected function doRender(?string $content = null): string;

    /**
     * See NodeInterface::addNode
     */
    public function addNode(NodeInterface $node): void
    {
        if (!$this->canContain($node))
            throw new \RuntimeException(sprintf("%s can't contain %s", $this::class, $node::class));

        $this->children[] = $node;
    }

    /**
     * See NodeInterface::getTag
     */
    public function getTag(): string
    {
        return $this->tag;
    }

    /**
     * See NodeInterface::getChildren
     */
    public function getChildren(): array
    {
        return $this->children ?? [];
    }

    /**
     * See NodeInterface::canContain
     */
    public function canContain(NodeInterface $other): bool
    {
        return ($this->getPriority() <=> $other->getPriority()) > -1;
    }

    /**
     * See NodeInterface::isEmpty
     */
    public function isEmpty(bool $trim = true): bool
    {
        foreach ($this->children as $child)
            if (!$child->isEmpty($trim))
                return false;

        return !$this->isVoid();
    }

    /**
     * See NodeInterface::isVoid
     */
    public function isVoid(): bool
    {
        return $this->_isVoid;
    }

    /**
     * See NodeInterface::clean
     */
    public function clean(): void
    {
        foreach ($this->children as $child)
            $child->clean();

        $this->children = array_filter($this->children, fn($c) => !$c->isEmpty(false) || $c->isVoid());
    }

    /**
     * See NodeInterface::render
     */
    public function render(array $options = []): string
    {
        try {
            if (empty($this->children))
                return $this->doRender();

            $content = array_map(fn($c) => $c->render($options), $this->children);
            return $this->doRender(implode('', $content));
        } catch (\Exception $exception) {
            \Sentry\captureException($exception);
            return '';
        }
    }

    /**
     * Don't print too much information when var_dumping.
     */
    public function __debugInfo() {
        return [
            'tag' => $this->tag,
            'children' => $this->children,
        ];
    }
}
