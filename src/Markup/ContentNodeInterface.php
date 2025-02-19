<?php

namespace App\Markup;

/**
 * Interface for nodes that (can) contain content directy without it being
 * wrapped in a paragraph. If used, the node's content wil never be wrapped in a
 * paragraph. If not used, any descending ContentNodes will be wrapped in a
 * paragraph at some level between the current node and content node.
 */
interface ContentNodeInterface
{
    /**
     * Add content to the node. Either directly, or by creating a ContentNode
     * and adding that as a child.
     */
    public function addContent(string $content): void;
}
