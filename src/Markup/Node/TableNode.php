<?php

namespace App\Markup\Node;

use App\Markup\Node\BlockRendererNode;

class TableNode extends BlockRendererNode
{
    public function clean(): void
    {
        if ($this->tag !== 'table') {
            parent::clean();
            return;
        }

        if (!empty($this->getChildren()))
            $rowLength = max(array_map(
                fn($n) => count($n->getChildren()),
                $this->getChildren() ?? []
            ));
        else
            $rowLength = 0;

        foreach ($this->getChildren() as $child)
            while (count($child->getChildren()) < $rowLength)
                $child->addNode(new TableNode(
                    renderer: $this->renderer,
                    tag: 'td',
                    token: ''
                ));

        // Now clean cell content, but don't remove empty cells from rows.
        foreach ($this->getChildren() as $child)
            foreach ($child->getChildren() as $grandchild)
                $grandchild->clean();

        $this->children = array_filter($this->children, fn($c) => !$c->isEmpty(false));
    }
}
