<?php

namespace Cornforth\Tests;

use Cornforth\Tests\Exceptions\UnequalArgumentListsException;
use Cornforth\Zxcvbn\Matcher;
use PHPUnit\Framework\TestCase;

/**
*  Corresponding Class to test YourClass class
*
*  For each class in your library, there should be a corresponding Unit-Test for it
*  Unit-Tests should be as much as possible independent from other test going on.
*
*  @author Adam Cornforth
*/
class MatcherTest extends TestCase
{
    /**
     * @var Matcher
     */
    private $sut;

    protected function setUp()
    {
        $this->sut = new Matcher();
        parent::setUp();
    }

    /**
    * Just check if the YourClass has no syntax error
    *
    * This is just a simple check to make sure your library has no syntax error. This helps you troubleshoot
    * any typo before you even use this library in a real project.
    *
    */
    public function testIsThereAnySyntaxError()
    {
        $this->assertTrue(is_object($this->sut));
        unset($this->sut);
    }

    /**
    * Test that we are loading the adjacency graphs.
    */
    public function testAdjacencyGraphsAreLoaded()
    {
        $this->assertNotEmpty($this->sut->getAdjacencyGraphs());
        $this->assertArrayHasKey('qwerty', $this->sut->getAdjacencyGraphs());
        unset($this->sut);
    }

    /**
    * Test that we are loading the ranked dictionaries.
    */
    public function testRankedDictionariesAreLoaded()
    {
        $rankedDictionaries = $this->sut->getRankedDictionaries();
        $this->assertNotEmpty($rankedDictionaries);
        $this->assertArrayHasKey('passwords', $rankedDictionaries);

        // Test the score starts at 1.
        foreach ($rankedDictionaries as $dictionary) {
            $this->assertContains(1, $dictionary);
        }

        unset($this->sut);
    }

    public function testDictionaryMatching()
    {
        $testDictionaries = [
            'd1' => [
                'motherboard' => 1,
                'mother' => 2,
                'board' => 3,
                'abcd' => 4,
                'cdef' => 5
            ],
            'd2' => [
                'z' => 1,
                '8' => 2,
                '99' => 3,
                '$' => 4,
                'asdf1234&*' => 5
            ]
        ];

        $matches = $this->sut->dictionaryMatch('motherboard', $testDictionaries);
        $patterns = ['mother', 'motherboard', 'board'];
        $this->checkMatches(
            "Matches words that contain other words",
            $matches,
            'dictionary',
            $patterns,
            [[0,5], [0,10], [6,10]],
            [
                'matched_word' => ['mother', 'motherboard', 'board'],
                'rank' => [2, 1, 3],
                'dictionary_name' => ['d1', 'd1', 'd1']
            ]
        );
    }

    private function checkMatches($prefix, $matches, $pattern_names, $patterns, $ijs, $props)
    {
        /**
         * If our pattern names is a string, we build an array of format [$pattern_names, $pattern_names] of length $length
         */
        $pattern_names = (is_string($pattern_names)) ?
            array_fill(0, sizeof($patterns), $pattern_names) :
            $pattern_names;

        $isEqualLengthArguments = (sizeof($pattern_names) == sizeof($patterns)) == sizeof($ijs);

        foreach ($props as $prop => $list) {
            // props = [key => list_of_values]
            $isEqualLengthArguments = $isEqualLengthArguments == (sizeof($list) == sizeof($patterns));
        }

        if (!$isEqualLengthArguments) {
            throw new UnequalArgumentListsException();
        }

        foreach (range(0, sizeof($patterns)-1) as $patternKey) {
            $match = $matches[$patternKey];
            $pattern_name = $pattern_names[$patternKey];
            $pattern = $patterns[$patternKey];

            [$i, $j] = $ijs[$patternKey];
            $msg = $prefix.": matches[".$patternKey."].pattern == '".$pattern_name."'";
            $this->assertEquals($pattern_name, $match['pattern'], $msg);
            $this->assertEquals([$i, $j], [$match['i'], $match['j']], $msg);
            $this->assertEquals($pattern, $match['token'], $msg);

            foreach ($props as $prop_name => $prop_list) {
                $this->assertEquals($prop_list[$patternKey], $match[$prop_name]);
            }
        }

    }
}
