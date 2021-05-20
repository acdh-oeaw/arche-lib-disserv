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

use acdhOeaw\arche\lib\exception\RepoLibException;
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
     * @var array
     */
    static private $transformations = [
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
     * Returns parameter value for a given resource.
     * @param RepoResourceInterface $res repository resource to return the value for
     * @param string $valueProp RDF property holding the parameter value. If
     *   empty, the $default value is used.
     * @param string $default parameter default value
     * @param string $transformations transformations to be applied to the parameter value
     * @return string
     */
    static public function value(RepoResourceInterface $res, string $valueProp,
                                 string $default, array $transformations): string {
        $value = $default;

        if ($valueProp !== '') {
            $matches = $res->getGraph()->all($valueProp);
            if (count($matches) > 0) {
                $value = (string) $matches[0];
            }
        }

        foreach ($transformations as $i) {
            $matches = [];
            preg_match('|^([^(]+)([(].*[)])?$|', $i, $matches);
            $name    = $matches[1];
            if (!isset(self::$transformations[$name])) {
                throw new RepoLibException('unknown transformation');
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

    /**
     * Returns parameter name
     * @return string
     */
    public function getName(): string {
        $meta = $this->getGraph();
        return (string) $meta->getLiteral($this->getRepo()->getSchema()->label);
    }

    /**
     * Return parameter value for a given repository resource
     * @param RepoResourceInterface $res repository resource to get the value for
     * @param string $transformations transformations to be applied to the value
     * @return string
     * @see transform()
     */
    public function getValue(RepoResourceInterface $res,
                             array $transformations = []): string {
        $overwrite = filter_input(INPUT_GET, $this->getName());
        if ($overwrite !== null) {
            $valueProp = '';
            $default   = $overwrite;
        } else {
            $meta      = $this->getGraph();
            $default   = $meta->all($this->getRepo()->getSchema()->dissService->parameterDefaultValue);
            $default   = count($default) > 0 ? (string) $default[0] : '';
            $valueProp = $meta->all($this->getRepo()->getSchema()->dissService->parameterRdfProperty);
            if (count($valueProp) > 0) {
                $valueProp = (string) $valueProp[0];
            } else {
                $valueProp = '';
            }
        }
        return self::value($res, $valueProp, $default, $transformations);
    }
}
