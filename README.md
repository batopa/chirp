# Chirp

A simple library to use MongoDB as cache for Twitter.

## Install

Using composer

```bash
composer require batopa/chirp
```
## Basic use

```php
<?php

use Bato\Chirp\Chirp

// set your Twitter auth conf
$twitterAuth = [
    'oauth_access_token' => 'xxx',
    'oauth_access_token_secret' => 'yyy',
    'consumer_key' => 'www',
    'consumer_secret' => 'zzz'
];


$mongoAuth = [
    // the MongoDB database name to use
    'db'  => 'chirp',
    // the MongoDB connection uri. Do not set to use default
    // 'uri' => 'mongodb://user:password@localhost:27017'
];

// instantiate Chirp with Twitter auth and MongoDB conf
$chirp = new Chirp();

// Perform GET statuses/user_timeline of @batopa user on Twitter
// and save tweets in MongoDB
$result = $chirp->write('statuses/user_timeline', [
    // contains parameter used for build the query string
    'query' => [
        'screen_name' => 'batopa'
    ]
]);

// $result will be an array as
// [
//     'saved' => [array of tweets saved],
//     'read' => [array of tweets returned from Twitter API]
// ]

// read data saved previously
$tweets = $chirp->read('statuses/user_timeline');
```

The MongoDB collection used is based on Twitter request endpoint
replacing `/` with `-` character, so:
* `statuses/user_timeline` request become `statuses-user_timeline` collection name
* `/statuses///home_timeline//` request become `statuses-home_timeline` collection name

## Advanced use

### Saving

You can just save some of tweets returned

```php
// Save only tweets under some conditions
$chirp->write('statuses/user_timeline', [
    // contains parameter used for build the query string
    'query' => [
        'screen_name' => 'batopa'
    ],
    // save only if '#chirp' is in 'text' key
    'grep' => '#chirp',
    // save only if 'entities' is not empty
    'require' => 'entities'
]);
```

# Reading

You can take advantage of MongoDB to filter,
sort and manipulate the tweets read

```php
// Save only tweets under some conditions
$chirp->read('statuses/user_timeline', [
    // filter
    [
        'user.screen_name' => 'batopa'
    ],
    // options
    [
        // order by id_str desc
        'sort' => ['id_str' => -1],
        // return only some fields
        'projection' => [
            'created_at' => true,
            'user.screen_name' => true,
            'text' => true,
            'id_str' => true,
            'media_url' => true,
            'entities' => true
        ]
    ]
]);
```
