<?php

namespace App\Markup;

interface NodeInterface
{
    const PRIORITY_BLOCK = 10;
    const PRIORITY_PARAGRAPH = 5;
    const PRIORITY_INLINE = 0;

    /**
     * Add a child node. (Or don't, if a node doesn't support children.)
     */
    public function addNode(NodeInterface $node): void;

    /**
     * Decide if this node can contain another node.
     */
    public function canContain(NodeInterface $other): bool;

    /**
     * Clean the node. That might mean removing any children that shouldn't be
     * rendered, or adding extra children to be compliant with the HTML spec.
     */
    public function clean(): void;

    /**
     * Return array with all child nodes.
     */
    public function getChildren(): array;

    /**
     * Return this node's priority. This is used to determine whether one node
     * can contain another.
     */
    public function getPriority(): int;

    /**
     * Get the tag for this node. This is used to correctly a node while
     * processing tokens.
     */
    public function getTag(): string;

    /**
     * Decide if the node is empty. If trim is true, a node containing only
     * whitespace will be considered empty.
     */
    public function isEmpty(bool $trim = true): bool;

    /**
     * Decide if the node is void (childless on purpose). This is needed to not
     * prune too many nodes while cleaning. E.g. image tags are childless, and
     * thus appear empty but should not be pruned.
     */
    public function isVoid(): bool;

    /**
     * Render this node and it's children into a string.
     */
    public function render(array $options = []): string;
}
