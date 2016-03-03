<?php

namespace Bato\Chirp\Test;

use Bato\Chirp\Chirp;

class ChirpTest extends \PHPUnit_Framework_TestCase
{
    private $twitterAuth = [
        'oauth_access_token' => '',
        'oauth_access_token_secret' => '',
        'consumer_key' => '',
        'consumer_secret' => ''
    ];

    private $dbName = 'test_chirp';

    public function testTwitterAPIExchangeMissingConf()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Make sure you are passing in the correct parameters');
        $chirp = new Chirp([]);
    }

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
        $chirp = new Chirp($this->twitterAuth, ['db' => $this->dbName]);
        $response = $chirp->twitterRequest('statuses/user_timeline');
        $response = json_decode($response, true);
        $expected = [
            'errors' => [
                [
                    'code' => 215,
                    'message' => 'Bad Authentication data.'
                ]
            ]
        ];
        $this->assertEquals($expected, $response);
    }
}
