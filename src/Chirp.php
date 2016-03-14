<?php
/**
 * This file is part of Chirp package
 *
 * Copyright (c) 2015 Alberto Pagliarini
 *
 * Licensed under the MIT license
 * https://github.com/batopa/chirp/blob/master/LICENSE
 */
namespace Bato\Chirp;

use MongoDB\Client;
use Abraham\TwitterOAuth\TwitterOAuth;

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
     * The instance of TwitterOAuth
     *
     * @var \Abraham\TwitterOAuth\TwitterOAuth
     */
    private $twitter;

    /**
     * Constructor
     * Initialize TwitterAPIExchange and MongoDB connection
     *
     * $twitterAuthConf must contain
     * - consumer_key
     * - consumer_secret
     * - oauth_access_token
     * - oauth_access_token_secret
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
     * @see \MongoDB\Client for $mongoConf
     * @param array $twitterAuthConf
     * @param array $mongoConf
     */
    public function __construct(array $twitterAuthConf, array $mongoConf = array())
    {
        $twitterAuthConf += [
            'consumer_key' => null,
            'consumer_secret' => null,
            'oauth_access_token' => null,
            'oauth_access_token_secret' => null
        ];
        $mongoConf += [
            'uri' => 'mongodb://localhost:27017',
            'uriOptions' => [],
            'driverOptions' => [],
            'db' => ''
        ];

        $this->twitter = new TwitterOAuth(
            $twitterAuthConf['consumer_key'],
            $twitterAuthConf['consumer_secret'],
            $twitterAuthConf['oauth_access_token'],
            $twitterAuthConf['oauth_access_token_secret']
        );
        $this->twitter->setDecodeJsonAsArray(true);

        $client = new Client($mongoConf['uri'], $mongoConf['uriOptions'], $mongoConf['driverOptions']);
        $this->db = $client->selectDatabase($mongoConf['db']);
    }

    /**
     * Return TwitterOAuth instance
     *
     * @return \Abraham\TwitterOAuth\TwitterOAuth
     */
    public function getTwitterConnection()
    {
        return $this->twitter;
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
     * It replaces "/" with "-" after removing trailing "/", for example:
     *
     * endpoint "statuses/user_timeline" corresponds to "statuses-user_timeline" collection
     *
     * @param string $endpoint [description]
     * @return \MongoDB\Collection
     */
    public function getCollection($endpoint)
    {
        $name = trim($endpoint, '/');
        $name = preg_replace('/\/+/', '-', $name);
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
    public function request($endpoint, $query = [], $requestMethod = 'get')
    {
        $validMethods = ['get', 'post', 'put', 'delete'];
        $requestMethod = strtolower($requestMethod);
        if (!in_array($requestMethod, $validMethods)) {
            throw new \UnexpectedValueException('Unsupported http request method ' . $requestMethod);
        }
        $response = $this->twitter
            ->{$requestMethod}($endpoint, $query);

        return $response;
    }

    /**
     * Read results from a collection (default self::collection)
     * using $filter to filter results
     *
     * If $options['limit'] = 1 return a \MongoDB\BSONDocument object
     * else return an array or a cursor depending from $options['returnType']
     *
     * @param string $endpoint
     * @param array $filter
     * @param array $options
     * @return object|array
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
        $response = $this->request($endpoint, $options['query']);
        if (empty($response)) {
            return ['saved' => [], 'read'=> []];
        }

        if (array_key_exists('errors', $response)) {
            return $response;
        }

        $collection = $this->getCollection($endpoint);
        $tweets = $this->filterToSave($response, $collection, $options);
        if (!empty($tweets)) {
            $collection->insertMany($tweets);
        }

        return [
            'saved' => $tweets,
            'read'=> $response
        ];
    }

    /**
     * Given an array of tweets and a collection
     * return the tweets that missing from the collection
     *
     * @see self::write() for $options
     * @param array $tweets
     * @param \MongoDB\Collection $collection
     * @param array $options
     * @return array
     */
    private function filterToSave(array $tweets, \MongoDB\Collection $collection, array $options)
    {
        $toSave = [];
        foreach ($tweets as $key => $tweet) {
            if (!isset($tweet['text']) || !isset($tweet['id_str'])) {
                continue;
            }
            if ($options['require'] && empty($tweet[$options['require']])) {
                continue;
            }
            if ($options['grep'] && stristr($tweet['text'], $options['grep']) === false) {
                continue;
            }

            $countTweets = $collection->count(['id_str' => $tweet['id_str']]);
            if ($countTweets === 0) {
                $toSave[] = $tweet;
            }
        }
        return $toSave;
    }
}
