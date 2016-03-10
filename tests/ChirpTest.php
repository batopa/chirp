<?php

namespace Bato\Chirp\Test;

use Bato\Chirp\Chirp;
use Abraham\TwitterOAuth\TwitterOAuth;

class ChirpTest extends \PHPUnit_Framework_TestCase
{
    private $twitterAuth = [
        'oauth_access_token' => OAUTH_ACCESS_TOKEN,
        'oauth_access_token_secret' => OAUTH_ACCESS_TOKEN_SECRET,
        'consumer_key' => CONSUMER_KEY,
        'consumer_secret' => CONSUMER_SECRET
    ];

    private $dbName = 'test_chirp';

    public function testMongoInvalidUri()
    {
        $this->expectException(\MongoDB\Driver\Exception\InvalidArgumentException::class);
        $chirp = new Chirp($this->twitterAuth, ['uri' => 'xxx']);
    }

    public function testMongoMissingDatabase()
    {
        $this->expectException(\MongoDB\Driver\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('$databaseName is invalid: ');
        $chirp = new Chirp($this->twitterAuth);
    }

    public function testMongoDbSuccess()
    {
        $chirp = new Chirp($this->twitterAuth, ['db' => $this->dbName]);
        $db = $chirp->getDb();
        $this->assertInstanceOf('\MongoDB\Database', $db);

        $dbName = $db->getDatabaseName();
        $this->assertEquals($this->dbName, $dbName);
    }

    public function testCollectionName()
    {
        $testNames = [
            'statuses/user_timeline',
            '///statuses/user_timeline',
            '//statuses///user_timeline',
            '/statuses/user_timeline///',
            'statuses/user_timeline//'
        ];
        $expected = 'statuses-user_timeline';
        $chirp = new Chirp($this->twitterAuth, ['db' => $this->dbName]);
        foreach ($testNames as $name) {
            $collection = $chirp->getCollection('//statuses/user_timeline');
            $this->assertEquals($expected, $collection->getCollectionName());
        }
    }

    public function testTwitterAuthFailed()
    {
        $twitterConf = array_combine(
            array_keys($this->twitterAuth),
            array('xxx', 'yyy', 'www', 'sss')
        );
        $chirp = new Chirp($twitterConf, ['db' => $this->dbName]);
        $response = $chirp->request('statuses/user_timeline');
        $connection = $chirp->getTwitterConnection();
        $httpCode = $connection->getLastHttpCode();
        $this->assertEquals(401, $httpCode);
        $this->assertArrayHasKey('errors', $response);
    }

    public function testWrite()
    {
        foreach ($this->twitterAuth as $key => $value) {
            if (empty($value)) {
                $this->markTestSkipped(
                    'Missing ' . $key . ' in twitter configuration. Copy phpunit.xml.dist to phpunit.xml and fill constants'
                );
                return;
            }
        }

        $chirp = new Chirp($this->twitterAuth, ['db' => $this->dbName]);
        $chirp->getCollection('statuses/user_timeline')
            ->drop();

        // write
        $response = $chirp->write('statuses/user_timeline', [
            'query' => [
                'screen_name' => 'batopa',
                'count' => 10
            ]
        ]);
        $this->assertArrayHasKey('saved', $response);
        $this->assertArrayHasKey('read', $response);
        $this->assertCount(10, $response['read']);
        $this->assertCount(10, $response['saved']);

        // write again (no entry in db should be saved)
        $response = $chirp->write('statuses/user_timeline', [
            'query' => [
                'screen_name' => 'batopa',
                'count' => 10
            ]
        ]);
        $this->assertArrayHasKey('saved', $response);
        $this->assertArrayHasKey('read', $response);
        $this->assertCount(10, $response['read']);
        $this->assertCount(0, $response['saved']);
    }

    public function testRead()
    {
        foreach ($this->twitterAuth as $key => $value) {
            if (empty($value)) {
                $this->markTestSkipped(
                    'Missing ' . $key . ' in twitter configuration. Copy phpunit.xml.dist to phpunit.xml and fill constants'
                );
                return;
            }
        }

        $chirp = new Chirp($this->twitterAuth, ['db' => $this->dbName]);

        $result = $chirp->read('statuses/user_timeline');
        $this->assertCount(10, $result);

        $result = $chirp->read('statuses/user_timeline', [], [
            'projection' => [
                'created_at' => true,
                'user.screen_name' => true,
                'text' => true,
            ],
            'limit' => 1
        ]);

        $this->assertObjectHasAttribute('created_at', $result);
        $this->assertObjectHasAttribute('user', $result);
        $this->assertObjectHasAttribute('text', $result);
        $this->assertInstanceOf('\MongoDB\Model\BSONDocument', $result->user);
        $this->assertObjectNotHasAttribute('id_str', $result);
    }
}
