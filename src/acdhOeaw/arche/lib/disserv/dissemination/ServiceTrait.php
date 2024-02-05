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

namespace acdhOeaw\arche\lib\disserv\dissemination;

use BadMethodCallException;
use Generator;
use RuntimeException;
use GuzzleHttp\Psr7\Request;
use quickRdf\DataFactory as DF;
use quickRdf\DatasetNode;
use termTemplates\PredicateTemplate as PT;
use acdhOeaw\arche\lib\Schema;
use acdhOeaw\arche\lib\SearchTerm;
use acdhOeaw\arche\lib\SearchConfig;
use acdhOeaw\arche\lib\disserv\RepoResourceInterface;
use acdhOeaw\arche\lib\exception\RepoLibException;
use zozlak\RdfConstants as RDF;
use zozlak\queryPart\QueryPart;

/**
 * Description of ServiceTrait
 *
 * @author zozlak
 */
trait ServiceTrait {

    /**
     * Returns an SQL query matching dissemination services and resources
     * (in any direction).
     * 
     * @param int $id resource or dissemination service id
     * @param int $mode one of:
     *   - ServiceInterface::QUERY_RES for querying resources for a given 
     *     dissemination service
     *   - ServiceInterface::QUERY_DISSERV for querying dissemination services 
     *     for a given resource
     * @param Schema $schema schema object providing RDF property mappings
     * @return QueryPart
     */
    static public function getMatchQuery(int $id, int $mode, Schema $schema): QueryPart {
        $parentProp = $schema->dissService->parent ?? $schema->parent;
        switch ($mode) {
            case ServiceInterface::QUERY_DISSERV:
                $selQuery      = 'dsid';
                $directQuery   = 'SELECT target_id AS id FROM relations WHERE id = ? AND property = ?';
                $directParam   = [$id, $schema->dissService->hasService];
                // all dissemination services
                $d1Query       = 'SELECT id FROM metadata WHERE property = ? AND substring(value, 1, 1000) = ?';
                $d1Param       = [RDF::RDF_TYPE, $schema->dissService->class];
                // only a given resource
                $m5m6Query     = 'WHERE t.id = ?';
                $m5m6Param     = [$id, $id];
                // all dissemination services without any matching parameter
                $matchAllQuery = "
                    SELECT id 
                    FROM metadata m1
                    WHERE 
                        m1.property = ? 
                        AND substring(m1.value, 1, 1000) = ?
                        AND NOT EXISTS (
                            SELECT 1 
                            FROM metadata m2 JOIN relations r2 USING (id)
                            WHERE
                                r2.target_id = m1.id
                                AND r2.property = ?
                                AND m2.property = ?
                        )
                        
                ";
                $matchAllParam = [
                    RDF::RDF_TYPE, $schema->dissService->class, // first part
                    $parentProp, $schema->dissService->matchProperty, // NOT EXISTS part
                ];
                break;
            case ServiceInterface::QUERY_RES:
                $selQuery      = 'resid';
                $directQuery   = 'SELECT id FROM relations WHERE target_id = ? AND property = ?';
                $directParam   = [$id, $schema->dissService->hasService];
                // only a given dissemination service
                $d1Query       = 'SELECT ?::bigint AS id';
                $d1Param       = [$id];
                // all resources
                $m5m6Query     = '';
                $m5m6Param     = [];
                // all resources if only dissemination service has no matching rules
                $matchAllQuery = "
                    SELECT id FROM resources WHERE (
                        SELECT count(*) = 0
                        FROM metadata m2 JOIN relations r2 USING (id)
                        WHERE 
                            r2.target_id = ?
                            AND r2.property = ?
                            AND m2.property = ?
                     )
                ";
                $matchAllParam = [$id, $parentProp, $schema->dissService->matchProperty];
                break;
            default:
                throw new BadMethodCallException('Wrong $mode parameter value');
        }
        $query = "
            SELECT id FROM (
                SELECT $selQuery AS id
                FROM (
                    SELECT $selQuery, required, count(*) AS count, sum(passed::int) AS passed
                    FROM (
                        SELECT 
                            coalesce(m5.id, m6.id) AS resid,
                            dsid, 
                            dspid, 
                            coalesce(required::bool, true) AS required, 
                            coalesce(m5.id, m6.id) IS NOT NULL OR required IS NULL AS passed
                        FROM 
                            (
                                SELECT d1.id AS dsid, r.id AS dspid, m1.value AS required, m2.value AS property, m3.value AS value, m4.target_id AS target_id
                                FROM
                                    ($d1Query) d1
                                    JOIN relations r ON d1.id = r.target_id AND r.property = ?
                                    JOIN metadata m1 ON r.id = m1.id AND m1.property = ?
                                    JOIN metadata m2 ON r.id = m2.id AND m2.property = ?
                                    LEFT JOIN metadata m3 ON r.id = m3.id AND m3.property = ?
                                    LEFT JOIN relations m4 ON r.id = m4.id AND m4.property = ?
                            ) d2
                            LEFT JOIN (SELECT id, property, value FROM metadata t $m5m6Query) m5
                                ON d2.property = m5.property AND (substring(d2.value, 1, 1000) = substring(m5.value, 1, 1000) OR (d2.value IS NULL AND d2.target_id IS NULL))
                            LEFT JOIN (SELECT t.id, property, target_id, ids FROM relations t JOIN identifiers i ON t.target_id = i.id $m5m6Query) m6
                                ON d2.property = m6.property AND (d2.target_id = m6.target_id OR d2.value = m6.ids OR (d2.target_id IS NULL AND d2.value IS NULL))
                    ) d3
                    GROUP BY 1, 2
                ) d4
                GROUP BY 1
                HAVING count(*) = sum((CASE required WHEN true THEN count = passed ELSE passed > 0 END)::int)
              UNION
                $directQuery
              UNION
                $matchAllQuery
            ) t ORDER BY id
        ";
        $param = array_merge(
            $d1Param,
            [
                $parentProp, // r
                $schema->dissService->matchRequired, // m1
                $schema->dissService->matchProperty, // m2
                $schema->dissService->matchValue, // m3
                $schema->dissService->matchValue, // m4
            ],
            $m5m6Param,
            $directParam,
            $matchAllParam,
        );
        return new QueryPart($query, $param);
    }

