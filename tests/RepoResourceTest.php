<?php

/*
 * The MIT License
 *
 * Copyright 2019 Austrian Centre for Digital Humanities.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace acdhOeaw\arche\disserv;

use acdhOeaw\acdhRepoLib\Repo;
use acdhOeaw\acdhRepoLib\RepoDb;
use acdhOeaw\arche\disserv\dissemination\Service;
use acdhOeaw\arche\disserv\dissemination\ServiceDb;

/**
 * Description of RepoResourceTest
 *
 * @author zozlak
 */
class RepoResourceTest extends \PHPUnit\Framework\TestCase {

    /**
     *
     * @var \acdhOeaw\acdhRepoLib\Repo
     */
    static private $repo;
    static private $repoDb;
    static private $config;

    static public function setUpBeforeClass(): void {
        $cfgFile      = __DIR__ . '/config.yaml';
        self::$config = json_decode(json_encode(yaml_parse_file($cfgFile)));
        self::$repo   = Repo::factory($cfgFile);
        self::$repoDb = RepoDb::factory($cfgFile);
    }

    static public function tearDownAfterClass(): void {
        
    }

    public function testGetDissServ(): void {
        $res  = self::$repo->getResourceById('https://id.acdh.oeaw.ac.at/10000');
        $res  = new RepoResource($res->getUri(), self::$repo);
        $ds1  = $res->getDissServices();
        $urls = [];
        foreach ($ds1 as $i) {
            /* @var $i \acdhOeaw\acdhRepoDisserv\dissemination\Service */
            $urls[] = $i->getRequest($res)->getUri();
        }


        $res  = self::$repo->getResourceById('https://id.acdh.oeaw.ac.at/10000');
        $res = new RepoResourceDb($res->getUri(), self::$repoDb);
        $ds2 = $res->getDissServices();
        $n   = 0;
        foreach ($ds2 as $i) {
            $this->assertEquals($urls[$n], $i->getRequest($res)->getUri());
            $n++;
        }
    }

    public function testGetResources(): void {
        $res = self::$repo->getResourceById('https://id.acdh.oeaw.ac.at/dissemination/xmlinsights');
        $res = new Service($res->getUri(), self::$repo);
        $res = $res->getMatchingResources(30); // but there are only 21 in the database
        $n   = 0;
        foreach ($res as $i) {
            $n++;
        }
        $this->assertEquals(21, $n);

        $res     = self::$repoDb->getResourceById('https://id.acdh.oeaw.ac.at/dissemination/gui');
        $res     = new ServiceDb($res->getUri(), self::$repoDb);
        $matches = $res->getMatchingResources(10);
        $n       = 0;
        $urls    = [];
        foreach ($matches as $i) {
            $urls[$n] = $i->getUri();
            $n++;
        }
        $matches = $res->getMatchingResources(10, 0);
        $n       = 0;
        foreach ($matches as $i) {
            $this->assertEquals($urls[$n], $i->getUri());
            $n++;
        }
        $this->assertEquals(10, $n);
        $matches = $res->getMatchingResources(10, 10);
        $n       = 0;
        foreach ($matches as $i) {
            $this->assertNotContains($i->getUri(), $urls);
            $n++;
        }
        $this->assertEquals(10, $n);
    }

}
