<?php

declare(strict_types=1);

namespace Spipu\DashboardBundle\Tests\Functional;

use Spipu\CoreBundle\Tests\WebTestCase;
use Spipu\UiBundle\Tests\UiWebTestCaseTrait;

class UserInterfaceTest extends WebTestCase
{
    use UiWebTestCaseTrait;

    public function testMain(): void
    {
        $client = static::createClient();

        $this->adminLogin($client, 'Admin');

        $crawler = $client->clickLink('Dashboard');
        $this->assertEquals(200, $client->getResponse()->getStatusCode());
        $this->assertSame("Dashboard", $crawler->filter('h1')->text());
    }
}
