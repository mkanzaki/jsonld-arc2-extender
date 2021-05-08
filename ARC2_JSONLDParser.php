<?php
/** @prefix : <http://purl.org/net/ns/doas#> .
<> a :PHPScript;
 :title "JSON-LD Parser/Extractor for ARC2";
 :created "2012-10-01";
 :release [:revision "1.04"; :created "2017-08-31"];
 :description """
 Extracts ARC representation of RDF graph from JSON-LD.
 Make sure to place this file in ARC2's 'parsers' directory, and also have jsonld.php there.
 License is the same as that of ARC2: The W3C Software License or the GPL.
 
 Usage is almost the same as other ARC2 parsers:
	$parser = ARC2::getParser("JSONLD");
	$parser->parse($uri);
	$turtle = $parser->toTurtle($parser->getTriples());
 
 """ ;
 
 :seeAlso <http://www.w3.org/TR/json-ld-syntax/> ;
 :seeAlso <https://github.com/digitalbazaar/php-json-ld> ;
 :dependencies "should be in ARC2's parsers directory. Also requires jsonld.php" ;
 :license <http://www.w3.org/Consortium/Legal/copyright-software.html>, <http://www.gnu.org/copyleft/gpl.html>.
 */

//ARC2::inc('Class');
ARC2::inc('RDFParser');
include_once(dirname(__FILE__)."/jsonld.php");

/**
 * ARC2 common parser/extractor methods
 */
class ARC2_JSONLDParser extends ARC2_RDFParser{//ARC2_Class
	
	/**
	 * initialize some global fields (callded by ARC::Class constructor)
	 */
	function __init() {
		parent::__init();
		$this->triples = array();
		$this->t_count = 0;
		$this->added_triples = array();
		$this->bnode_prefix = $this->v('bnode_prefix', 'arc'.substr(md5(uniqid(rand())), 0, 4), $this->a);
		$this->bnode_id = 0;
		$this->auto_extract = $this->v('auto_extract', 1, $this->a);
	}
	
	/**
	 * create new blank node ID
	 */
	function createBnodeID(){
		$this->bnode_id++;
		return '_:' . $this->bnode_prefix . $this->bnode_id;
	}
	
	/**
	 * get all triples stored in the ARC2 internal structure
	 * @return array	Set of triples
	 */
	function getTriples() {
		return $this->v('triples', array());
	}

	/**
	 * count processed triples by addT()
	 * @return int	number of triples
	 */
	function countTriples() {
		return $this->t_count;
	}
	
	/**
	 * get simple index style triple
	 * @param int $flatten_objects	
	 * @param string $vals	
	 * @return array	simple index
	 */
	function getSimpleIndex($flatten_objects = 1, $vals = '') {
		return ARC2::getSimpleIndex($this->getTriples(), $flatten_objects, $vals);
	}

