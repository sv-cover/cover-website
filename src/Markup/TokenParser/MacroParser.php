<?php

namespace App\Markup\TokenParser;

use App\DataModel\DataModelCommissie;
use App\Exception\NotFoundException;
use App\Markup\NodeInterface;
use App\Markup\ParserToken;
use App\Markup\TokenProcessorInterface;
use App\Markup\Node\InlineRendererNode;
use App\Markup\TokenParser\AbstractTokenParser;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

/**
 * Call a macro and render its results. Syntax:
 *
 * [[ macro_name(<argument>, <argument>...) ]]
 */
class MacroParser extends AbstractTokenParser implements TokenProcessorInterface
{
    private array $macros = [
        'commissie' => [
            'function' => 'renderCommittee',
            'node' => InlineRendererNode::class,
        ],
        'committee' => [
            'function' => 'renderCommittee',
            'node' => InlineRendererNode::class,
        ],
    ];

    private string $regex = '/\[\[\s*(?P<macro>[a-z_]+)\((?P<args>.*?)\)\s*\]\]/';

    public function __construct(
        private DataModelCommissie $committeeModel,
        private Environment $twig,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    private function parseToken(string $unparsed): iterable
    {
        while (preg_match($this->regex, $unparsed, $match, PREG_OFFSET_CAPTURE)) {
            if ($match[0][1])
                yield substr($unparsed, 0, $match[0][1]);

            yield new ParserToken($match[0][0], $this, $match['macro'][0], ParserToken::TYPE_VOID);

            $unparsed = substr($unparsed, $match[0][1] + strlen($match[0][0]));
        }

        // Yield any leftover non-whitespace content.
        if (!empty($unparsed))
            yield $unparsed;
    }

    public function processTokens(iterable $tokens): iterable
    {
        foreach ($tokens as $token) {
            if (!is_string($token))
                yield $token;
            else
                yield from $this->parseToken($token);
        }
    }

    private function renderCommittee(string $name): string
    {
        try {
            $committee = $this->committeeModel->get_from_name($name);
            $url = $this->urlGenerator->generate('committees.single', ['slug' => $committee['login']]);
            $name = $this->twig->getRuntime('Twig\Runtime\EscaperRuntime')->escape($committee['naam'], 'html');
            return "<a href=\"$url\">$name</a>";
        } catch (NotFoundException $e) {
            return $name ?: 'Unkown Committee';
        }
    }

    public function render(?string $content, string $tag, string $token): string
    {
        preg_match($this->regex, $token, $match);

        if (!isset($this->macros[$match['macro']]))
            return $token;

        return call_user_func_array(
            [$this, $this->macros[$match['macro']]['function']],
            preg_split('/\s*,\s*/', $match['args']) ?? [],
        );
    }

    public function getNode(?string $tag, ?string $token): NodeInterface
    {
        $node = $this->macros[$tag]['node'] ?? InlineRendererNode::class;
        return new $node(
            renderer: $this->render(...),
            tag: $tag,
            token: $token,
            isVoid: true,
        );
    }
}
