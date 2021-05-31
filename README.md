# arche-lib-disserv

[![Latest Stable Version](https://poser.pugx.org/acdh-oeaw/arche-lib-disserv/v/stable)](https://packagist.org/packages/acdh-oeaw/arche-lib-disserv)
![Build status](https://github.com/acdh-oeaw/arche-lib-disserv/workflows/phpunit/badge.svg?branch=master)
[![Coverage Status](https://coveralls.io/repos/github/acdh-oeaw/arche-lib-disserv/badge.svg?branch=master)](https://coveralls.io/github/acdh-oeaw/arche-lib-disserv?branch=master)
[![License](https://poser.pugx.org/acdh-oeaw/arche-lib-disserv/license)](https://packagist.org/packages/acdh-oeaw/arche-lib-disserv)

A library implementing dissemination services on top of the [arche-lib](https://github.com/acdh-oeaw/arche-lib)

## Installation

`composer require acdh-oeaw/acdh-repo-acdh`

## Documentation

API documentation: https://acdh-oeaw.github.io/arche-docs/devdocs/namespaces/acdhoeaw-arche-lib-disserv.html

Broader description of the dissemination services idea: https://acdh-oeaw.github.io/arche-docs/aux/dissemination_services.html

## Dissemination service description schema

Dissemination service description is provided in RDF.

There are three classes of resources describing a service:

* The service itself
  * required predicates:
    * RDF class `https://vocabs.acdh.oeaw.ac.at/schema#DisseminationService`
    * `https://vocabs.acdh.oeaw.ac.at/schema#hasIdentifier` (URI) dissemination service identifier.
    * `https://vocabs.acdh.oeaw.ac.at/schema#hasTitle` (string) dissemination service name.
    * `https://vocabs.acdh.oeaw.ac.at/schema#serviceLocation` (string, exactly one value) provides a redirection URL template.  
      The template can contain parameter placeholders using the `{name([@&]prefix)?(|trans)*}` syntax, where:
      * `name` is one of 
        `RES_URI`/`RES_URL` - the repository resource URL, 
        `RES_ID` - the internal repository resource identifier (the number being the last part of the `RES_URL`),
        `ID` - resource identifier,
        custom parameter placeholder name as described below
      * `&prefix` (requested prefix) or `@prefix` (preferred prefix) allow to define value prefix.
        This can be useful if many values are expected, e.g. when `name` is `RES_ID`.
        The actual prefix comes from the YAML config (`$.schema.namespaces.{id}`).
        `&prefix` and `@prefix` behavior differs if no value matches a given prefix. In such a case `&prefix` returns empty value while `@prefix` returns any value.
      * `|trans` is optional chain of transformations like URL-encoding, extracting URI part, etc.
        You can check available transformations [here](https://github.com/acdh-oeaw/arche-lib-disserv/blob/master/src/acdhOeaw/arche/lib/disserv/dissemination/ParameterTrait.php#L44).
        If a transformation takes additional parameters the syntax is `|trans(p1,p2)`.
        Transformations can be chained, e.g. `|trans1|trans2(p1)`.
        For real world examples see [here](https://github.com/acdh-oeaw/arche-docker-config/blob/arche/initScripts/dissServices.ttl).
    * `https://vocabs.acdh.oeaw.ac.at/schema#hasReturnType` (string, one or more values) describes the returned data format.
      Technically it can be any string but the value should be easy to guess by users (so e.g. return mime type can be a good idea).
* Matching rules  
  Rules describing how to find repository resources which can be processed by a given service.  
  All required rules and, if defined, at least one optional rule must match for the dissemination service to match.  
  In case of no rules being defined, all repository resources match a given dissemination service.
  * required predicates:
    * `https://vocabs.acdh.oeaw.ac.at/schema#relation` (URI) pointing to the service's `https://vocabs.acdh.oeaw.ac.at/schema#hasIdentifier` value.
    * `https://vocabs.acdh.oeaw.ac.at/schema#hasIdentifier` (URI) match rule identifier.
    * `https://vocabs.acdh.oeaw.ac.at/schema#hasTitle` (string) match rule name.
    * `https://vocabs.acdh.oeaw.ac.at/schema#matchesProp` (string, exactly one value) metadata predicate the rule is testing.
    * `https://vocabs.acdh.oeaw.ac.at/schema#isRequired` (bool, exactly one value) is this rule a required or an optional one?
  * optional predicates:
    * `https://vocabs.acdh.oeaw.ac.at/schema#matchesValue` (string, no more than one value) value required for the rule to match.
      If not provided, any value is accepted. All values are casted to string before the comparison.
* Redirection URL template placeholders  
  Describe the way URL template placeholders are substituted with values.
  * required predicates:
    * RDF class `https://vocabs.acdh.oeaw.ac.at/schema#DisseminationServiceParameter`
    * `https://vocabs.acdh.oeaw.ac.at/schema#relation` (URI) pointing to the service's `https://vocabs.acdh.oeaw.ac.at/schema#hasIdentifier` value.
    * `https://vocabs.acdh.oeaw.ac.at/schema#hasIdentifier` (URI) parameter placeholder identifier.
    * `https://vocabs.acdh.oeaw.ac.at/schema#hasTitle` (string) parameter placeholder name (must match the name used in the redirection URL template!).
  * optional predicates:
    * `https://vocabs.acdh.oeaw.ac.at/schema#hasDefaultValue` (string, no more than one value) default value.
    * `https://vocabs.acdh.oeaw.ac.at/schema#usesRdfProperty` (string, no more than one value) RDF property from which the placeholder value will be taken.

Example definition can be found [here](https://github.com/acdh-oeaw/arche-docker-config/blob/arche/initScripts/dissServices.ttl).

See also https://acdh-oeaw.github.io/arche-docs/aux/dissemination_services.html
