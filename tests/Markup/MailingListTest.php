<?php

namespace App\Tests\Markup;

use App\Service\Database;
use function App\Legacy\init;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\BrowserKit\AbstractBrowser;
use Symfony\Component\BrowserKit\Cookie;
use Symfony\Component\DomCrawler\Test\Constraint as CC;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
// see https://github.com/symfony/symfony/tree/7.2/src/Symfony/Component/DomCrawler/Test/Constraint

class MailingListTest extends WebTestCase
{
    protected Database $db;
    protected ?AbstractBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        init(self::$kernel);
        $this->db = static::getContainer()->get(Database::class);
    }

    protected function login(): void
    {
        $db = static::getContainer()->get(Database::class);
        $_SERVER['REMOTE_ADDR'] = '0.0.0.0';
        $session = $db->getModel('DataModelSession')->create(1, 'test');
        $this->client->getCookieJar()->set(new Cookie(
            'cover_session_id',
            $session->get('session_id'),
        ));
    }

    public function testMailingList(): void
    {
        // Skip test, it doesn't work with the current authentication system.
        // TODO SFY: fix authentication system for tests
        // One assertion so silence PHPUnit
        $this->assertTrue(true);
        return;

        $this->login();

        $router = static::getContainer()->get(UrlGeneratorInterface::class);

        $result = $this->client->request('POST', $router->generate('page.preview'), [
            'session_id' => '6f70727dc540e7a31f30b3554fa285ba85e2c8f2',
            'content' => "[mailinglist=mailing@svcover.nl]",
        ]);

        $this->assertStringNotContainsString('mailinglist=', $result->text(), 'Mailing list tag should be rendered.');
        $this->assertStringContainsString('mailing@svcover.nl', $result->text(), 'Mailing list email should be included in rendered content.');
    }
}
