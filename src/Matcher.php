<?php namespace Cornforth\Zxcvbn;

/**
*
*  @author Adam Cornforth
*/
class Matcher
{
    /**
     * Keyboard Adjacency Graphs
     *
     * Lifted from https://github.com/dropbox/zxcvbn/blob/master/src/adjacency_graphs.coffee
     *
     * @var array
     */
    private $adjacencyGraphs;

    /**
     * Array of dictionaries, ranked by how often they occur
     */
    private $rankedDictionaries;

    /**
     * Table of character => l33t symbol substitutions
     * @var array
     */
    private $leettable;

    public function __construct()
    {
        $this->buildRankedDictionaries(json_decode(file_get_contents(__DIR__."/frequency_lists.json"), true));
        $this->adjacencyGraphs = json_decode(file_get_contents(__DIR__."/adjacency_graphs.json"), true);
        $this->leettable = [
            'a' => ['4', '@'],
            'b' => ['8'],
            'c' => ['(', '{', '[', '<'],
            'e' => ['3'],
            'g' => ['6', '9'],
            'i' => ['1', '!', '|'],
            'l' => ['1', '|', '7'],
            'o' => ['0'],
            's' => ['$', '5'],
            't' => ['+', '7'],
            'x' => ['%'],
            'z' => ['2']
        ];
    }

    public function dictionaryMatch($password, $_ranked_dictionaries = null)
    {
        $_ranked_dictionaries = $_ranked_dictionaries ?? $this->rankedDictionaries;

        $matches = [];
        $length = strlen($password);
        $passwordLower = strtolower($password);
        foreach ($_ranked_dictionaries as $dictionary_name => $ranked_dictionary) {
            foreach (range(0, $length) as $i) {
                foreach (range($i, $length) as $j) {
                    // If the sliced string is in the dictionary, we need to add a match.
                    $subStrLength = $j - $i;
                    $substr = substr($passwordLower, $i, $subStrLength);
                    if (
                        array_key_exists(
                            $substr,
                            $ranked_dictionary
                        )
                    ) {
                        $word = $substr;
                        $matches[] = [
                            'pattern' => 'dictionary',
                            'i' => $i,
                            'j' => $j - 1,
                            'token' => substr($password, $i, $subStrLength),
                            'matched_word' => $word,
                            'rank' => $ranked_dictionary[$word],
                            'dictionary_name' => $dictionary_name,
                            'reversed' => false,
                            'l33t' => false
                        ];
                    }
                }
            }
        }

        return $matches;
    }

    public function reverseDictionaryMatch($password, $_ranked_dictionaries = null)
    {
        $matches = $this->dictionaryMatch(strrev($password), $_ranked_dictionaries);

        foreach ($matches as $key => $match) {
            $matches[$key]['reversed'] = true;

            // Reverse token back
            $matches[$key]['token'] = strrev($match['token']);

            // Map coordinates back to original string
            $matches[$key]['i'] = strlen($password) - 1 - $match['j'];
            $matches[$key]['j'] = strlen($password) - 1 - $match['i'];
        }

        return array_reverse($matches);
    }

    public function setUserInputDictionary($ordered_list)
    {
        $this->rankedDictionaries['user_inputs'] = $this->buildRankedDictionary($ordered_list);
    }

    /**
     * @return array
     */
    public function getAdjacencyGraphs(): array
    {
        return $this->adjacencyGraphs;
    }

    private function buildRankedDictionaries($frequencyLists)
    {
        foreach ($frequencyLists as $name => $list) {
            $this->rankedDictionaries[$name] = $this->buildRankedDictionary($list);
        }
    }

    public function getRankedDictionaries()
    {
        return $this->rankedDictionaries;
    }

    /**
     * @return mixed
     */
    public function getLeettable()
    {
        return $this->leettable;
    }

    /**
     * @param $list
     * @return array
     * @internal param $name
     */
    private function buildRankedDictionary($list): array
    {
        $result = [];

        $i = 1; // Rank starts at 1, not 0.
        foreach ($list as $word) {
            $result[$word] = $i++;
        }

        return $result;
    }
}
