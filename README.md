# Chirp

A simple PHP library to use MongoDB as cache for Twitter.

## Requirements

**Chirp** requires [MongoDB](https://www.mongodb.org) and [MongoDB PHP Driver](http://php.net/manual/en/set.mongodb.php)

## Install

Using [composer](https://getcomposer.org/doc/00-intro.md#installation-linux-unix-osx) installed globally

```bash
composer require batopa/chirp
```

## Basic use

```php
require 'vendor/autoload.php';

use Bato\Chirp\Chirp;

// set your Twitter auth conf
$twitterAuth = [
    'oauth_access_token' => 'xxx',
    'oauth_access_token_secret' => 'yyy',
    'consumer_key' => 'www',
    'consumer_secret' => 'zzz'
];


$mongoDbAuth = [
    // the MongoDB database name to use
    'db'  => 'chirp',
    // the MongoDB connection uri. Do not set to use default
    // 'uri' => 'mongodb://user:password@localhost:27017'
];

// instantiate Chirp with Twitter auth and MongoDB conf
$chirp = new Chirp($twitterAuth, $mongoDbAuth);

// Perform Twitter API request GET statuses/user_timeline of @batopa user
// and save tweets in MongoDB
$result = $chirp->write('statuses/user_timeline', [
    // contains parameter used to build the query string
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
    // save only if '#chirp' or 'cache' are in 'text' key
    'grep' => [
        'text' => ['#chirp', 'cache']
    ],
    // save only if 'entities' is not empty
    'require' => ['entities']
]);
```

`grep` and `require` can be used to traversing the result set

```php
$chirp->write('statuses/user_timeline', [
    // contains parameter used for build the query string
    'query' => [
        'screen_name' => 'batopa'
    ],
    // save only if 'user' has the key 'location'
    // populated with a string containing 'IT'
    'grep' => [
        'user.location' => 'IT'
    ],
    // save only if 'entities' has the key 'hashtags' valorized
    'require' => ['entities.hashtags']
]);
```

### Reading

You can take advantage of MongoDB to filter,
sort and manipulate the tweets read, for example

```php
// Read data from MongoDB
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

### Using directly MongoDB and Twitter API

If you need you can get the db or a collection and use them for your purposes

```php
// get db
$db = $chirp->getDb();

// get collection
$collection = $chirp->getCollection('statuses/user_timeline');
```

You can also send requests to Twitter API using `Chirp::request()` or
getting the instance of [TwitterOAuth](https://twitteroauth.com)

```php
$twitter = $chirp->getTwitter();
```

and use directly it.
