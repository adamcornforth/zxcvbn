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
    private $testl33tTable;
    private $l33tDictionaries;

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

        $this->testl33tTable = [
            'a' => ['4', '@'],
            'c' => ['(', '{', '[', '<'],
            'g' => ['6', '9'],
            'o' => ['0']
        ];

        $this->l33tDictionaries = [
            'words' => [
                'aac' => 1,
                'password' => 3,
                'paassword' => 4,
                'asdf0' => 5
            ],
            'words2' => [
                'cgo' => 1
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

    public function test_it_matches_the_default_dictionaries() {
        $matches = $this->sut->dictionaryMatch('wow');
        $patterns = ['wow'];
        $ijs = [[0,2]];
        $this->checkMatches(
            "Test it matches the default dictionaries",
            $matches,
            'dictionary',
            $patterns,
            $ijs,
            [
                'matched_word' => $patterns,
                'rank' => [322],
                'dictionary_name' => ['us_tv_and_film']
            ]
        );
    }

    public function test_it_matches_with_provided_user_input_dictionary() {
        $this->sut->setUserInputDictionary(['foo', 'bar']);
        $matches = $this->sut->dictionaryMatch('foobar');
        $matches = array_values(array_filter($matches, function ($match) {
            return $match['dictionary_name'] === 'user_inputs';
        }));

        $this->checkMatches(
            "Test it matches with the provided user input dictionary",
            $matches,
            'dictionary',
            ['foo', 'bar'],
            [[0, 2], [3, 5]],
            [
                'matched_word' => ['foo', 'bar'],
                'rank' => [1, 2]
            ]
        );
    }

    public function test_reverse_dictionary_matching() {
        $this->testDictionaries = [
            'd1' => [
                123 => 1,
                321 => 2,
                456 => 3,
                654 => 4
            ]
        ];
        $password = '0123456789';
        $matches = $this->sut->reverseDictionaryMatch($password, $this->testDictionaries);
        $this->checkMatches(
            "Test it matches against reversed words",
            $matches,
            'dictionary',
            ['123', '456'],
            [[1, 3], [4, 6]],
            [
                'matched_word' => ['321', '654'],
                'reversed' => [true, true],
                'dictionary_name' => ['d1', 'd1'],
                'rank' => [2, 4]
            ]
        );
    }

    public function test_it_reduces_l33t_table_to_password_only_substitutions() {
        foreach ([
            '' => [],
            'abcdefgo123578!#$&*)]}>' => [],
            'a' => [],
            '4' => ['a' => ['4']],
            '4@' => ['a' => ['4','@']],
            '4({60' => ['a' => ['4'], 'c' => ['(','{'], 'g' => ['6'], 'o' => ['0']],
         ] as $pw => $expected) {
            $subtable = $this->sut->getRelevantL33tSubtable($pw, $this->testl33tTable);
            $this->assertEquals($expected, $subtable, json_encode($subtable));
        }
    }

    public function test_it_enumerates_l33t_substitutions_for_password() {
        foreach ([
            [ [],                        [[]] ],
            [ ['a' => ['@']],                [['@' => 'a']] ],
            [ ['a' => ['@','4']],            [['@' => 'a'], ['4' => 'a']] ],
            [ ['a' => ['@','4'], 'c' => ['(']],  [['@' => 'a', '(' => 'c' ], ['4' => 'a', '(' => 'c']] ]
         ] as list($table, $subs)) {
            $substitutions = $this->sut->getL33tSubstitutions($table);
            $this->assertEquals(
                $subs,
                $substitutions,
                "Actual: ".json_encode($substitutions)."\nExpected: ".json_encode($subs)
            );
        }
    }

    public function test_l33t_match() {
        $this->assertEquals([], $this->sut->l33tMatch('', $this->l33tDictionaries, $this->testl33tTable));

        $this->assertEquals([], $this->sut->l33tMatch('password', $this->l33tDictionaries, $this->testl33tTable));

        foreach ([
            [ 'p4ssword',    'p4ssword', 'password', 'words',  3, [0,7],  ['4' => 'a'] ],
            [ 'p@ssw0rd',    'p@ssw0rd', 'password', 'words',  3, [0,7],  ['@' => 'a', '0' => 'o'] ],
            [ 'aSdfO{G0asDfO', '{G0',    'cgo',      'words2', 1, [5, 7], ['{' => 'c', '0' => 'o'] ]
            ] as list ($password, $pattern, $word, $dictionary_name, $rank, $ij, $sub)) {
            $matches = $this->sut->l33tMatch($password, $this->l33tDictionaries, $this->testl33tTable);

            $this->checkMatches(
                'Matches against common l33t substitutions',
                $matches,
                'dictionary',
                [$pattern],
                [$ij],
                [
                    'l33t' => [true],
                    'sub' => [$sub],
                    'matched_word' => [$word],
                    'rank' => [$rank],
                    'dictionary_name' => [$dictionary_name]
                ]
            );
        }
    }

    public function test_l33t_match_overlapping_patterns() {
        $matches = $this->sut->l33tMatch('@a(go{G0', $this->l33tDictionaries, $this->testl33tTable);

        $this->checkMatches(
            'Matches against (overlapping) l33t substitutions',
            $matches,
            'dictionary',
            ['@a(', '(go', '{G0'],
            [[0,2], [2,4], [5,7]],
            [
                'l33t' => [true, true, true],
                'sub' => [['@' => 'a', '(' => 'c'], ['(' => 'c'], ['{' => 'c', '0' => 'o']],
                'matched_word' => ['aac', 'cgo', 'cgo'],
                'rank' => [1, 1, 1],
                'dictionary_name' => ['words', 'words2', 'words2']
            ]
        );
    }

    public function test_l33t_match_doesnt_match_multiple_subsitutions_one_letter() {
        $this->assertEmpty(
            $this->sut->l33tMatch('p4@ssword', $this->l33tDictionaries, $this->testl33tTable),
            "Test l33t match doesn't match multiple substitutions (one letter)"
        );
    }

    public function test_spatial_matching() {
        foreach (['', '/', 'qw', '*/'] as $password) {
            $this->assertEquals([], $this->sut->spatialMatch($password));
        }

        $adjacencyGraphs = $this->sut->getAdjacencyGraphs();
        $graphs = ['qwerty' => $adjacencyGraphs['qwerty']];
        $pattern = '6tfGHJ';

        $matches = $this->sut->spatialMatch("rz!$pattern%z", $graphs);

        $this->checkMatches(
            "Matches against spatial patterns surrounded by non-spatial patterns",
            $matches,
            'spatial',
            [$pattern],
            [[3, 3 + strlen($pattern) - 1]],
            [
                'graph' => ['qwerty'],
                'turns' => [2],
                'shifted_count' => [3]
            ]
        );

        foreach ([
            [ '12345',        'qwerty',     1, 0 ],
            [ '@WSX',         'qwerty',     1, 4 ],
            [ '6tfGHJ',       'qwerty',     2, 3 ],
            // [ 'hGFd',         'qwerty',     1, 2 ],
            [ '/;p09876yhn',  'qwerty',     3, 0 ],
            [ 'Xdr%',         'qwerty',     1, 2 ],
            [ '159-',         'keypad',     1, 0 ],
            [ '*84',          'keypad',     1, 0 ],
            [ '/8520',        'keypad',     1, 0 ],
            [ '369',          'keypad',     1, 0 ],
            [ '/963.',        'mac_keypad', 1, 0 ],
            [ '*-632.0214',   'mac_keypad', 9, 0 ],
            [ 'aoEP%yIxkjq:', 'dvorak',     4, 5 ],
            [ ';qoaOQ:Aoq;a', 'dvorak',    11, 4 ]
        ] as list($pattern, $keyboard, $turns, $shifts)) {
            $_graphs = [];
            $_graphs[$keyboard] = $adjacencyGraphs[$keyboard];
            $matches = $this->sut->spatialMatch($pattern, $_graphs);
            $this->checkMatches(
                "Matches ".$pattern." as a ".$keyboard." pattern",
                $matches,
                'spatial',
                [$pattern],
                [[0, strlen($pattern) -1]],
                [
                    'graph' => [$keyboard],
                    'turns' => [$turns],
                    'shifted_count' => [$shifts]
                ]
            );
        }
    }

    public function test_sequence_matching()
    {
        foreach (['', 'a', '1'] as $password) {
            $this->assertEquals(
                [],
                $this->sut->sequenceMatch($password),

            );
        }

        $matches = $this->sut->sequenceMatch('abcbabc');
        $this->checkMatches(
            'Matches overlapping patterns',
            $matches,
            'sequence',
            ['abc', 'cba', 'abc'],
            [[0, 2], [2, 4], [4, 6]],
            [
                'ascending' => [true, false, true]
            ]
        );
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
                $this->assertEquals(
                    $prop_list[$patternKey],
                    $match[$prop_name],
                    $pattern."\n".json_encode($prop_list[$patternKey])."\n".json_encode($match[$prop_name])
                );
            }
        }
    }
}
