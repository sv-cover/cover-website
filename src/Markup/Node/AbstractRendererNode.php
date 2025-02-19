<?php

namespace App\Markup\Node;

use App\Markup\Node\AbstractNode;

/**
 * Abstract node that can be rendered by a renderer closure (function). This is
 * useful if the node's rendering depends on an (autowired) service. The render
 * function can then be defined on the parser, which is accessed by the
 * RendererNode as a closure.
 *
 * The renderer function takes the node's rendered content, it's tag and token,
 * with the following signature:
 *
 * function renderer(?string $content, string $tag, string $token): string
 */
abstract class AbstractRendererNode extends AbstractNode
{
    public function __construct(
        protected \Closure $renderer,
        protected string $token,
        string $tag,
        bool $isVoid = false,
    ) {
        parent::__construct($tag, $isVoid);
    }

    /**
     * The actual implementation for NodeInterface::render.
     * Calls the renderer closure.
     */
    protected function doRender(?string $content = null): string
    {
        return $this->renderer->__invoke($content, $this->tag, $this->token);
    }
}
