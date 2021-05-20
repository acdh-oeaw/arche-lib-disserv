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

namespace acdhOeaw\arche\lib\disserv\tests;

use acdhOeaw\arche\lib\Repo;
use acdhOeaw\arche\lib\RepoDb;
use acdhOeaw\arche\lib\disserv\RepoResource;
use acdhOeaw\arche\lib\disserv\RepoResourceDb;
use acdhOeaw\arche\lib\disserv\dissemination\Service;
use acdhOeaw\arche\lib\disserv\dissemination\ServiceDb;

/**
 * Description of RepoResourceTest
 *
 * @author zozlak
 */
class RepoResourceTest extends \PHPUnit\Framework\TestCase {

    static private Repo $repo;
    static private RepoDb $repoDb;
    static private object $config;

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


        $res = self::$repo->getResourceById('https://id.acdh.oeaw.ac.at/10000');
        $res = new RepoResourceDb($res->getUri(), self::$repoDb);
        $ds2 = $res->getDissServices();
        $n   = 0;
        foreach ($ds2 as $i) {
            $this->assertEquals($urls[$n], $i->getRequest($res)->getUri());
            $n++;
        }
    }

    public function testGetResources(): void {
        $res = self::$repo->getResourceById('https://id.acdh.oeaw.ac.at/dissemination/cmdi2html');
        $res = new Service($res->getUri(), self::$repo);
        $res = $res->getMatchingResources(200); // but there are < 200 in the database
        $n   = 0;
        foreach ($res as $i) {
            $n++;
        }
        $this->assertLessThan(200, $n);

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

    /**
     * Tests matching on a relation while the match value is a literal
     * @return void
     */
    public function testMatchLiteralUri(): void {
        // from resource side
        $res = self::$repo->getResourceById('https://id.acdh.oeaw.ac.at/10000');
        $res = new RepoResource($res->getUri(), self::$repo);
        $ds  = $res->getDissServices();
        $this->assertArrayHasKey('application/x-cmdi+xml', $ds);

        $res = self::$repoDb->getResourceById('https://id.acdh.oeaw.ac.at/10000');
        $res = new RepoResourceDb($res->getUri(), self::$repoDb);
        $ds  = $res->getDissServices();
        $this->assertArrayHasKey('application/x-cmdi+xml', $ds);

        // from diss serv side
        $res     = self::$repo->getResourceById('https://id.acdh.oeaw.ac.at/dissemination/rawCmdi');
        $res     = new Service($res->getUri(), self::$repo);
        $matches = $res->getMatchingResources(15); // but there are only 11 in the database
        $this->assertEquals(11, iterator_count($matches));

        $res     = self::$repoDb->getResourceById('https://id.acdh.oeaw.ac.at/dissemination/rawCmdi');
        $res     = new ServiceDb($res->getUri(), self::$repoDb);
        $matches = $res->getMatchingResources(15); // but there are only 11 in the database
        $this->assertEquals(11, iterator_count($matches));
    }
    
    public function testDissServParam(): void {
        // from resource side
        $res = self::$repo->getResourceById('https://id.acdh.oeaw.ac.at/10100');
        $res = new RepoResource($res->getUri(), self::$repo);
        $ds  = $res->getDissServices();
        $this->assertStringContainsString('width=100&height=100', $ds['thumbnail']->getRequest($res)->getUri());

        $res = self::$repoDb->getResourceById('https://id.acdh.oeaw.ac.at/10100');
        $res = new RepoResourceDb($res->getUri(), self::$repoDb);
        $ds  = $res->getDissServices();
        $this->assertStringContainsString('width=100&height=100', $ds['thumbnail']->getRequest($res)->getUri());

        // from diss serv side
        $ds     = self::$repo->getResourceById('https://id.acdh.oeaw.ac.at/dissemination/thumbnail');
        $ds     = new Service($ds->getUri(), self::$repo);
        $matches = $ds->getMatchingResources(1);
        $this->assertStringContainsString('width=100&height=100', $ds->getRequest($matches->current())->getUri());

        $ds     = self::$repoDb->getResourceById('https://id.acdh.oeaw.ac.at/dissemination/thumbnail');
        $ds     = new ServiceDb($ds->getUri(), self::$repoDb);
        $matches = $ds->getMatchingResources(1); // but there are only 11 in the database
        $res = $matches->current();
        $this->assertStringContainsString('width=100&height=100', $ds->getRequest($matches->current())->getUri());
    }
}
