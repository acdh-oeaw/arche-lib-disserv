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

use Generator;
use RuntimeException;
use GuzzleHttp\Psr7\Request;
use acdhOeaw\arche\lib\disserv\RepoResourceInterface;

/**
 *
 * @author zozlak
 */
interface ServiceInterface {

    const QUERY_RES     = 1;
    const QUERY_DISSERV = 2;

    /**
     * Returns all return formats provided by the dissemination service.
     * 
     * Technically return formats are nothing more then strings. There is no
     * requirement forcing them to be mime types, etc. 
     * @return array<Format>
     */
    public function getFormats(): array;

    /**
     * Returns PSR-7 HTTP request disseminating a given resource.
     * @param RepoResourceInterface $res repository resource to be disseminated
     * @return Request
     * @throws RuntimeException
     */
    public function getRequest(RepoResourceInterface $res): Request;

    /**
     * Tells the service it can load information on service parameters from its metadata.
     * 
     * Information will be loaded in a lazy way (when it's needed).
     * 
     * @return void
     */
    public function loadParametersFromMetadata(): void;

    /**
     * Gets disseminations service's URL (before parameters subsitution)
     * @return string
     */
    public function getLocation(): string;

    /**
     * Should the dissemination service request be reverse-proxied?
     * 
     * If it's not set in the metadata, false is assumed.
     * @return bool
     */
    public function getRevProxy(): bool;
    
    /**
     * Returns repository resources which can be disseminated by a given service
     * 
     * @return Generator<RepoResourceInterface>
     */
    public function getMatchingResources(): Generator;
}
