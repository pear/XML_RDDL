<?PHP
/* vim: set expandtab tabstop=4 shiftwidth=4: */
// +----------------------------------------------------------------------+
// | PHP Version 4                                                        |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2002 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.0 of the PHP license,       |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Authors: Stephan Schmidt <schst@php-tools.net>                       |
// +----------------------------------------------------------------------+

/**
 * XML_Parser is needed to parse the docuemnt
 */
require_once 'XML/Parser.php';

/**
 * parsing from strings is currently not supported
 */
define('XML_RDDL_ERROR_NOT_SUPPORTED', 151);
 
/**
 * resource with the given ID could not be found
 */
define('XML_RDDL_ERROR_ID_NOT_FOUND', 152);

/**
 * RDDL Parser class
 *
 * This is a class to extract all resources from a
 * {@link http://www.rddl.org RDDL (Resource Directory Description Language)} document.
 *
 * With RDDL resources can be embedded in XHTML, RDF or other XML files.
 * A RDDL resource contains the following information:
 * - Human-readable descriptive material about the target.
 * - A directory of individual resources related to the target, each directory entry containing descriptive material and linked to the resource in question.
 * RDDL makes use of the XLink specificatoins to create links to resources.
 *
 * <code>
 * require_once 'RDDL.php';
 * $rddl   = &new XML_RDDL();
 * $result = $rddl->parseRDDL('http://www.rddl.org');
 *
 * // get all resources
 * $resources = $rddl->getAllResources();
 * 
 * // get one resource by id
 * $resource  = $rddl->getResourceById('CSS');
 *
 * // get all stylesheets that are referenced
 * $stylesheets = $rddl->getResourcesByNature('http://www.w3.org/1999/XSL/Transform');
 *
 * // get all resources that are declared as a normative reference
 * $references  = $rddl->getResourcesByPurpose('http://www.rddl.org/purposes#normative-reference');
 * </code>
 *
 * The resources are returned as associative arrays, which can contain the following information:
 * - lang (language of the resource)
 * - title (a human readable title)
 * - href (the URL where the resource is located)
 * - role (the nature of the resource, machine readable)
 * - arcrole (the purpose of the resource, machine readable)
 * - type (with the current specification, this is always 'simple')
 *
 * @category XML
 * @package  XML_RDDL
 * @version  0.9
 * @author   Stephan Schmidt <schst@php.net>
 * @link	 http://www.rddl.org/
 */
class XML_RDDL extends XML_Parser {

   /**
	* namespace of the RDDL resources
	* @access private
	* @var	string
	*/
	var $_rddlNamespace;

   /**
	* namespace for XLink
	* @access private
	* @var	string
	*/
	var $_xlinkNamespace;

   /**
	* resources that have been found in document
	* @access private
	* @var	array
	*/
	var $_resources = array();

   /**
	* index to resources with an id
	* @access private
	* @var	array
	*/
	var $_index = array();

   /**
	* flag to indicate whether the root tag has been found
	* @access private
	* @var	boolean
	*/
	var $_rootProcessed = false;

   /**
	* base URL, either dirname(file), or xml:base attribute of the root tag
	* @access private
	* @var	string
	*/
	var $_baseUrl = false;

   /**
	* base language
	* @access private
	* @var	string
	*/
	var $_lang = false;

   /**
    * Create a new RDDL parser
	*
	* As XML_Parser currently does not support namespaces
	* you have to define namespaces for RDDL and XLink.
    *
    * @access public
	* @param  string  $rddlNamespace
	* @param  string  $xlinkNamespace
    */
    function XML_RDDL($rddlNamespace = "rddl", $xlinkNamespace = "xlink")
    {
		$this->_rddlNamespace  = strtoupper($rddlNamespace);
		$this->_xlinkNamespace = strtoupper($xlinkNamespace);
		$this->folding   = true;
    }

   /**
    * return API version
    *
    * @access   public
    * @return   string  $version API version
    */
    function apiVersion()
    {
        return "0.9";
    }

   /**
	* parse RDDL document
	*
	* @access	public
	* @param	mixed	$input		resource or filename
	* @param	boolean	$isFile		flag to indicate wether the first parameter is a filename.
	*								Currently only filenames are supported, later versions will also accept strings.
	* @return	mixed	true if no error occured
	* @throws	PEAR_Error
	*/
	function parseRDDL($input, $isFile = true)
	{
		// this has to be done for each XML document
		$this->XML_Parser();

		$this->_index         = array();
		$this->_resources     = array();
		$this->_rootProcessed = false;
		$this->_baseUrl       = false;
		$this->_lang          = false;

		if (is_string($input)) {
			if ($isFile) {
				$this->setInputFile($input);
				$this->_baseUrl = dirname($input);
			} else {
				// processing of strings not yet implemented
				// needs work in XML_Parser
				return PEAR::raiseError('Parsing of strings is not yet supported',XML_RDDL_ERROR_NOT_SUPPORTED, null, null, $id, 'PEAR_Error');
			}
		} elseif (is_resource($input)) {
			$this->setInput($input);
		}
		$result = $this->parse();
		return $result;
	}

