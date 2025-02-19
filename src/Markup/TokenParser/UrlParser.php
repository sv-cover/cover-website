<?php

namespace App\Markup\TokenParser;

use App\Markup\Node\UrlNode;
use App\Markup\NodeInterface;
use App\Markup\ParserToken;
use App\Markup\TagParserInterface;
use App\Markup\TokenParser\AbstractTokenParser;
use App\Markup\TokenProcessorInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Twig\Environment;

class UrlParser extends AbstractTokenParser implements TagParserInterface, TokenProcessorInterface
{
    public static function getDefaultPriority(): int
    {
        // Make sure we're (almost) last so we don't parse anything we're not supposed to.
        return -100;
    }

    public function __construct(
        private Environment $twig,
        private ContainerBagInterface $params,
    ) {
    }

    public function getTags(): iterable
    {
        yield [
            'name' => 'url',
        ];
    }

    public function parseNaked(string $unparsed): iterable
    {
        $emailRegex = '\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,10}\b';
        $urlRegex = '((([A-Za-z]{3,9}:(?:\/\/)?)[A-Za-z0-9.-]+|(?:www.)[A-Za-z0-9.-]+)((?:\/[\+~%\/.\w\-_]*)?\??(?:[\-\+=&;%@.\w_]*)#?(?:[\w]*))?)';
        $regex = sprintf('/(?P<url>%s)|(?P<email>%s)/i', $urlRegex, $emailRegex);

        while (preg_match($regex, $unparsed, $match, PREG_OFFSET_CAPTURE)) {
            if ($match[0][1])
                yield substr($unparsed, 0, $match[0][1]);

            if (isset($match['email']) && $match['email'][1] > -1)
                yield new ParserToken($match[0][0], $this, 'naked_email', ParserToken::TYPE_VOID);
            elseif (isset($match['url']) && $match['url'][1] > -1)
                yield new ParserToken($match[0][0], $this, 'naked_url', ParserToken::TYPE_VOID);
            else // impossible?
                yield $match[0][0];

            $unparsed = substr($unparsed, $match[0][1] + strlen($match[0][0]));
        }

        // Yield any leftover non-whitespace content.
        if (!empty($unparsed))
            yield $unparsed;
    }

    public function processTokens(iterable $tokens): iterable
    {
        // We can't be recursive!
        $depth = 0;

        foreach ($tokens as $token) {
            if (is_string($token)) {
                yield from $this->parseNaked($token);
            } elseif ($token->parser !== $this) {
                yield $token;
            } elseif ($token->type === ParserToken::TYPE_START) {
                $depth += 1;
                if ($depth === 1)
                    yield $token;
            } else {
                $depth -= 1;
                if ($depth === 0)
                    yield $token;
                elseif ($depth < 0)
                    $depth = 0;
            }
        }
    }

    public function render(?string $content, string $tag, string $token): string
    {
        $class = '';
        if ($tag === 'naked_url') {
            $url = (
                preg_match('~^https?://~', $token)
                ? $token :
                'https://' . $token
            );
            $content = (
                strlen($token) > 60
                ? (substr($token, 0, 28) . '…' . substr($token, -29))
                : $token
            );
        } elseif ($tag === 'naked_email') {
            $url = 'mailto:' . $token;
            $content = $token;
        } else {
            preg_match('/\[url(?P<class>(\.[a-z-\d]+)*)=(?P<url>.*?)\]/i', $token, $match);
            $url = $match['url'];
            $class = str_replace('.', ' ', $match['class']);
        }

        $host = \parse_url($url, \PHP_URL_HOST);
        $isExternal = (
            $host !== null
            && $host != \parse_url($this->params->get('default_uri'), \PHP_URL_HOST)
        );
        return $this->twig->render('markup/_link.html.twig', [
            'content' => $content,
            'is_external' => $isExternal,
            'class' => $class,
            'url' => $url,
        ]);
    }

    public function getNode(?string $tag, ?string $token): NodeInterface
    {
        return new UrlNode(
            renderer: $this->render(...),
            tag: $tag,
            token: $token,
            isVoid: str_starts_with($tag, 'naked_'),
        );
    }
}
