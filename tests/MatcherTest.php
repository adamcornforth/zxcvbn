<?php

namespace Cornforth\Tests;

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
  * Just check if the YourClass has no syntax error 
  *
  * This is just a simple check to make sure your library has no syntax error. This helps you troubleshoot
  * any typo before you even use this library in a real project.
  *
  */
  public function testIsThereAnySyntaxError(){
	$var = new Matcher();
	$this->assertTrue(is_object($var));
	unset($var);
  }
  
  /**
   * Test that we are loading the frequency lists.
   */
  public function testFrequencyListsAreLoaded(){
      $var = new Matcher();
      $this->assertNotEmpty($var->getFrequencyLists());
      $this->assertArrayHasKey('passwords', $var->getFrequencyLists());
      unset($var);
  }
}