    /**
     * Parameters list
     * @var array<mixed>
     */
    private array $param;
    private bool $loadParamFromMeta = false;

    /**
     * Returns all return formats provided by the dissemination service.
     * 
     * Technically return formats are nothing more then strings. There is no
     * requirement forcing them to be mime types, etc. 
     * @return array<Format>
     */
    public function getFormats(): array {
        $meta    = $this->getMetadata();
        $formats = [];
        foreach ($meta->listObjects(new PT($this->getRepo()->getSchema()->dissService->returnFormat)) as $i) {
            $formats[] = new Format((string) $i);
        }
        return $formats;
    }

    /**
     * Returns PSR-7 HTTP request disseminating a given resource.
     * @param RepoResourceInterface $res repository resource to be disseminated
     * @return Request
     * @throws RuntimeException
     */
    public function getRequest(RepoResourceInterface $res): Request {
        $uri = $this->getLocation();

        $param  = $this->getUrlParameters();
        $values = $this->getParameterValues($param, $res);
        foreach ($values as $k => $v) {
            $uri = str_replace($k, $v, $uri);
        }

        return new Request('get', $uri);
    }

    /**
     * Tells the service it can load information on service parameters from its metadata.
     * 
     * Information will be loaded in a lazy way (when it's needed).
     * 
     * @return void
     */
    public function loadParametersFromMetadata(): void {
        $this->loadParamFromMeta = true;
    }

    /**
     * Gets disseminations service's URL (before parameters subsitution)
     * @return string
     */
    public function getLocation(): string {
        $meta = $this->getMetadata();
        return (string) $meta->getObject(new PT($this->getRepo()->getSchema()->dissService->location));
    }

    /**
     * Should the dissemination service request be reverse-proxied?
     * 
     * If it's not set in the metadata, false is assumed.
     * @return bool
     */
    public function getRevProxy(): bool {
        $meta  = $this->getMetadata();
        $value = $meta->getObject($this->getRepo()->getSchema()->dissService->revProxy)?->getValue() ?? false;
        return (bool) $value;
    }

    /**
     * Returns repository resources which can be disseminated by a given service
     * 
     * @param int $limit
     * @param int $offset
     * @return Generator
     */
    public function getMatchingResources(int $limit = null, int $offset = null): Generator {
        $id    = $this->getUri();
        $id    = (int) substr($id, strrpos($id, '/') + 1);
        $query = self::getMatchQuery($id, ServiceInterface::QUERY_RES, $this->getRepo()->getSchema());

        if ($limit > 0) {
            $query->query .= " LIMIT $limit";
        }
        if ($offset > 0) {
            $query->query .= " OFFSET $offset";
        }

        $config               = new SearchConfig();
        $config->metadataMode = RepoResourceInterface::META_RESOURCE;
        $config->class        = get_parent_class();

        $tmp = $this->getRepo()->getResourcesBySqlQuery($query->query, $query->param, $config);
        foreach ($tmp as $i) {
            yield $i;
        }
    }

    /**
     * Returns list of all parameters of a given dissemination service
     * @return array<string>
     */
    private function getUrlParameters(): array {
        $uri   = $this->getLocation();
        $param = [];
        preg_match_all('#{[^}]+}#', $uri, $param);
        return $param[0];
    }