	/**
	 * expands getSimpleIndex with graph name as its top level
	 * @param array &$quads	reference to Triples array
	 * @param boolean $flatten_objects	whether to eliminate duplicated triples
	 * @return array 	structured array: 1st key=graph name, 2nd key=subject URI
	 */
	function & getQuadIndex(&$quads=null, $flatten_objects = false){
		if(!$quads) $quads = $this->triples;
		$graphs = $this->getQuadArray($quads);
		$index = array();
		foreach($graphs as $g => $triples){
			//デフォルトグラフでは$gが空になる
			$index[$g] = ARC2::getSimpleIndex($triples, $flatten_objects);
		}
		return $index;
	}
	/**
	 * convert flat quad array to structured triples array with graph name as keys.
	 * @param array &$quads	reference to Triples array
	 * @return array 	stuructured Triples array
	 */
	function & getQuadArray(&$quads=null){
		if(!$quads) $quads = $this->triples;
		$graphs = array();
		foreach($quads as $q) {
			$graphs[$q['g']][] = $q;
		}
		return $graphs;
	}
	/**
	 * converts flat quad array to N-Quad
	 * @param array &$quads	reference to Triples array
	 * @return array 	N-Quad string
	 */
	function toNQuad(&$quads=null){
		if(!$quads) $quads = $this->triples;
		$nquad = "";
		foreach($quads as $q) {
			$nquad .= $this->expand($q['s'], $q['s_type'])." <".$q['p']."> ".
			$this->expand($q['o'], $q['o_type'], $q['o_datatype'], $q['o_lang']).
			($q['g'] ? " ".$this->expand($q['g'], substr($q['g'],0,2)=='_:' ? 'bnode' : 'uri'): ""). " .\n";
		}
		return $nquad;
	}
	/**
	 * adds <>, "", datatype, lang etc according to the type
	 * @param string $elt	value of the element
	 * @param string $type	type of the element
	 * @param string $d	datatype of the literal element
	 * @param string $lang	lang tag of the literal element
	 * @return string	formatted value string
	 */
	function expand($elt, $type, $dt="", $lang=""){
		switch($type){
		case 'uri':
			return "<$elt>";
		case 'literal':
			$elt = (strpos($elt, '"') or strpos($elt, "\n")) ? '"""'.$let.'"""' : '"'.$elt.'"';
			if($lang) $elt .= "@".$lang;
			elseif($dt) $elt .= "^^<$dt>";
			return $elt;
		case 'bnode':
		default:
			return $elt;
		}
	}
	/**
	 * Parses JSON-LD, and store the resulting triples in ARC2 structure
	 * @param string $url	URL of source JSON-LD, or base URL if $data is provided
	 * @param mixed $data	JSON string or JSON object (optional)
	 * @return mixed number of triples if success, false if JSON parse failed
	 */
	function parse($url, $data="") {
		if(!isset($this->baseUri)) $this->baseUri = $url;
		if(empty($data)) $data = file_get_contents($url);
		$json = is_string($data) ? json_decode($data) : $data;
		if($json === NULL){
			$this->addError("JSON Error: <code>json_decode()</code> failed to parse.");
			return false;
		}
		$jsonld = new JSONLD2RDF();
		//＠＠ここでpfxを更新しないと、アプリケーションからparseを反復呼び出しした時に、toARC2経由で呼ばれるUniqueNamerがidを0に戻すためにIDが重複する。2017-10-20
		$jsonld->bnode_prefix = $this->bnode_prefix = $this->v('bnode_prefix', 'b'.substr(md5(uniqid(rand())), 0, 6), $this->a);
		$option = array(
			'base' => $this->baseUri
		);
		try{
			$triples = $jsonld->toARC2($json, $option);
			$this->t_count += count($triples);
			$this->triples += $triples;
			if(isset($jsonld->masaka["pfx2uri"])){
				$this->ns += $jsonld->masaka["pfx2uri"];
				$this->nsp += $jsonld->masaka["uri2pfx"];
			}
			return $triples;
		}catch(Exception $e){
			$this->addError("JSON-LD Error:");
			$this->addError($e->getMessage());
			if($det = $e->details) $this->addError($det->getMessage());
			return false;
		}
	}

	/**
	 * Set base URI to resolve relative URIs (if not set, document URI is base URI).
	 * @param string $uri	the base URI
	 */
	function setBase($uri){
		$this->baseUri = $uri;
	}
	
	/**
	 * Parses JSON-LD inside <script> element in HTML
	 * @param string $url	URL of source HTML, or base URL if $data is provided
	 * @param string $data	HTML string (optional)
	 * @return mixed number of triples if success, false if parse failed
	 */
	function parse_html($uri, $data=""){
		$lx = ARC2::getParser("LegacyXML");
		$lx->parse($uri, $data);
		$jsonld = "";
		foreach($lx->nodes as $node){
			if($node["tag"] == "script"){
				if(isset($node["a"]) and $node["a"]["type"]=="application/ld+json"){
					$jsonld = trim($node["cdata"]);
					break;
				}
			}
		}
		if($jsonld){
			return self::parse($uri, $jsonld);
		}else{
			return false;
		}
	}

}


