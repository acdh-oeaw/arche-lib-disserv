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
        $query                          = "
            SELECT dsid AS id
            FROM (
                SELECT dsid, required, count(*) AS count, sum(passed::int) AS passed
                FROM (
                    SELECT dsid, dspid, coalesce(required::bool, true) AS required, coalesce(m5.id, m6.id) IS NOT NULL OR required IS NULL AS passed
                    FROM 
                        (
                            SELECT d1.id AS dsid, r.id AS dspid, m1.value AS required, m2.value AS property, m3.value AS value, m4.target_id AS target_id
                            FROM
                                (SELECT id FROM metadata WHERE property = ? AND substring(value, 1, 1000) = ?) d1
                                LEFT JOIN relations r ON d1.id = r.target_id AND r.property = ?
                                LEFT JOIN metadata m1 ON r.id = m1.id AND m1.property = ?
                                LEFT JOIN metadata m2 ON r.id = m2.id AND m2.property = ?
                                LEFT JOIN metadata m3 ON r.id = m3.id AND m3.property = ?
                                LEFT JOIN relations m4 ON r.id = m4.id AND m4.property = 'https://vocabs.acdh.oeaw.ac.at/schema#matchesValue'
                        ) d2
                        LEFT JOIN (SELECT id, property, value FROM metadata WHERE id = ?) m5 USING (property, value)
                        LEFT JOIN (SELECT id, property, target_id FROM relations WHERE id = ?) m6 USING (property, target_id)
                ) d3
                GROUP BY 1, 2
            ) d4
            GROUP BY 1
            HAVING count(*) = sum((CASE required WHEN true THEN count = passed ELSE passed > 0 END)::int)
          UNION
            SELECT target_id AS id FROM relations WHERE id = ? AND property = ?
        ";
        $schema                         = $this->getRepo()->getSchema();
        $resId                          = (int) substr($this->getUri(), strlen($this->getRepo()->getBaseUrl()));
        $param                          = [
            RDF::RDF_TYPE, $schema->dissService->class, // d1
            $schema->parent, // r
            $schema->dissService->matchRequired, // m1
            $schema->dissService->matchProperty, // m2
            $schema->dissService->matchValue, // m3
            $resId, $resId, // m5, m6
            $resId, $schema->dissService->hasService, // after union
        ];
        $config                         = new SearchConfig();
        $config->metadataMode           = self::META_NEIGHBORS;
        $config->metadataParentProperty = $schema->parent;
        $config->class                  = Service::class;
        $tmp                            = $this->getRepo()->getResourcesBySqlQuery($query, $param, $config);

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
