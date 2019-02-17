<?php

namespace tests;

use PHPUnit\Framework\TestCase;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;

require_once '/var/www/html/wp-content/plugins/branch-helper/vendor/autoload.php';

class ApiTest extends TestCase
{
    public $client;

    public function setUp()
    {
        $this->client = new Client([
            'base_uri' => 'http://localhost/wp-json/branch-helper/v1/',
        ]);
    }

    /** @test */
    function it_can_check_connection()
    {
        // When
        $response = $this->client->get('check-connection');
        $json = json_decode($response->getBody()->getContents(), true);

        // Then
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('http://localhost', $json['site_url']);
    }

    /** @test */
    function it_can_receive_deployment_webhook_trigger()
    {
        // When
        try{
            $response = $this->client->post('trigger-deployment', [
                'json' => [
                    'package' => '123123123',
                ],
            ]);
        } catch (TransferException $e) {
            var_dump($e->getResponse()->getBody()->getContents()); die();
        }

        $json = json_decode($response->getBody()->getContents(), true);

        // Then
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('123123123', $json['package']);
    }
}