    /**
     * Start element handler for XML parser
     *
     * @access private
     * @param  object $parser  XML parser object
     * @param  string $element XML element
     * @param  array  $attribs attributes of XML tag
     * @return void
     */
    function startHandler($parser, $element, $attribs)
    {
		// is this the first tag => then look for some basic definitions
		if (!$this->_rootProcessed) {
			if (isset($attribs["XML:LANG"])) {
				$this->_lang = $attribs["XML:LANG"];
			}
			if (isset($attribs["XML:BASE"])) {
				$this->_baseUrl = $attribs["XML:BASE"];
			}

			$this->_rootProcessed = true;
		}

		// check for namespace
        if (!strstr($element, ':')) {
			return true;
		}
		
		// check for the defined RDF namespace
		list($ns, $local) = explode(':', $element);
		if ($ns != $this->_rddlNamespace) {
			return true;
		}
		
		// only RESOURCE tag can be used
		if ($local != "RESOURCE") {
			return true;
		}

		$resource = array();
		if ($this->_lang !== false) {
			$resource["lang"] = $this->_lang;
		}
		
		foreach ($attribs as $attrib => $value) {
			// resources may have a unique identifier
			if ($attrib == "ID") {
				$resource["id"] = $value;
				// store it in the index
				$this->_index[$value] = count($this->_resources);
				continue;
			}
		
			// check for namespace
	        if (!strstr($attrib, ':')) {
				continue;
			}
			list($ns, $local) = explode(':', $attrib);
			$local = strtolower($local);
			
			// check for the defined XLink namespace
			if ($ns == $this->_xlinkNamespace) {
				$resource[$local] = $value;
			} elseif ($ns == 'XML') {
				// check for xml:base and xml:lang
				switch($local) {
					case	"lang":
						$resource["lang"] = $value;
						break;
					case	"base":
						$resource["base"] = $value;
						break;
				}				
			}
		}
		$resource = $this->_adjustHref($resource);
		array_push($this->_resources, $resource);
    }

   /**
	* get all resources that have been found
	*
	* @access public
	* @return array	resources
	*/
	function getAllResources()
	{
		return $this->_resources;
	}
	
   /**
	* get one resource by a unique id
	*
	* @access public
	* @param  string $id	id of the resource
	* @return array	resource
	*/
	function getResourceById($id)
	{
		if (!isset($this->_index[$id])) {
			return PEAR::raiseError('ID not found',XML_RDDL_ERROR_ID_NOT_FOUND, null, null, $id, 'PEAR_Error');
		}
		return $this->_resources[$this->_index[$id]];
	}

   /**
	* get resources by nature of the resource.
	*
	* This allows you to get all resources that are XSL Stylesheets,
	* DTDs, and so on...
	* The nature of a RDDL resource is defined in the xlink:role attribute.
	*
	* @access public
	* @param  string $nature
	* @return array	resources with the given nature
	* @see getResourcesByPurpose(), getResourcesByLanguage()
	* @link   http://www.rddl.org/#nature
	*/
	function getResourcesByNature($nature)
	{
		return $this->_getResourcesByAttribute('role', $nature);
	}

   /**
	* Get resources by purpose of the resource.
	*
	* The purpose of a RDDL resource is defined in the xlink:arcrole attribute.
	*
	* @access public
	* @param  string $nature
	* @return array	resources with the given purpose
	* @see getResourcesByNature(), getResourcesByLanguage()
	* @link   http://www.rddl.org/#purpuse
	*/
	function getResourcesByPurpose($purpose)
	{
		return $this->_getResourcesByAttribute('arcrole', $purpose);
	}

   /**
	* get resources by language
	*
	* The xml:lang attribute of the root tag and/or the resource tag is evaluated
	* to determine the language of the resource
	*
	* @access public
	* @param  string $lang
	* @return array	resources with the given language
	* @see getResourcesByNature(), getResourcesByPurpose()
	* @link   http://www.rddl.org/#lang
	*/
	function getResourcesByLanguage($lang)
	{
		return $this->_getResourcesByAttribute('lang', $lang);
	}


   /**
	* get all resources where an attribute matches a value
	*
	* This is a genereic method, that's used to select resources.
	*
	* @access private
	* @param  string   $name  name of the attribute
	* @param  string   $value value of the attribute
	* @return array	   matching resources
	*/
	function	_getResourcesByAttribute($name, $value)
	{
		$resources = array();
		$cnt = count($this->_resources);
		for ($i = 0; $i < $cnt; $i++) {
			if (!isset($this->_resources[$i][$name]) ) {
				continue;
			}
			if ($this->_resources[$i][$name] != $value ) {
				continue;
			}

			array_push($resources, $this->_resources[$i]);
		}
		return $resources;
	}

   /**
	* adjust the href of a resource
	*
	* If the resource has a xml:base attribute, it is used,
	* otherwise the xml:base of the root element is used.
	* if none of these is present, relative paths are calculated from
	* the current file.
	*
	* @access	private
	* @param	array	$resource	resource data as an array
	* @return	array	$resource	resource data with adjusted href value
	*/
	function _adjustHref($resource)
	{
		if (isset($resource["base"])) {
			$base = $resource["base"];
		} elseif( $this->_baseUrl !== false ) {
			$base = $this->_baseUrl;
		} else {
			return $resource;
		}

		$href = parse_url($resource["href"]);

		// if scheme is present, base is not needed
		if(isset($href["scheme"])) {
			return $resource;
		}
		
		//	prepend base
		$resource["href"] = $base . $resource["href"];
		return	$resource;
	}
}
?>