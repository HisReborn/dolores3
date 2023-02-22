
<?php
/**
 * JSON extension to the PHP-XMLRPC lib
 *
 * For more info see:
 * http://www.json.org/
 * http://json-rpc.org/
 *
 * @version $Id: jsonrpc.inc,v 1.36 2009/02/05 09:50:59 ggiunta Exp $
 * @author Gaetano Giunta
 * @copyright (c) 2005-2009 G. Giunta
 * @license code licensed under the BSD License: http://phpxmlrpc.sourceforge.net/license.txt
 *
 * @todo the JSON proposed RFC states that when making json calls, we should
 *       specify an 'accep: application/json' http header. Currently we either
 *       do not otuput an 'accept' header or specify  'any' (in curl mode)
 **/

	// requires: xmlrpc.inc 2.0 or later

	// Note: the json spec omits \v, but it is present in ECMA-262, so we allow it
	$GLOBALS['ecma262_entities'] = array(
		'b' => chr(8),
		'f' => chr(12),
		'n' => chr(10),
		'r' => chr(13),
		't' => chr(9),
		'v' => chr(11)
	);

	// tables used for transcoding different charsets into us-ascii javascript

	$GLOBALS['ecma262_iso88591_Entities']=array();
	$GLOBALS['ecma262_iso88591_Entities']['in'] = array();
	$GLOBALS['ecma262_iso88591_Entities']['out'] = array();
	for ($i = 0; $i < 32; $i++)
	{
		$GLOBALS['ecma262_iso88591_Entities']['in'][] = chr($i);
		$GLOBALS['ecma262_iso88591_Entities']['out'][] = sprintf('\u%\'04x', $i);
	}
	for ($i = 160; $i < 256; $i++)
	{
		$GLOBALS['ecma262_iso88591_Entities']['in'][] = chr($i);
		$GLOBALS['ecma262_iso88591_Entities']['out'][] = sprintf('\u%\'04x', $i);
	}

	/**
	* Encode php strings to valid JSON unicode representation.
	* All chars outside ASCII range are converted to \uXXXX for maximum portability.
	* @param string $data (in iso-8859-1 charset by default)
	* @param string charset of source string, defaults to $GLOBALS['xmlrpc_internalencoding']
	* @param string charset of the encoded string, defaults to ASCII for maximum interoperabilty
	* @return string
	* @access private
	* @todo add support for UTF-16 as destination charset instead of ASCII
	* @todo add support for UTF-16 as source charset
	*/
	function json_encode_entities($data, $src_encoding='', $dest_encoding='')
	{
		if ($src_encoding == '')
		{
			// lame, but we know no better...
			$src_encoding = $GLOBALS['xmlrpc_internalencoding'];
		}

		switch(strtoupper($src_encoding.'_'.$dest_encoding))
		{
			case 'ISO-8859-1_':
			case 'ISO-8859-1_US-ASCII':
				$escaped_data = str_replace(array('\\', '"', '/', "\t", "\n", "\r", chr(8), chr(11), chr(12)), array('\\\\', '\"', '\/', '\t', '\n', '\r', '\b', '\v', '\f'), $data);
				$escaped_data = str_replace($GLOBALS['ecma262_iso88591_Entities']['in'], $GLOBALS['ecma262_iso88591_Entities']['out'], $escaped_data);
				break;
			case 'ISO-8859-1_UTF-8':
				$escaped_data = str_replace(array('\\', '"', '/', "\t", "\n", "\r", chr(8), chr(11), chr(12)), array('\\\\', '\"', '\/', '\t', '\n', '\r', '\b', '\v', '\f'), $data);
				$escaped_data = utf8_encode($escaped_data);
				break;
			case 'ISO-8859-1_ISO-8859-1':
			case 'US-ASCII_US-ASCII':
			case 'US-ASCII_UTF-8':
			case 'US-ASCII_':