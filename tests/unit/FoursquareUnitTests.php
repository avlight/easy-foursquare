<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Lib\FoursquareClient;

final class FoursquareUnitTests extends TestCase
{
	public function testValidGetRedirectUrlResult() {
        $result = FoursquareClient::getRedirectUrl([
            'endpoint' => 'goodJob',
        ]);

        $this->assertNotEmpty($result);

        $urlParts = parse_url($result);
        $query = $urlParts['query'];
        unset($urlParts);

        $this->assertEquals($query, "endpoint=goodJob");
    }
	
	public function testValidateClientCredentialsExistence() {
        $clientKey = FoursquareClient::getClientKey();
        $clientSecret = FoursquareClient::getClientSecret();

        $this->assertNotEmpty($clientKey);
        $this->assertNotEmpty($clientSecret);

        $this->assertEquals(strlen($clientKey), 48);
        $this->assertEquals(strlen($clientSecret), 48);
	}

	public function testValidResultForRealEndpointCall() {
        $fs = new FoursquareClient();
        $result = $fs->endpointCall("venues/categories", [
            // params here
        ]);

        $this->assertNotEmpty($result);
        $this->assertInternalType("array", $result);
        $this->assertArrayHasKey("meta", $result);
        $this->assertArrayHasKey("code", $result["meta"]);
        $this->assertEquals(200, $result["meta"]["code"]);
    }
}