# PHP JSON-LD Parser extender for ARC2

## Overview

ARC2_JSONLDParser.php is an ARC2 module to parse JSON-LD and extract RDF, by extending JsonLdProcessor provided by jsonld.php.

ARC2_JSONLDParser has a class JSONLD2RDF which extends JsonLdProcessor in order to get ARC2 compatible quad object from the parse results returned by JsonLdProcessor.


## Installation

Place ARC2_JSONLDParser.php and jsonld.php in ARC2's 'parsers' directory.

## Credits

ARC2_JSONLDParser.php was created and maintained by KANZAKI, Masahide, 2012-2017. jsonld.php was created by Dave Longley, 2011-2014. Licenses are the same as original:

- ARC2_MicrodataParser.php is licensed under The W3C Software License or the GPL.
- jsonld.php is licensed under BSD 3-Clause License.

The bundled jsonld.php is a slitely modified version of its v0.4.8-dev, for easier info access from ARC2_JSONLDParser.



## ARC2_JSONLDParser.php

This file follows the general style of ARC2 parser, so you can call this parser from within ARC2 application and use the extracted results (RDF triples) with standard ARC2 methods.

A simple usage might be:

```php
include_once($arc_path."ARC2.php");
$parser = ARC2::getParser("JSONLD");
$parser->parse($uri);
$turtle = $parser->toTurtle($parser->getTriples());
```

### Original jsonld.php

jsonld.php is a part of php-json-ld, maintained at https://github.com/digitalbazaar/php-json-ld .
