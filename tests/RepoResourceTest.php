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

namespace acdhOeaw\acdhRepoAcdh;

use acdhOeaw\acdhRepoLib\Repo;

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
    static private $config;

    static public function setUpBeforeClass(): void {
        $cfgFile      = __DIR__ . '/config.yaml';
        self::$config = json_decode(json_encode(yaml_parse_file($cfgFile)));
        self::$repo   = Repo::factory($cfgFile);
    }

    static public function tearDownAfterClass(): void {
        
    }
    
    public function testGetDissServ(): void {
        $res = self::$repo->getResourceById('https://id.acdh.oeaw.ac.at/Troesmis/troesmis-title-image.png');
        $res = new RepoResource($res->getUri(), self::$repo);
        $ds = $res->getDissServices();
        $this->assertTrue(is_array($ds));
        foreach ($ds as $i){
            /* @var $i \acdhOeaw\acdhRepoAcdh\dissemination\Service */
            echo "\n---------------------------------------\n";
            echo $i->getRequest($res)->getUri() . "\n";
            break;
        }
    }
}
