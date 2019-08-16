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

use acdhOeaw\acdhRepoAcdh\dissemination\Service;

/**
 * Description of RepoResource
 *
 * @author zozlak
 */
class RepoResource extends \acdhOeaw\acdhRepoLib\RepoResource {

    /**
     * Returns list of dissemination services available for a resource.
     * @return array
     */
    public function getDissServices(): array {
        $query = "
            WITH ds AS (
                SELECT id, target_id AS dsid
                FROM relations r 
                WHERE
                    property = ?
                    AND EXISTS (SELECT 1 FROM metadata WHERE r.target_id = id AND substring(value, 1, 1000) = ?)
            )
            SELECT dsid AS id 
            FROM (
                SELECT dsid, required, count(*) AS count, sum(passed::int) AS sum 
                from (
                    SELECT dsid, dspid, required, bool_or(id is not null) AS passed
                    FROM
                             (SELECT dsid, id AS dspid, value AS property FROM metadata m JOIN ds USING (id) WHERE property = ?) t1
                        JOIN (SELECT dsid, id AS dspid, value             FROM metadata m JOIN ds USING (id) WHERE property = ?) t2 USING (dsid, dspid)
                        JOIN (SELECT dsid, id AS dspid, value AS required FROM metadata m JOIN ds USING (id) WHERE property = ?) t3 USING (dsid, dspid)
                        LEFT  JOIN (SELECT id, property, value FROM metadata WHERE id = ?) t4 USING (property, value)
                    GROUP BY 1, 2, 3
                ) t5
                GROUP BY 1, 2
            ) t6 
            GROUP BY 1 
            HAVING count(*) = sum((CASE required WHEN 'true' THEN count = sum ELSE sum > 0 END)::int)
          UNION
            SELECT target_id AS id FROM relations WHERE id = ? AND property = ?
        ";
        $schema = $this->getRepo()->getSchema();
        $param = [
            $schema->parent,
            $schema->dissService->class,
            $schema->dissService->matchProperty,
            $schema->dissService->matchValue,
            $schema->dissService->matchRequired,
            (int) substr($this->getUri(), strlen($this->getRepo()->getBaseUrl())),
            (int) substr($this->getUri(), strlen($this->getRepo()->getBaseUrl())),
            $schema->dissService->hasService,
        ];
        $tmp = $this->getRepo()->getResourcesBySqlQuery($query, $param, self::META_NEIGHBORS);
        $services = [];
        foreach ($tmp as $i) {
            /* @var $i \acdhOeaw\acdhRepoLib\RepoResource */
            $service = new Service($i->getUri(), $i->getRepo());
            $service->loadParametersFromGraph($service->getGraph()->getGraph());
            $services[] = $service;
        }
        return $services;
    }

}
