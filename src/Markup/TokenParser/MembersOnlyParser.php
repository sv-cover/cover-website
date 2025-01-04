<?php

namespace App\Markup\TokenParser;

use App\Legacy\Authentication\Authentication;
use App\Markup\NodeInterface;
use App\Markup\TagParserInterface;
use App\Markup\Node\BlockRendererNode;
use App\Markup\TokenParser\AbstractTokenParser;
use Twig\Environment;

class MembersOnlyParser extends AbstractTokenParser implements TagParserInterface
{
    public function __construct(
        private Authentication $auth,
        private Environment $twig,
    ) {
    }

    public function getTags(): iterable
    {
        yield [
            'name' => 'membersonly',
        ];
    }

    public function render(?string $content, string $tag, string $token): string
    {
        if ($this->auth->loggedIn)
            return trim($content);

        preg_match('/\[membersonly(=(?P<description>[^\]]+))?\]/i', $token, $match);
        return $this->twig->render('markup/_members_only.html.twig', [
            'content' => $content,
            'description' => $match['description'] ?? null,
        ]);
    }

    public function getNode(?string $tag, ?string $token): NodeInterface
    {
        return new BlockRendererNode(
            renderer: $this->render(...),
            tag: $tag,
            token: $token,
        );
    }
}
