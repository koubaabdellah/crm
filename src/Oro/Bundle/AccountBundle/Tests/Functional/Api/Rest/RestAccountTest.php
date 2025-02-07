<?php

namespace Oro\Bundle\AccountBundle\Tests\Functional\Api\Rest;

use Oro\Bundle\AccountBundle\Entity\Account;
use Oro\Bundle\TestFrameworkBundle\Test\WebTestCase;

class RestAccountTest extends WebTestCase
{
    protected function setUp(): void
    {
        $this->initClient([], $this->generateWsseAuthHeader());
    }

    public function testCreate()
    {
        $doctrine = $this->getContainer()->get('doctrine');
        $manager = $doctrine->getManagerForClass(Account::class);
        $manager->createQuery(sprintf('DELETE FROM %s', Account::class))->execute();

        $request = [
            'account' => [
                'name' => 'Account_name_' . mt_rand(),
                'owner' => '1',
            ]
        ];

        $this->client->jsonRequest(
            'POST',
            $this->getUrl('oro_api_post_account'),
            $request
        );

        $result = $this->getJsonResponseContent($this->client->getResponse(), 201);
        $this->assertArrayHasKey('id', $result);

        $request['id'] = $result['id'];
        return $request;
    }

    /**
     * @param array $request
     * @depends testCreate
     * @return array
     */
    public function testGet(array $request)
    {
        $this->client->jsonRequest(
            'GET',
            $this->getUrl('oro_api_get_accounts')
        );

        $result = $this->getJsonResponseContent($this->client->getResponse(), 200);

        $id = $request['id'];
        $result = array_filter(
            $result,
            function ($a) use ($id) {
                return $a['id'] == $id;
            }
        );

        $this->assertNotEmpty($result);
        $this->assertEquals($request['account']['name'], reset($result)['name']);

        $this->client->jsonRequest(
            'GET',
            $this->getUrl('oro_api_get_account', ['id' => $request['id']])
        );

        $result = $this->getJsonResponseContent($this->client->getResponse(), 200);

        $this->assertEquals($request['account']['name'], $result['name']);
        $this->assertTrue(array_key_exists('lifetimeValue', $result));
    }

    /**
     * @depends testCreate
     * @depends testGet
     */
    public function testUpdate(array $request)
    {
        $request['account']['name'] .= '_Updated';
        $this->client->jsonRequest(
            'PUT',
            $this->getUrl('oro_api_put_account', ['id' => $request['id']]),
            $request
        );
        $result = $this->client->getResponse();

        $this->assertEmptyResponseStatusCodeEquals($result, 204);

        $this->client->jsonRequest(
            'GET',
            $this->getUrl('oro_api_get_account', ['id' => $request['id']])
        );

        $result = $this->getJsonResponseContent($this->client->getResponse(), 200);

        $this->assertEquals(
            $request['account']['name'],
            $result['name']
        );
    }

    /**
     * @param array $request
     * @depends testCreate
     */
    public function testList($request)
    {
        $this->client->jsonRequest(
            'GET',
            $this->getUrl('oro_api_get_accounts')
        );

        $result = $this->getJsonResponseContent($this->client->getResponse(), 200);
        $this->assertEquals(1, count($result));
    }

    /**
     * @depends testCreate
     * @depends testList
     */
    public function testDelete(array $request)
    {
        $this->client->jsonRequest(
            'DELETE',
            $this->getUrl('oro_api_delete_account', ['id' => $request['id']])
        );
        $result = $this->client->getResponse();
        $this->assertEmptyResponseStatusCodeEquals($result, 204);
        $this->client->jsonRequest('GET', $this->getUrl('oro_api_get_account', ['id' => $request['id']]));
        $result = $this->client->getResponse();
        $this->assertJsonResponseStatusCodeEquals($result, 404);
    }
}
