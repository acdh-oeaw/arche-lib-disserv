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
 * Conditionally removes parameter value 
 *
 * @author zozlak
 */
class EmptyIf implements iTransformation {

    /**
     * Returns transformation name
     */
    public function getName(): string {
        return 'emptyIf';
    }

    /**
     * Returns empty value if query parameter of a given name has a given value
     *   or just is not empty if the $value is empty
     * @return string
     */
    public function transform(string $value, string $varName = '',
                              string $varValue = ''): string {
        if (empty($value)) {
            return !empty($_GET[$varName]) ? '' : $value;
        } else {
            return ($_GET[$varName] ?? null) === $varValue ? '' : $value;
        }
    }
}
