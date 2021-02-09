#!/bin/bash
echo "INSERT INTO resources (id) SELECT generate_series(10000, 10100);" | psql 
echo "INSERT INTO identifiers (ids, id) SELECT 'http://127.0.0.1/api/' || id, id FROM resources WHERE id >= 10000;" | psql
echo "INSERT INTO identifiers (ids, id) SELECT 'https://id.acdh.oeaw.ac.at/' || id, id FROM resources WHERE id >= 10000;" | psql
echo "INSERT INTO metadata (id, property, type, lang, value) SELECT id, 'https://vocabs.acdh.oeaw.ac.at/schema#hasFormat', 'http://www.w3.org/2001/XMLSchema#string', '', 'application/xml' FROM resources WHERE id BETWEEN 10000 AND 10020;" | psql
echo "INSERT INTO identifiers (ids, id) VALUES ('https://vocabs.acdh.oeaw.ac.at/archeoaisets/clarin-vlo', 10100);" | psql
echo "INSERT INTO relations (id, property, target_id) SELECT id, 'https://vocabs.acdh.oeaw.ac.at/schema#hasOaiSet', 10100 FROM resources WHERE id BETWEEN 10000 AND 10010;" | psql
