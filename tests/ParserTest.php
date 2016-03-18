<?php

namespace Bato\Chirp\Test;

use Bato\Chirp\Utility\Parser;

class ParserTest extends \PHPUnit_Framework_TestCase
{
    public function testNormalize()
    {
        $testNames = [
            'statuses/user_timeline',
            '///statuses/user_timeline',
            '//statuses///user_timeline',
            '/statuses/user_timeline///',
            'statuses/user_timeline//'
        ];
        $expected = 'statuses-user_timeline';
        foreach ($testNames as $name) {
            $this->assertEquals($expected, Parser::normalize($name));
        }
    }

    public function testHasKey()
    {
        $test = [
            'name' => [
                'name2' => [
                    'name3' => 'value'
                ]
            ]
        ];

         $this->assertTrue(Parser::hasKey($test, 'name.name2.name3'));
         $this->assertTrue(Parser::hasKey($test, 'name.name2.name3', '/alu/'));
         $this->assertFalse(Parser::hasKey($test, 'name.name2.name3', '/^alu/'));
    }

    public function testMatch()
    {
        $test = [
            'name' => [
                'name2' => [
                    'name3' => 'yeppa'
                ],
                'name4' => 'yuppi'
            ],
            'surname' => 'yappi'
        ];

        $match = Parser::match($test, [
            'require' => ['surname', 'name', 'name.name2.name3', 'name.name4']
        ]);
        $this->assertTrue($match);

        $match = Parser::match($test, [
            'require' => ['surname', 'name', 'name.name2.name5', 'name.name4']
        ]);
        $this->assertFalse($match);
    }

    public function testMatchGrep()
    {
        $test = [
            'name' => [
                'name2' => [
                    'name3' => 'yep pa'
                ],
                'name4' => 'pa red pa'
            ],
            'surname' => '#blue sky'
        ];

        $match = Parser::match($test, [
            'grep' => [
                'name.name2.name3' => ['yep', 'blue', 'red']
            ]
        ]);
        $this->assertTrue($match);

        $match = Parser::match($test, [
            'grep' => [
                'name.name2.name3' => ['yep', 'blue', 'red'],
                'name.name4' => 'red',
                'surname' => ['blue']
            ]
        ]);
        $this->assertTrue($match);

        $match = Parser::match($test, [
            'grep' => [
                'name.name2.name3' => ['ye', 'blue', 'red']
            ]
        ]);
        $this->assertFalse($match);
    }
}
