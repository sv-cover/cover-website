<?php

namespace App\Tests\Markup;

use App\Markup\Markup;
use function App\Legacy\init;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint as CC;
// see https://github.com/symfony/symfony/tree/7.2/src/Symfony/Component/DomCrawler/Test/Constraint

class UrlTest extends KernelTestCase
{
    protected Markup $markup;

    protected function setUp(): void
    {
        self::bootKernel();
        init(self::$kernel);
        $this->markup = static::getContainer()->get(Markup::class);
    }

    protected function render(string $markup): Crawler
    {
        return new Crawler($this->markup->render($markup));
    }

    public function testBaseTag(): void
    {
        $url = 'https://example.com';
        $text = 'Link to example.com';

        $result = $this->render("[url={$url}]{$text}[/url]");

        $this->assertThat($result, new CC\CrawlerSelectorExists('p > a'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p > a', $text));
        $this->assertThat($result, new CC\CrawlerSelectorAttributeValueSame('p > a', 'href', $url));
    }

    public function testClassTag(): void
    {
        $result = $this->render('[url.button.is-primary.is-2=https://example.com]Text[/url]');

        $this->assertStringContainsString('button', $result->filter('a')->attr('class'));
        $this->assertStringContainsString('is-primary', $result->filter('a')->attr('class'));
        // class can contain number
        $this->assertStringContainsString('is-2', $result->filter('a')->attr('class'));
        // $this->assertThat($result, new CC\CrawlerSelectorAttributeValueSame('a', 'class', 'button is-primary'));
    }

    public function testInternal(): void
    {
        $url = self::$kernel->getContainer()->getParameterBag()->get('default_uri') . '/test';

        $result = $this->render("[url={$url}]Text[/url]");

        $this->assertEquals(null, $result->filter('a')->attr('target'));
        $this->assertThat($result, new CC\CrawlerSelectorAttributeValueSame('a', 'rel', 'nofollow'));
    }

    public function testExternal(): void
    {
        $url = 'https://www.example.com';

        $result = $this->render("[url={$url}]Text[/url]");

        $this->assertThat($result, new CC\CrawlerSelectorAttributeValueSame('a', 'target', '_blank'));
        $this->assertStringContainsString('noopener', $result->filter('a')->attr('rel'));
        $this->assertStringContainsString('noreferrer', $result->filter('a')->attr('rel'));
        $this->assertStringContainsString('nofollow', $result->filter('a')->attr('rel'));
    }

    public function testNakedUrl(): void
    {
        $url = 'https://svcover.nl';

        $result = $this->render($url);

        $this->assertThat($result, new CC\CrawlerSelectorExists('a'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('a', $url));
        $this->assertThat($result, new CC\CrawlerSelectorAttributeValueSame('a', 'href', $url));

        $result = $this->render("{$url} end");

        $this->assertThat($result, new CC\CrawlerSelectorExists('p > a'));
        $this->assertThat($result, new CC\CrawlerSelectorTextContains('a', $url));
        $this->assertThat($result, new CC\CrawlerSelectorAttributeValueSame('a', 'href', $url));
        $this->assertThat($result, new CC\CrawlerSelectorTextContains('p', 'end'));

        $result = $this->render("start {$url}");

        $this->assertThat($result, new CC\CrawlerSelectorExists('p > a'));
        $this->assertThat($result, new CC\CrawlerSelectorTextContains('a', $url));
        $this->assertThat($result, new CC\CrawlerSelectorAttributeValueSame('a', 'href', $url));
        $this->assertThat($result, new CC\CrawlerSelectorTextContains('p', 'start'));

        $result = $this->render("start {$url} end");

        $this->assertThat($result, new CC\CrawlerSelectorExists('p > a'));
        $this->assertThat($result, new CC\CrawlerSelectorTextContains('a', $url));
        $this->assertThat($result, new CC\CrawlerSelectorAttributeValueSame('a', 'href', $url));
        $this->assertThat($result, new CC\CrawlerSelectorTextContains('p', 'start'));
        $this->assertThat($result, new CC\CrawlerSelectorTextContains('p', 'end'));
    }

    public function testNakedEmail(): void
    {
        $email = 'board@svcover.nl';
        $result = $this->render($email);

        $this->assertThat($result, new CC\CrawlerSelectorExists('a'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('a', $email));
        $this->assertThat($result, new CC\CrawlerSelectorAttributeValueSame('a', 'href', "mailto:{$email}"));

        $result = $this->render("{$email} end");

        $this->assertThat($result, new CC\CrawlerSelectorExists('p > a'));
        $this->assertThat($result, new CC\CrawlerSelectorTextContains('a', $email));
        $this->assertThat($result, new CC\CrawlerSelectorAttributeValueSame('a', 'href', "mailto:{$email}"));
        $this->assertThat($result, new CC\CrawlerSelectorTextContains('p', 'end'));

        $result = $this->render("start {$email}");

        $this->assertThat($result, new CC\CrawlerSelectorExists('p > a'));
        $this->assertThat($result, new CC\CrawlerSelectorTextContains('a', $email));
        $this->assertThat($result, new CC\CrawlerSelectorAttributeValueSame('a', 'href', "mailto:{$email}"));
        $this->assertThat($result, new CC\CrawlerSelectorTextContains('p', 'start'));

        $result = $this->render("start {$email} end");

        $this->assertThat($result, new CC\CrawlerSelectorExists('p > a'));
        $this->assertThat($result, new CC\CrawlerSelectorTextContains('a', $email));
        $this->assertThat($result, new CC\CrawlerSelectorAttributeValueSame('a', 'href', "mailto:{$email}"));
        $this->assertThat($result, new CC\CrawlerSelectorTextContains('p', 'start'));
        $this->assertThat($result, new CC\CrawlerSelectorTextContains('p', 'end'));
    }

    public function testNesting(): void
    {
        // url > inline
        $result = $this->render(<<<END
            [url=https://www.example.com]
                [b]bold[/b]
            [/url]
        END);

        $this->assertThat($result, new CC\CrawlerSelectorExists('p > a'));
        $this->assertThat($result, new CC\CrawlerSelectorExists('p > a > strong'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p > a > strong', 'bold'));

        // inline > url
        $result = $this->render(<<<END
            [b]
                [url=https://www.example.com]
                    text
                [/url]
            [/b]
        END);

        $this->assertThat($result, new CC\CrawlerSelectorExists('p > strong > a'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p > strong > a', 'text'));

        // url > block (> p)
        $result = $this->render(<<<END
            [url=https://www.example.com]
                [center]centered[/center]
            [/url]
        END);
        $this->assertThat($result, new CC\CrawlerSelectorExists('a > div'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('a > div > p', 'centered'));

        // block (> p) > url
        $result = $this->render(<<<END
            [center]
                [url=https://www.example.com]
                    text
                [/url]
            [/center]
        END);
        $this->assertThat($result, new CC\CrawlerSelectorExists('div > p > a'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('div > p > a', 'text'));


        // block (> p) > url > inline
        $result = $this->render(<<<END
            [center]
                [url=https://www.example.com]
                    [b]bold[/b]
                [/url]
            [/center]
        END);
        $this->assertThat($result, new CC\CrawlerSelectorExists('div > p > a > strong'));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('div > p > a > strong', 'bold'));

        // inline > url > block (should not work!)
        $result = $this->render(<<<END
            [b]
                [url=https://www.example.com]
                    [center]centered[/center]
                [/url]
            [/b]
        END);
        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('strong > a')));
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('a > div > p', 'centered'));

        // url > url (not allowed!)
        $result = $this->render(<<<END
            [url=https://www.example.com]
                [url=https://svcover.nl]nested[/url]
            [/url]
        END);

        $this->assertThat($result, new CC\CrawlerSelectorExists('p > a'));
        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('p > a > a')));
        $this->assertThat($result, new CC\CrawlerSelectorTextContains('p > a', 'nested'));
        $this->assertThat($result, new CC\CrawlerSelectorAttributeValueSame('p > a', 'href', "https://www.example.com"));



        // url > something > url (not allowed!)
        $result = $this->render(<<<END
            [url=https://www.example.com]
                [b]
                    [url=https://svcover.nl]nested[/url]
                [/b]
            [/url]
        END);

        $this->assertThat($result, new CC\CrawlerSelectorExists('p > a > strong'));
        $this->assertThat($result, $this->logicalNot(new CC\CrawlerSelectorExists('p > a > strong > a')));
        $this->assertThat($result, new CC\CrawlerSelectorTextContains('p > a > strong', 'nested'));
        $this->assertThat($result, new CC\CrawlerSelectorAttributeValueSame('p > a', 'href', "https://www.example.com"));
    }
}
