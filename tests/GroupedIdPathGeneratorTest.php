<?php

use CatLab\Assets\Laravel\PathGenerators\GroupedIdPathGenerator;
use PHPUnit\Framework\TestCase;

/**
 * Class GroupedIdPathGeneratorTest
 * @package Tests
 */
class GroupedIdPathGeneratorTest extends TestCase
{
    /**
     *
     */
    public function testPathGenerator()
    {
        $generator = new GroupedIdPathGenerator();

        $this->assertEquals('000/000/001', $generator->getFolderFromId(1));
        $this->assertEquals('000/000/011', $generator->getFolderFromId(11));
        $this->assertEquals('000/000/111', $generator->getFolderFromId(111));
        $this->assertEquals('000/001/111', $generator->getFolderFromId(1111));
        $this->assertEquals('000/000/999', $generator->getFolderFromId(999));
        $this->assertEquals('000/001/000', $generator->getFolderFromId(1000));
        $this->assertEquals('000/100/100', $generator->getFolderFromId(100100));
        $this->assertEquals('100/100/100', $generator->getFolderFromId(100100100));
        $this->assertEquals('100100/100/100', $generator->getFolderFromId(100100100100));
        $this->assertEquals('999100/100/100', $generator->getFolderFromId(999100100100));
        $this->assertEquals('1000100/100/100', $generator->getFolderFromId(1000100100100));
        $this->assertEquals('19000100/100/100', $generator->getFolderFromId(19000100100100));
        $this->assertEquals('000/010/883', $generator->getFolderFromId(10883));
    }

}