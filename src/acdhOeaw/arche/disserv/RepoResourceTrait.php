<?php

/*
 * The MIT License
 *
 * Copyright 2020 Austrian Centre for Digital Humanities.
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

use acdhOeaw\arche\disserv\dissemination\Service;
use acdhOeaw\acdhRepoLib\SearchConfig;
use zozlak\RdfConstants as RDF;

/**
 * Description of RepoResourceTrait
 *
 * @author zozlak
 */
trait RepoResourceTrait {

    /**
     * Returns list of dissemination services available for a resource.
     * @return array
     */
    public function getDissServices(): array {
        $schema = $this->getRepo()->getSchema();
        $resId  = (int) substr($this->getUri(), strlen($this->getRepo()->getBaseUrl()));
        $query  = Service::getMatchQuery($resId, dissemination\ServiceInterface::QUERY_DISSERV, $schema);

        $config                         = new SearchConfig();
        $config->metadataMode           = self::META_NEIGHBORS;
        $config->metadataParentProperty = $schema->parent;
        $config->class                  = Service::class;

        $tmp = $this->getRepo()->getResourcesBySqlQuery($query->query, $query->param, $config);

        // gather all services
        $services = $formats  = $mime     = [];
        foreach ($tmp as $i) {
            /* @var $i \acdhOeaw\arche\disserv\dissemination\Service */
            $i->loadParametersFromMetadata();
            foreach ($i->getFormats() as $f) {
                /* @var $f \acdhOeaw\arche\disserv\dissemination\Format */
                $services[] = $i;
                $formats[]  = $f->weight;
                $mime[]     = $f->format;
            }
        }

        // deal with possible conflicts for same mime types
        arsort($formats);
        $ret = [];
        foreach (array_keys($formats) as $k) {
            if (!isset($ret[$mime[$k]])) {
                $ret[$mime[$k]] = $services[$k];
            }
        }
        return $ret;
    }

}
