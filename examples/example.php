<?PHP
	require_once 'XML/RDDL.php';
	
	// create new parser
	$rddl   = &new XML_RDDL();
	
	// parse a document that contains RDDL resources
	$result = $rddl->parseRDDL('http://www.rddl.org');
	// check for error
	if (PEAR::isError($result)) {
		echo	sprintf( "ERROR: %s (code %d)", $result->getMessage(), $result->getCode());
		exit;
	}
	
	// get all resources
	$resources = $rddl->getAllResources();
	echo	"<pre>";
	print_r($resources);
	echo	"</pre>";

	//	get one resource by its Id
	$test = $rddl->getResourceById('CSS');
	echo	"<pre>";
	print_r($test);
	echo	"</pre>";
	
	// get all stylesheets
	$test = $rddl->getResourcesByNature('http://www.w3.org/1999/XSL/Transform');
	echo	"<pre>";
	print_r($test);
	echo	"</pre>";
	
	// get all normative references
	$test = $rddl->getResourcesByPurpose('http://www.rddl.org/purposes#normative-reference');
	echo	"<pre>";
	print_r($test);
	echo	"</pre>";
?>