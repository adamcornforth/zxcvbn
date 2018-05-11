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

    private $testDictionaries;

    protected function setUp()
    {
        $this->sut = new Matcher();

        $this->testDictionaries = [
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

    public function test_it_matches_words_that_contain_other_words()
    {
        $matches = $this->sut->dictionaryMatch('motherboard', $this->testDictionaries);
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

    public function test_it_matches_multiple_words_when_they_overlap() {
        $matches = $this->sut->dictionaryMatch('abcdef', $this->testDictionaries);
        $patterns = ['abcd', 'cdef'];
        $this->checkMatches(
            "Matches multiple words when they overlap",
            $matches,
            'dictionary',
            $patterns,
            [[0,3], [2,5]],
            [
                'matched_word' => ['abcd', 'cdef'],
                'rank' => [4, 5],
                'dictionary_name' => ['d1', 'd1']
            ]
        );
    }

    public function test_it_ignores_uppercasing() {
        $matches = $this->sut->dictionaryMatch('BoaRdZ', $this->testDictionaries);
        $patterns = ['BoaRd', 'Z'];
        $this->checkMatches(
            "Ignores uppercasing",
            $matches,
            'dictionary',
            $patterns,
            [[0,4], [5,5]],
            [
                'matched_word' => ['board', 'z'],
                'rank' => [3, 1],
                'dictionary_name' => ['d1', 'd2']
            ]
        );
    }

    public function test_it_identifies_words_surrounded_by_non_words() {
        $prefixes = ['q', '%%'];
        $suffixes = ['%', 'qq'];
        $word = 'asdf1234&*';
        $passwords = $this->generatePasswords($word, $prefixes, $suffixes);
        foreach ($passwords as list($password, $i, $j)) {
            $matches = $this->sut->dictionaryMatch($password, $this->testDictionaries);
            $this->checkMatches(
                "Identifies words surrounded by non-words",
                $matches,
                'dictionary',
                [$word],
                [[$i,$j]],
                [
                    'matched_word' => [$word],
                    'rank' => [5],
                    'dictionary_name' => ['d2']
                ]
            );
        }
    }

    public function test_it_matches_against_all_words_in_provided_dictionaries() {
        foreach ($this->testDictionaries as $name => $dictionary) {
            foreach ($dictionary as $word => $rank) {
                if ($word === 'motherboard') {
                    continue;
                }

                $matches = $this->sut->dictionaryMatch($word, $this->testDictionaries);
                $this->checkMatches(
                    "Matches against all words in provided dictionaries",
                    $matches,
                    'dictionary',
                    [$word],
                    [[0, strlen($word) - 1]],
                    [
                        'matched_word' => [$word],
                        'rank' => [$rank],
                        'dictionary_name' => [$name]
                    ]
                );
            }
        }
    }

    /**
     * Takes a pattern and a list of prefixes / suffixes
     *
     * Returns variants with the pattern embedded
     * with each possible prefix/suffix combination, including no prefix/suffix
     *
     * @param $pattern
     * @param $prefixes
     * @param $suffixes
     * @return array triplet [variant, i, j] where [i, j] is the start/end of the pattern
     */
    private function generatePasswords($pattern, $prefixes, $suffixes) {
        $result = [];
        foreach ($prefixes as $prefix) {
            foreach ($suffixes as $suffix) {
                list($i, $j) = [strlen($prefix), strlen($prefix) + strlen($pattern) - 1];
                $result[] = [
                    $prefix.$pattern.$suffix,
                    $i,
                    $j
                ];
            }
        }

        return $result;
    }

    private function checkMatches($message, $matches, $pattern_names, $patterns, $ijs, $props)
    {
        $this->assertNotEmpty($matches, $message." (empty matches array!)");
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

            $msg = $message.": matches[".$patternKey."].pattern == '".$pattern_name."'";
            $this->assertEquals($pattern_name, $match['pattern'], $msg);

            $msg = $message.": matches[".$patternKey."] should have [i, j] of [$i, $j]";
            $this->assertEquals([$i, $j], [$match['i'], $match['j']], $msg);

            $msg = $message.": matches[".$patternKey."].token == $pattern";
            $this->assertEquals($pattern, $match['token'], $msg);

            foreach ($props as $prop_name => $prop_list) {
                $this->assertEquals($prop_list[$patternKey], $match[$prop_name]);
            }
        }

    }
}
