<?php namespace Cornforth\Zxcvbn;

/**
*
*  @author Adam Cornforth
*/
class Matcher
{
    const SHIFTED_RX = '/[~!@#$%^&*()_+QWERTYUIOP{}|ASDFGHJKL:"ZXCVBNM<>?]/';
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

    /**
     * Makes a subtable of the leettable that only includes the relevant substitutions in the password
     *
     * @param $password
     * @param null $_leettable
     * @return array
     */
    public function getRelevantL33tSubtable($password, $_leettable = null)
    {
        $_leettable = $_leettable ?? $this->leettable;

        $table = [];

        if (!empty($password)) {
            // Iterate the password letters
            foreach (str_split($password) as $pwLetter) {
                // Iterate the leettable substitutions
                foreach ($_leettable as $letter => $substitutions) {
                    $found = array_search($pwLetter, $substitutions);
                    if ($found !== false) {
                        // If we find a substitution, add it to the new table
                        $table[$letter][] = $substitutions[$found];
                    }
                }
            }
        }

        return $table;
    }

    /**
     * List the possible l33t substitutions in a l33t table
     *
     * @param $table
     * @return array
     */
    public function getL33tSubstitutions($table)
    {
        $substitutions = $this->enumerateL33tReplacements(array_keys($table), $table);

        $subDictionaries = [];
        foreach ($substitutions as $substitution) {
            $sub_dict = [];
            foreach ($substitution as $l33tCharacter => $character) {
                $sub_dict[$l33tCharacter] = $character;
            }
            $subDictionaries[] = $sub_dict;
        }

        return $subDictionaries;
    }

    private function deduplicate($substitutions)
    {
        $deduplicated = [];
        $members = [];

        foreach ($substitutions as $l33tCharacter => $substitution) {
            $assoc = $substitution;
            sort($assoc);

            $label = '';
            foreach ($assoc as $key => $value) {
                $label = $label.$l33tCharacter.','.$value.'-';
            }
            $label = rtrim($label, '-');

            if (!array_key_exists($label, $members)) {
                $members[$label] = true;
                $deduplicated[] = $substitution;
            }
        }

        return $deduplicated;
    }

    private function enumerateL33tReplacements($keys, $table, $substitutions = [[]])
    {
        if (empty($keys)) {
            // break clause
            return $substitutions;
        }

        $firstKey = $keys[0];
        $restKeys = array_slice($keys, 1);

        $nextSubstitutions = [];

        foreach ($table[$firstKey] as $l33tCharacter) {
            foreach ($substitutions as $substitution) {
                $duplicateL33tIndex = -1;

                foreach ($substitution as $i => $value) {
                    if ($substitution[$i][0] == $l33tCharacter) {
                        $duplicateL33tIndex = $i;
                        break;
                    }
                }

                if ($duplicateL33tIndex == -1) {
                    $subExtension = $substitution + [$l33tCharacter => $firstKey];
                    $nextSubstitutions[] = $subExtension;
                } else {
                    $subAlternative = array_slice($substitution, $duplicateL33tIndex, 1, true);
                    $subAlternative[] = [$l33tCharacter => $firstKey];

                    $nextSubstitutions[] = $substitution;
                    $nextSubstitutions[] = $subAlternative;
                }
            }
        }

        return $this->enumerateL33tReplacements(
            $restKeys,
            $table,
            $this->deduplicate($nextSubstitutions)
        );
    }