class JSONLD2RDF extends JsonLdProcessor {
	/** @var array jsonld.phpのJsonLdProcessor内を含むクラスのデータを保持しておく配列*/
	var $masaka = array();

	/**
	 * Outputs the RDF statements found in the given JSON-LD object.
	 *
	 * @param mixed $input the JSON-LD object.
	 * @param assoc $options the options to use:
	 *					[base] the base IRI to use.
	 *					[resolver(url)] the URL resolver to use.
	 *
	 * @return array all RDF statements in the JSON-LD object.
	 */
	public function & toARC2($input, $options=array()) {
		global $jsonld_default_load_document;
		self::setdefaults($options, array(
			'base' => is_string($input) ? $input : '',
			'produceGeneralizedRdf' => false,
			'documentLoader' => $jsonld_default_load_document));

		try {
			// expand input
			$expanded = $this->expand($input, $options);
		} catch(JsonLdException $e) {
			throw new JsonLdException(
			'Could not expand input before serialization to RDF.',
			'jsonld.RdfError', null, null, $e);
		}

		// create node map for default graph (and any named graphs)
		//$namer = new UniqueNamer('_:b');
		//＠＠これは毎回counterを0にするので、bnode_prefixが同じだとbnodeIdが重複する
		$namer = new UniqueNamer('_:'.$this->bnode_prefix); //@masaka 2017-08-31
		$node_map = (object)array('@default' => new stdClass());
		$this->_createNodeMap($expanded, $node_map, '@default', $namer);

		// output RDF dataset
		$dataset = new stdClass();
		$graph_names = array_keys((array)$node_map);
		sort($graph_names);
		foreach($graph_names as $graph_name) {
			$graph = $node_map->{$graph_name};
			// skip relative IRIs
			if($graph_name === '@default' || self::_isAbsoluteIri($graph_name)) {
				$dataset->{$graph_name} = $this->_graphToRDF($graph, $namer, $options);
			}
		}

		// convert to output format
		$quads = array();
		foreach((array)$dataset as $graph => $statements){
			//print "$graph:<pre>".print_r($statements,true)."</pre>";
			if($graph === '@default') $graph = "";
			foreach((array)$statements as $statement) {
				$this->addT($statement, $quads, $graph);
			}
		}

		// output RDF statements
		//debug_show($quads);
		return $quads;
	}
	
	/**
	 * Converts an RDF statement to an ARC2 data.
	 *
	 * @param stdClass $statement the RDF statement to convert.
	 * @param array $quads ARC2 array.
	 * @param string $graph graph label.
	 *
	 */
	public function addT(&$statement, &$quads, $graph) {
		$s = $statement->subject;
		$p = $statement->predicate;
		$o = $statement->object;
		$t = array(
			's' => $s->value,
			's_type' => ($s->type === 'IRI' ? 'uri' : 'bnode'),
			'p' => $p->value,
			'g' => $graph
		);

		// object is IRI, bnode, or literal
		if($o->type === 'IRI') {
			$t['o'] = $o->value;
			$t['o_type'] = 'uri';
		}
		else if($o->type === 'blank node') {
			$t['o'] = $o->value;
			$t['o_type'] = 'bnode';
		}
		else {
			$t['o_type'] = 'literal';
			//json_decodeで実体参照にされてしまう文字をもとに戻す
			$t['o'] = html_entity_decode($o->value, ENT_COMPAT, "UTF-8");
			//langを先にしないと^^langStringが優先されてしまう
			if(property_exists($o, 'language')) {
				$t['o_lang'] = $o->language;
			}
			else if(property_exists($o, 'datatype') &&
				$o->datatype !== JsonLdProcessor::XSD_STRING) {
				$t['o_datatype'] = $o->datatype;
			}
		}
		$quads[] = $t;
	}
	
}
?>
