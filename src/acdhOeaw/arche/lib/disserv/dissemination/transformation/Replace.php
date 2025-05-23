<?php

/*
 * The MIT License
 *
 * Copyright 2025 Austrian Centre for Digital Humanities.
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

namespace acdhOeaw\arche\lib\disserv\dissemination\transformation;

/**
 * Runs str_replace()
 *
 * @author zozlak
 */
class Replace implements iTransformation {

    /**
     * Returns transformation name
     */
    public function getName(): string {
        return 'emptyIf';
    }

    /**
     * Returns str_replace()
     * @param string $value value to be transformed
     * @param string $from
     * @param string $to
     * @param string $sep if provided, $from and $to are split into arrays using
     *   $sep as a separator
     * @return string
     */
    public function transform(string $value, string $from = '', string $to = '',
                              string $sep = ''): string {
        if (!empty($sep)) {
            $from = explode($sep, $from);
            $to   = explode($sep, $to);
        }
        return str_replace($from, $to, $value);
    }
}