    public function l33tMatch($password, $_ranked_dictionaries = null, $_leettable = null)
    {
        $_ranked_dictionaries = $_ranked_dictionaries ?? $this->rankedDictionaries;
        $_leettable = $_leettable ?? $this->leettable;

        if (empty($password)) {
            return [];
        }

        $matches = [];
        $length = strlen($password);
        $passwordLower = strtolower($password);
        foreach ($_ranked_dictionaries as $dictionary_name => $ranked_dictionary) {
            foreach (range(0, $length) as $i) {
                foreach (range($i, $length) as $j) {
                    // If the sliced string is in the dictionary, we need to add a match.
                    $subStrLength = $j - $i;
                    $sliceSubstr = substr($passwordLower, $i, $subStrLength);

                    $table = $this->getRelevantL33tSubtable($sliceSubstr, $_leettable);
                    $sub = $this->getL33tSubstitutions($table);

                    foreach ($sub as $substitutions) {
                        // We overwrite the string here so we aren't replacing more than one sub at
                        // a time e.g. p4@ssword --> paassword
                        $substr = $sliceSubstr;
                        foreach ($substitutions as $l33tCharacter => $replacement) {
                            $substr = str_replace($l33tCharacter, $replacement, $substr);
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
                                    'sub' => $substitutions,
                                    'token' => substr($password, $i, $subStrLength),
                                    'matched_word' => $word,
                                    'rank' => $ranked_dictionary[$word],
                                    'dictionary_name' => $dictionary_name,
                                    'reversed' => false,
                                    'l33t' => true
                                ];
                            }
                        }
                    }
                }
            }
        }

        return $matches;
    }

    public function spatialMatch($password, $_graphs = null)
    {
        $_graphs = $_graphs ?? $this->adjacencyGraphs;

        $matches = [];

        foreach ($_graphs as $graphName => $graph) {
            $passwordArray = str_split($password);
            if ($match = $this->spatialMatchHelper($passwordArray, $graph, $graphName)) {
                $matches = array_merge($matches, $match);
            }
        }

        sort($matches);

        return $matches;
    }

    private function spatialMatchHelper($password, $graph, $graphName)
    {
        $matches = [];
        $i = 0;

        while ($i < sizeof($password) - 1) {
            $j = $i + 1;
            $last_direction = null;
            $turns = 0;
            if (in_array($graphName, ['qwerty', 'dvorak']) && preg_match(self::SHIFTED_RX, $password[$i])) {
                // initial character is shifted
                $shifted_count = 1;
            } else {
                $shifted_count = 0;
            }

            // Loop forever until we break out
            while(true) {
                $prevChar = $password[$j-1];
                $found = false;
                $foundDirection = -1;
                $curDirection = -1;
                $adjacents = $graph[$prevChar] ?? [];

                # consider growing pattern by one character if j hasn't gone over the edge.
                if ($j < sizeof($password)) {
                    $curChar = $password[$j];
                    // For every character around the current character on the keyboard layout...
                    foreach ($adjacents as $adj) {
                        $adj = str_split($adj);
                        $curDirection += 1;
                        if ($adj && array_search($curChar, $adj) !== false) {
                            $found = true;
                            $foundDirection = $curDirection;
                            if (array_search($curChar, $adj) == 1) {
                                // index 1 in the adjacency means the key is shifted,
                                // 0 means unshifted: A vs a, % vs 5, etc.
                                // for example, 'q' is adjacent to the entry '2@'.
                                // @ is shifted w/ index 1, 2 is unshifted.
                                $shifted_count += 1;
                            }
                            if ($last_direction != $foundDirection) {
                                // adding a turn is correct even in the initial case when last_direction is null:
                                // every spatial pattern starts with a turn.
                                $turns += 1;
                                $last_direction = $foundDirection;
                            }
                            break;
                        }
                    }
                }

                # if the current pattern continued, extend j and try to grow again
                if ($found) {
                    $j += 1;
                } else {
                    # otherwise push the pattern discovered so far, if any...
                    if ($j - $i > 2) {
                        $matches[] = [
                            'pattern' => 'spatial',
                            'i' => $i,
                            'j' => $j-1,
                            'token' => implode("", array_slice($password, $i, $j - $i)),
                            'graph' => $graphName,
                            'turns' => $turns,
                            'shifted_count' => $shifted_count
                        ];
                    }
                    $i = $j;
                    break;
                }
            }
        }

        return $matches;
    }

    public function sequenceMatch($password)
    {
        return [];
    }
}
