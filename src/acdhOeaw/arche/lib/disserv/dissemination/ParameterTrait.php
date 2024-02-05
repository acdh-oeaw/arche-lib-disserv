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

use rdfInterface\DatasetNodeInterface;
use rdfInterface\NamedNodeInterface;
use rdfInterface\TermInterface;
use termTemplates\PredicateTemplate as PT;
use acdhOeaw\arche\lib\exception\RepoLibException;
use acdhOeaw\arche\lib\Schema;
use acdhOeaw\arche\lib\disserv\RepoResourceInterface;
use acdhOeaw\arche\lib\disserv\dissemination\transformation\iTransformation;
use acdhOeaw\arche\lib\disserv\dissemination\transformation\AddParam;
use acdhOeaw\arche\lib\disserv\dissemination\transformation\Base64Encode;
use acdhOeaw\arche\lib\disserv\dissemination\transformation\UriPart;
use acdhOeaw\arche\lib\disserv\dissemination\transformation\SetParam;
use acdhOeaw\arche\lib\disserv\dissemination\transformation\Substr;
use acdhOeaw\arche\lib\disserv\dissemination\transformation\UrlEncode;
use acdhOeaw\arche\lib\disserv\dissemination\transformation\RawUrlEncode;
use acdhOeaw\arche\lib\disserv\dissemination\transformation\RemoveProtocol;

/**
 * Description of ParameterTrait
 *
 * @author zozlak
 */
trait ParameterTrait {

    /**
     * Stores list of registered transformations
     * @var array<string, string>
     */
    static private array $transformations = [
        'add'            => AddParam::class,
        'base64'         => Base64Encode::class,
        'part'           => UriPart::class,
        'set'            => SetParam::class,
        'substr'         => Substr::class,
        'url'            => UrlEncode::class,
        'rawurlencode'   => RawUrlEncode::class,
        'removeprotocol' => RemoveProtocol::class,
    ];

    /**
     * Registers a new transformation
     * @param iTransformation $transformation transformation to be registered
     */
    static public function registerTransformation(iTransformation $transformation): void {
        self::$transformations[$transformation->getName()] = get_class($transformation);
    }

    /**
     * Applies given transformations to a value
     * @param string $value value
     * @param array<string> $transformations transformations to be applied to the value
     * @return string
     */
    static public function applyTransformations(string $value,
                                                array $transformations = []): string {
        foreach ($transformations as $i) {
            $matches = [];
            preg_match('|^([^(]+)([(].*[)])?$|', $i, $matches);
            $name    = $matches[1];
            if (!isset(self::$transformations[$name])) {
                throw new RepoLibException('Unknown transformation');
            }

            $param = [$value];
            if (isset($matches[2])) {
                $tmp = explode(',', substr($matches[2], 1, -1));
                foreach ($tmp as $j) {
                    $param[] = trim($j);
                }
            }

            $transformation = new self::$transformations[$name]();
            $value          = $transformation->transform(...$param);
        }
        return $value;
    }

    private TermInterface $valueProp;
    private string $default    = '';
    private Schema $namespaces;

    /**
     * Returns parameter name
     * @return string
     */
    public function getName(): string {
        $meta = $this->getGraph();
        return (string) $meta->getObject(new PT($this->getRepo()->getSchema()->label));
    }

    /**
     * Return parameter value for a given repository resource
     * @param RepoResourceInterface $res repository resource to get the value for
     * @param array<string> $transformations transformations to be applied to the value
     * @param ?string $prefix preferred value prefix
     * @param bool $forcePrefix should error be thrown if $prefix is defined and
     *   no value with a given prefix can be found
     * @return string
     * @see transform()
     */
    public function getValue(RepoResourceInterface $res,
                             array $transformations = [],
                             ?string $prefix = null, bool $forcePrefix = false): string {
        $value = filter_input(INPUT_GET, $this->getName());
        if ($value === null) {
            $value = $this->findValue($res->getGraph(), $prefix, $forcePrefix);
        }
        return self::applyTransformations($value, $transformations);
    }

    private function findValue(DatasetNodeInterface $meta, ?string $prefix,
                               bool $force): string {
        $this->init();
        if (!isset($this->valueProp)) {
            return $this->default;
        }

        $values = $meta->listObjects(new PT($this->valueProp));
        if (!$values->valid()) {
            return $this->default;
        }

        if (empty($prefix)) {
            return (string) $values->current();
        }
        $prefix = $this->namespaces[$prefix] ?? throw new RepoLibException("namespace '$prefix' is not defined in the config");

        $allValues = [];
        foreach ($values as $i) {
            if ($i instanceof NamedNodeInterface) {
                $i         = new self((string) $i, $this->getRepo());
                $allValues = array_merge($allValues, $i->getIds());
            } else {
                $allValues[] = (string) $i;
            }
        }
        $allValues = array_unique($allValues);

        $match = null;
        foreach ($allValues as $value) {
            if (str_starts_with($value, $prefix)) {
                $inOtherNmsp = false;
                foreach ($this->namespaces as $otherNmsp) {
                    if ($prefix !== $otherNmsp && str_starts_with($value, $otherNmsp)) {
                        $inOtherNmsp = true;
                        break;
                    }
                }
                if (!$inOtherNmsp) {
                    return $value;
                } else {
                    $match = $value;
                }
            }
        }
        return $match ?? ($force ? $this->default : reset($allValues));
    }

    private function init(): void {
        if (!isset($this->valueProp)) {
            $schema        = $this->getRepo()->getSchema();
            $meta          = $this->getGraph();
            $tmp           = $meta->getObject(new PT($schema->dissService->parameterDefaultValue));
            $this->default = (string) ($tmp ?? '');
            $tmp           = $meta->getObject(new PT($schema->dissService->parameterRdfProperty));
            if ($tmp !== null) {
                $this->valueProp = $tmp;
            }
            $this->namespaces = $schema->namespaces ?? new Schema([]);
        }
    }
}