    /**
     * Evaluates parameter values for a given resource.
     * @param array<string> $param list of parameters
     * @param RepoResourceInterface $res repository resource to be disseminated
     * @return array<string, mixed> associative array with parameter values
     * @throws RuntimeException
     */
    private function getParameterValues(array $param, RepoResourceInterface $res): array {
        $this->loadParameters();

        $values = [];
        foreach ($param as $i) {
            $ii    = explode('|', substr($i, 1, -1));
            $name  = array_shift($ii);
            $match = preg_match('/^([^@&]+)(?:([@&])(.*))?$/', $name, $matches);
            if ($match === false) {
                throw new RepoLibException("Wrong parameter definition: $name");
            }
            $name   = $matches[1];
            $force  = ($matches[2] ?? '') === '&';
            $prefix = $matches[3] ?? null;

            if ($name === 'RES_URI' || $name === 'RES_URL') {
                $value = Parameter::applyTransformations($res->getUri(), $ii);
            } else if ($name === 'RES_ID') {
                $id    = substr($res->getUri(), strlen($res->getRepo()->getBaseUrl()));
                $value = Parameter::applyTransformations($id, $ii);
            } else if ($name === 'ID') {
                $id    = $this->getResNmspId($res, $prefix, $force);
                $value = Parameter::applyTransformations($id, $ii);
            } else if (isset($this->param[$name])) {
                $value = $this->param[$name]->getValue($res, $ii, $prefix, $force);
            } else {
                throw new RepoLibException('Unknown parameter ' . $name . ' (' . $this->getUri() . ')');
            }
            $values[$i] = $value;
        }

        return $values;
    }

    private function loadParameters(): void {
        if (isset($this->param)) {
            return;
        }
        $paramClass = $this->getParameterClass();
        $schema     = $this->getRepo()->getSchema();
        $parentProp = $schema->dissService->parent ?? $schema->parent;
        $type       = $schema->dissService->parameterClass;
        if ($this->loadParamFromMeta) {
            $typeTmpl    = new PT(DF::namedNode(RDF::RDF_TYPE), $type);
            $graph       = $this->getGraph()->getDataset();
            $params      = $graph->listSubjects(new PT($parentProp, $this->getUri()));
            $this->param = [];
            foreach ($params as $i) {
                if ($graph->any($typeTmpl->withSubject($i))) {
                    $param                          = new $paramClass((string) $i, $this->getRepo());
                    $ds                             = new DatasetNode($i);
                    $param->setMetadata($ds->withDataset($graph));
                    $this->param[$param->getName()] = $param;
                }
            }
        } else {
            $terms             = [
                new SearchTerm(RDF::RDF_TYPE, (string) $type),
                new SearchTerm((string) $parentProp, (string) $this->getUri()),
            ];
            $cfg               = new SearchConfig();
            $cfg->metadataMode = RepoResourceInterface::META_RESOURCE;
            $cfg->class        = $paramClass;
            $params            = $this->getRepo()->getResourcesBySearchTerms($terms, $cfg);
            $this->param       = [];
            foreach ($params as $i) {
                $this->param[$i->getName()] = $i;
            }
        }
    }

    /**
     * Fetches a resource id in a given namespace.
     * 
     * If namespaces overlap, tries to to avoid id in the overlapping ones.
     * 
     * @param RepoResourceInterface $res
     * @param string $namespace
     * @param bool $force
     * @return string
     * @throws RepoLibException
     */
    private function getResNmspId(RepoResourceInterface $res, string $namespace,
                                  bool $force): string {
        $namespaces = $res->getRepo()->getSchema()->namespaces;
        $nmsp  = $namespaces->$namespace ?? throw new RepoLibException("namespace '$namespace' is not defined in the config");
        $ids   = $res->getIds();
        $match = null;
        foreach ($ids as $i) {
            if (str_starts_with($i, $nmsp)) {
                $otherNmsp = false;
                foreach ($namespaces as $j) {
                    if ($nmsp !== $j && str_starts_with($i, $j)) {
                        $otherNmsp = true;
                        break;
                    }
                }
                if (!$otherNmsp) {
                    return $i;
                } else {
                    $match = $i;
                }
            }
        }
        if ($match !== null) {
            return $match;
        }
        if ($force || count($ids) === 0) {
            throw new RepoLibException('no ID in namespace ' . $namespace);
        } else {
            return $ids[0];
        }
    }

    /**
     * Get the parameters 
     * @return array<mixed>
     */
    public function getParameters(): array {
        $this->loadParameters();
        return $this->param;
    }

    private abstract function getParameterClass(): string;
}
