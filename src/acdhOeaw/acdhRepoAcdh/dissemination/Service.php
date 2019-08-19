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

namespace acdhOeaw\acdhRepoAcdh\dissemination;

use GuzzleHttp\Psr7\Request;
use EasyRdf\Graph;
use acdhOeaw\acdhRepoAcdh\RepoResource;
use acdhOeaw\acdhRepoLib\SearchTerm;
use acdhOeaw\acdhRepoLib\exception\RepoLibException;

/**
 * Represents a dissemination service.
 *
 * @author zozlak
 */
class Service extends RepoResource {

    const RDF_TYPE = 'http://www.w3.org/1999/02/22-rdf-syntax-ns#type';

    /**
     * Parameters list
     * @var array
     */
    private $param;

    /**
     *
     * @var bool
     */
    private $loadParamFromMeta = false;

    /**
     * Returns all return formats provided by the dissemination service.
     * 
     * Technically return formats are nothing more then strings. There is no
     * requirement forcing them to be mime types, etc. 
     * @return array
     */
    public function getFormats(): array {
        $meta    = $this->getMetadata();
        $formats = [];
        foreach ($meta->all($this->getRepo()->getSchema()->dissService->returnFormat) as $i) {
            $formats[] = new Format((string) $i);
        }
        return $formats;
    }

    /**
     * Returns PSR-7 HTTP request disseminating a given resource.
     * @param FedoraResource $res repository resource to be disseminated
     * @return Request
     * @throws RuntimeException
     */
    public function getRequest(RepoResource $res): Request {
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
        return $meta->getLiteral($this->getRepo()->getSchema()->dissService->location);
    }

    /**
     * Should the dissemination service request be reverse-proxied?
     * 
     * If it's not set in the metadata, false is assumed.
     * @return bool
     */
    public function getRevProxy(): bool {
        $meta  = $this->getMetadata();
        $value = $meta->getLiteral($this->getRepo()->getSchema()->dissService->revProxy)->getValue();
        return $value ?? false;
    }

    /**
     * Returns list of all parameters of a given dissemination service
     * @return array
     */
    private function getUrlParameters(): array {
        $uri   = $this->getLocation();
        $param = [];
        preg_match_all('#{[^}]+}#', $uri, $param);
        return $param[0];
    }

    /**
     * Evaluates parameter values for a given resource.
     * @param array $param list of parameters
     * @param FedoraResource $res repository resource to be disseminated
     * @return array associative array with parameter values
     * @throws RuntimeException
     */
    private function getParameterValues(array $param, RepoResource $res): array {
        $this->loadParameters();

        $values = [];
        foreach ($param as $i) {
            $ii   = explode('|', substr($i, 1, -1));
            $name = array_shift($ii);

            if ($name === 'RES_URI') {
                $value = Parameter::value($res, '', $res->getUri(), $ii);
            } else if ($name === 'RES_ID') {
                $value = Parameter::value($res, '', $res->getUri(), $ii);
            } else if (isset($this->param[$name])) {
                $value = $this->param[$name]->getValue($res, $ii);
            } else {
                throw new RepoLibException('unknown parameter ' . $name . ' (' . $this->getUri() . ')');
            }
            $values[$i] = $value;
        }

        return $values;
    }

    private function loadParameters(): void {
        if (is_array($this->param)) {
            return;
        }
        if ($this->loadParamFromMeta) {
            $type        = $this->getRepo()->getSchema()->dissService->parameterClass;
            $parentProp  = $this->getRepo()->getSchema()->parent;
            $graph       = $this->getGraph()->getGraph();
            $params      = $graph->resourcesMatching($parentProp, $graph->resource($this->getUri()));
            $this->param = [];
            foreach ($params as $i) {
                /* @var $i \EasyRdf\Resource */
                if ($i->isA($type)) {
                    $param                          = new Parameter($i->getUri(), $this->getRepo());
                    $param->setMetadata($i);
                    $this->param[$param->getName()] = $param;
                }
            }
        } else {
            $typeProp    = self::RDF_TYPE;
            $type        = $this->getRepo()->getSchema()->dissService->parameterClass;
            $parentProp  = $this->getRepo()->getSchema()->parent;
            $params      = $this->getRepo()->getResourcesBySearchTerms([
                new SearchTerm($typeProp, $type),
                new SearchTerm($parentProp, $this->getUri()),
            ]);
            $this->param = [];
            foreach ($params as $i) {
                $param                          = new Parameter($i->getUri(), $this->getRepo());
                $param->setGraph($i->getMetadata());
                $this->param[$param->getName()] = $param;
            }
        }
    }

}
