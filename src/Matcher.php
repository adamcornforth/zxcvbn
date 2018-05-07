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

    public function __construct()
    {
        $this->adjacencyGraphs = json_decode(file_get_contents(__DIR__."/adjacency_graphs.json"), true);
    }

    /**
     * @return array
     */
    public function getAdjacencyGraphs(): array
    {
        return $this->adjacencyGraphs;
    }
    {
        return $this->frequencyLists;
    }
}
