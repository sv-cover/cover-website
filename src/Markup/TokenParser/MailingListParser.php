<?php

namespace App\Markup\TokenParser;

use App\Markup\NodeInterface;
use App\Markup\TagParserInterface;
use App\Markup\Node\BlockRendererNode;
use App\Markup\TokenParser\AbstractTokenParser;
use Twig\Environment;

class MailingListParser extends AbstractTokenParser implements TagParserInterface
{
    public function __construct(
        private Environment $twig,
    ) {
    }

    public function getTags(): iterable
    {
        yield [
            'name' => 'mailinglist',
            'is_void' => true,
        ];
    }

    public function render(?string $content, string $tag, string $token): string
    {
        preg_match('/\[mailinglist=(?P<list_id>.*?)\]/i', $token, $match);
        return $this->twig->render('markup/_mailing_list.html.twig', [
            'list_id' => $match['list_id'],
        ]);
    }

    public function getNode(?string $tag, ?string $token): NodeInterface
    {
        return new BlockRendererNode(
            renderer: $this->render(...),
            tag: $tag,
            token: $token,
            isVoid: true,
        );
    }
}
