<?php namespace Cornforth\Zxcvbn;

/**
*
*  @author Adam Cornforth
*/
class Matcher
{
    /**
     * Frequency lists to do matching on
     * @var array
     */
    private $frequencyLists;

    public function __construct()
    {
        $this->frequencyLists = json_decode(file_get_contents(__DIR__."/frequency_lists.json"), true);
    }

    /**
     * @return array
     */
    public function getFrequencyLists(): array
    {
        return $this->frequencyLists;
    }
}
