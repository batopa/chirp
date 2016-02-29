<?php

namespace Bato\Chirp;

use MongoDB\Client;
use TwitterAPIExchange;

/**
 * Chirp Class
 *
 * Help to create a cache for Twitter using MongoDB
 *
 */
class Chirp
{

    /**
     * The Database object
     *
     * @var \MongoDB\Database
     */
    private $db = null;

    /**
     * Twitter auth configuration
     *
     * @var array
     */
    private $authConf = array();

    /**
     * Twitter API base url
     *
     * @var string
     */
    private $apiBaseUrl = 'https://api.twitter.com/1.1/';

    /**
     * The instance of TwitterAPIExchange
     *
     * @var TwitterAPIExchange
     */
    private $twitter;

    /**
     * Constructor
     * Initialize TwitterAPIExchange and MongoDB connection
     *
     * $twitterAuthConf must contain
     * - oauth_access_token
     * - oauth_access_token_secret
     * - consumer_key
     * - consumer_secret
     *
     * $mongoConf must contain
     *  - db: the name of mongo database to use
     *
     *  and can contain
     * - uri
     * - uriOptions
     * - driverOptions
     *
     * used for MongoDB connection
     *
     * @see \TwitterAPIExchange for $twitterAuthConf
     * @see \MongoDB\Client for $mongoConf
     * @param array $twitterAuthConf
     * @param array $mongoConf
     */
    public function __construct(array $twitterAuthConf, array $mongoConf = array())
    {
        $mongoConf += [
            'uri' => '',
            'uriOptions' => [],
            'driverOptions' => [],
            'db' => ''
        ];
        $client = new Client($mongoConf['uri'], $mongoConf['uriOptions'], $mongoConf['driverOptions']);
        $this->db = $client->selectDatabase($mongoConf['db']);
        $this->authConf = $twitterAuthConf;
        $this->twitter = new TwitterAPIExchange($this->authConf);
    }

    /**
     * Return a complete Twitter resource url starting from an endpoint
     *
     * @param string $endpoint
     * @return string
     */
    public function resourceUrl($endpoint)
    {
        return $this->apiBaseUrl . $endpoint . '.json';
    }

    /**
     * Return the instance of MongoDB database
     *
     * @return \MongoDB\Database
     */
    public function getDb()
    {
        return $this->db;
    }

    /**
     * Starting from twitter endpoint return the related collection
     * It replaces "/" with "-", for example:
     *
     * endpoint "statuses/user_timeline" corresponds to "statuses-user_timeline" collection
     *
     * @param string $endpoint [description]
     * @return \MongoDB\Collection
     */
    public function getCollection($endpoint)
    {
        $name = preg_replace('/\/+/', '-', $endpoint);
        return $this->db->selectCollection($name);
    }

    /**
     * Perform a Twitter request
     *
     * @param string $endpoint the endpoint for example 'statuses/user_timeline'
     * @param array $query an array that specify query string to use with the endpoint
     * @param string $requestMethod the http request method
     * @return array
     */
    public function twitterRequest($endpoint, $query = [], $requestMethod = 'GET')
    {
        $url = $this->resourceUrl($endpoint);
        $queryString = !empty($query) ? '?' . http_build_query($query) :  '';
        $response = $this->twitter
            ->setGetfield($queryString)
            ->buildOauth($url, $requestMethod)
            ->performRequest();

        return $response;
    }

    /**
     * Read results from a collection (default self::collection)
     * using $filter to filter results
     *
     * @param string $endpoint
     * @param array $filter
     * @param array $options
     * @return cursor|array|\MongoDB\BSONDocument (if findOne used)
     */
    public function read($endpoint, array $filter = [], array $options = [])
    {
        $options += ['returnType' => 'array'];
        $collection = $this->getCollection($endpoint);
        // delegate to MongoDB\Collection::findOne()
        if (isset($options['limit']) && $options['limit'] === 1) {
            return $collection->findOne($filter, $options);
        }
        $cursor = $collection->find($filter, $options);
        return ($options['returnType'] == 'array') ? iterator_to_array($cursor) : $cursor;
    }

    /**
     * Perform twitter request and save results in db.
     * Only requests returing tweets are persisted.
     *
     * Possible $options are:
     * - query: an array used for compose query string
     * - grep: a string to search in 'text' field. Result containing that string will be saved
     * - require: only results with that field will be saved
     *
     * @param string $endpoint the twitter endpoint for example 'statuses/user_timeline'
     * @param array $options
     * @return array
     */
    public function write($endpoint, array $options = [])
    {
        $options += [
            'query' => [],
            'grep' => '',
            'require' => ''
        ];

        $options['query'] = array_filter($options['query']);

        // use the API
        $result = $this->twitterRequest($endpoint, $options['query']);

        $tweets = [];
        if (!empty($result)) {
            foreach ($result as $key => $tweet) {
                if (!isset($tweet['text']) || !isset($tweet['id_str'])) {
                    continue;
                }
                if ($options['require'] && empty($tweet[$options['require']])) {
                    continue;
                }
                if ($options['grep'] && stristr($tweet['text'], $options['grep']) === false) {
                    continue;
                }

                $tweets[$key] = $tweet;

                $collection = $this->getCollection($endpoint);
                $tweetInMongo = $collection->findOne(['id_str' => $tweet['id_str']]);
                if (empty($tweetInMongo)) {
                    $result = $collection->insertOne($tweet);
                }
            }
        }

        return [
            'saved' => $tweets,
            'read'=> $result
        ];
    }

}
