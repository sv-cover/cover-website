<?php

namespace App\Tests\Markup;

use App\Markup\Markup;
use function App\Legacy\init;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DomCrawler\Test\Constraint as CC;
// see https://github.com/symfony/symfony/tree/7.2/src/Symfony/Component/DomCrawler/Test/Constraint

class MemberTest extends KernelTestCase
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

    /**
     * Test whether member block is rendered.
     * There's no inherent implementation to this, so let's only make sure the
     * provided content ends up in the result.
     */
    public function testMember(): void
    {
        $imageUrl = 'https://www.svcover.nl/images/cover_logo.png';

        // Test base tag
        $result = $this->render('[member name="Alan Turing"]Text[/member]');
        // $this->assertThat($result, new CC\CrawlerSelectorExists('.member-block'));
        $this->assertStringContainsString('Alan Turing', $result->text(), 'Name has to be rendered.');
        $this->assertStringContainsString('Text', $result->text(), 'Content has to be rendered.');

        // Position
        $result = $this->render('[member name="Ada Lovelace" position="Programmer"]Text[/member]');
        $this->assertStringContainsString('Ada Lovelace', $result->text(), 'Name has to be rendered.');
        $this->assertStringContainsString('Programmer', $result->text(), 'Position has to be rendered if provided.');
        $this->assertStringContainsString('Text', $result->text(), 'Content has to be rendered.');

        // Image
        $result = $this->render('[member name="Ada Lovelace" image="' . $imageUrl . '"]Text[/member]');
        $this->assertStringContainsString('Ada Lovelace', $result->text());
        $this->assertStringContainsString('Text', $result->text());
        $this->assertThat($result, new CC\CrawlerSelectorExists('img'));
        $this->assertEquals($imageUrl, $result->filter('img')->attr('src'));

        // Image and position
        $result = $this->render('[member name="Ada Lovelace" position="Programmer" image="' . $imageUrl . '"]Text[/member]');
        $this->assertStringContainsString('Ada Lovelace', $result->text(), 'Name has to be rendered.');
        $this->assertStringContainsString('Programmer', $result->text(), 'Position has to be rendered if provided.');
        $this->assertStringContainsString('Text', $result->text(), 'Content has to be rendered.');
        $this->assertThat($result, new CC\CrawlerSelectorExists('img'));
        $this->assertEquals($imageUrl, $result->filter('img')->attr('src'));
    }

    public function testNesting(): void
    {
        // Make sure that markup inside headings is rendered.
        $result = $this->render('[member name="Alan Turing"]Text [b]bold[/b] more text[/member]');
        $this->assertThat($result, new CC\CrawlerSelectorExists('strong'), 'Markup inside a member block should be rendered.');

        $result = $this->render(<<<END
            [member name="Alan Turing"]
                Paragraph

                Another paragraph
            [/member]
        END);
        $this->assertThat($result, new CC\CrawlerSelectorExists('p + p'), 'Paragraphs can exist inside headings.');
        $this->assertThat($result, new CC\CrawlerSelectorTextSame('p + p', 'Another paragraph', 'Paragraphs can exist inside headings.'));
    }
}
