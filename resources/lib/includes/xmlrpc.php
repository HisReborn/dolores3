
<?php
// by Edd Dumbill (C) 1999-2002
// <edd@usefulinc.com>
// $Id: xmlrpc.inc,v 1.174 2009/03/16 19:36:38 ggiunta Exp $

// Copyright (c) 1999,2000,2002 Edd Dumbill.
// All rights reserved.
//
// Redistribution and use in source and binary forms, with or without
// modification, are permitted provided that the following conditions
// are met:
//
//    * Redistributions of source code must retain the above copyright
//      notice, this list of conditions and the following disclaimer.
//
//    * Redistributions in binary form must reproduce the above
//      copyright notice, this list of conditions and the following
//      disclaimer in the documentation and/or other materials provided
//      with the distribution.
//
//    * Neither the name of the "XML-RPC for PHP" nor the names of its
//      contributors may be used to endorse or promote products derived
//      from this software without specific prior written permission.
//
// THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS
// "AS IS" AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT
// LIMITED TO, THE IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS
// FOR A PARTICULAR PURPOSE ARE DISCLAIMED. IN NO EVENT SHALL THE
// REGENTS OR CONTRIBUTORS BE LIABLE FOR ANY DIRECT, INDIRECT,
// INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
// (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
// SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
// HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
// STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE)
// ARISING IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED
// OF THE POSSIBILITY OF SUCH DAMAGE.

	if(!function_exists('xml_parser_create'))
	{
		// For PHP 4 onward, XML functionality is always compiled-in on windows:
		// no more need to dl-open it. It might have been compiled out on *nix...
		if(strtoupper(substr(PHP_OS, 0, 3) != 'WIN'))
		{
			dl('xml.so');
		}
	}

	// G. Giunta 2005/01/29: declare global these variables,
	// so that xmlrpc.inc will work even if included from within a function
	// Milosch: 2005/08/07 - explicitly request these via $GLOBALS where used.
	$GLOBALS['xmlrpcI4']='i4';
	$GLOBALS['xmlrpcInt']='int';
	$GLOBALS['xmlrpcBoolean']='boolean';
	$GLOBALS['xmlrpcDouble']='double';
	$GLOBALS['xmlrpcString']='string';
	$GLOBALS['xmlrpcDateTime']='dateTime.iso8601';
	$GLOBALS['xmlrpcBase64']='base64';
	$GLOBALS['xmlrpcArray']='array';
	$GLOBALS['xmlrpcStruct']='struct';
	$GLOBALS['xmlrpcValue']='undefined';

	$GLOBALS['xmlrpcTypes']=array(
		$GLOBALS['xmlrpcI4']       => 1,
		$GLOBALS['xmlrpcInt']      => 1,
		$GLOBALS['xmlrpcBoolean']  => 1,
		$GLOBALS['xmlrpcString']   => 1,
		$GLOBALS['xmlrpcDouble']   => 1,
		$GLOBALS['xmlrpcDateTime'] => 1,
		$GLOBALS['xmlrpcBase64']   => 1,
		$GLOBALS['xmlrpcArray']    => 2,
		$GLOBALS['xmlrpcStruct']   => 3
	);

	$GLOBALS['xmlrpc_valid_parents'] = array(
		'VALUE' => array('MEMBER', 'DATA', 'PARAM', 'FAULT'),
		'BOOLEAN' => array('VALUE'),
		'I4' => array('VALUE'),
		'INT' => array('VALUE'),
		'STRING' => array('VALUE'),
		'DOUBLE' => array('VALUE'),
		'DATETIME.ISO8601' => array('VALUE'),
		'BASE64' => array('VALUE'),
		'MEMBER' => array('STRUCT'),
		'NAME' => array('MEMBER'),
		'DATA' => array('ARRAY'),
		'ARRAY' => array('VALUE'),
		'STRUCT' => array('VALUE'),
		'PARAM' => array('PARAMS'),
		'METHODNAME' => array('METHODCALL'),
		'PARAMS' => array('METHODCALL', 'METHODRESPONSE'),
		'FAULT' => array('METHODRESPONSE'),
		'NIL' => array('VALUE'), // only used when extension activated
		'EX:NIL' => array('VALUE') // only used when extension activated
	);

	// define extra types for supporting NULL (useful for json or <NIL/>)
	$GLOBALS['xmlrpcNull']='null';
	$GLOBALS['xmlrpcTypes']['null']=1;

	// Not in use anymore since 2.0. Shall we remove it?
	/// @deprecated
	$GLOBALS['xmlEntities']=array(
		'amp'  => '&',
		'quot' => '"',
		'lt'   => '<',
		'gt'   => '>',
		'apos' => "'"
	);

	// tables used for transcoding different charsets into us-ascii xml

	$GLOBALS['xml_iso88591_Entities']=array();
	$GLOBALS['xml_iso88591_Entities']['in'] = array();
	$GLOBALS['xml_iso88591_Entities']['out'] = array();
	for ($i = 0; $i < 32; $i++)
	{
		$GLOBALS['xml_iso88591_Entities']['in'][] = chr($i);
		$GLOBALS['xml_iso88591_Entities']['out'][] = '&#'.$i.';';
	}
	for ($i = 160; $i < 256; $i++)
	{
		$GLOBALS['xml_iso88591_Entities']['in'][] = chr($i);
		$GLOBALS['xml_iso88591_Entities']['out'][] = '&#'.$i.';';
	}

	/// @todo add to iso table the characters from cp_1252 range, i.e. 128 to 159?
	/// These will NOT be present in true ISO-8859-1, but will save the unwary
	/// windows user from sending junk (though no luck when reciving them...)
  /*
	$GLOBALS['xml_cp1252_Entities']=array();
	for ($i = 128; $i < 160; $i++)
	{
		$GLOBALS['xml_cp1252_Entities']['in'][] = chr($i);
	}
	$GLOBALS['xml_cp1252_Entities']['out'] = array(
		'&#x20AC;', '?',        '&#x201A;', '&#x0192;',
		'&#x201E;', '&#x2026;', '&#x2020;', '&#x2021;',
		'&#x02C6;', '&#x2030;', '&#x0160;', '&#x2039;',
		'&#x0152;', '?',        '&#x017D;', '?',
		'?',        '&#x2018;', '&#x2019;', '&#x201C;',
		'&#x201D;', '&#x2022;', '&#x2013;', '&#x2014;',
		'&#x02DC;', '&#x2122;', '&#x0161;', '&#x203A;',
		'&#x0153;', '?',        '&#x017E;', '&#x0178;'
	);
  */

	$GLOBALS['xmlrpcerr'] = array(
	'unknown_method'=>1,
	'invalid_return'=>2,
	'incorrect_params'=>3,
	'introspect_unknown'=>4,
	'http_error'=>5,
	'no_data'=>6,
	'no_ssl'=>7,
	'curl_fail'=>8,
	'invalid_request'=>15,
	'no_curl'=>16,
	'server_error'=>17,
	'multicall_error'=>18,
	'multicall_notstruct'=>9,
	'multicall_nomethod'=>10,
	'multicall_notstring'=>11,
	'multicall_recursion'=>12,
	'multicall_noparams'=>13,
	'multicall_notarray'=>14,

	'cannot_decompress'=>103,
	'decompress_fail'=>104,
	'dechunk_fail'=>105,
	'server_cannot_decompress'=>106,
	'server_decompress_fail'=>107
	);

	$GLOBALS['xmlrpcstr'] = array(
	'unknown_method'=>'Unknown method',
	'invalid_return'=>'Invalid return payload: enable debugging to examine incoming payload',
	'incorrect_params'=>'Incorrect parameters passed to method',
	'introspect_unknown'=>"Can't introspect: method unknown",
	'http_error'=>"Didn't receive 200 OK from remote server.",
	'no_data'=>'No data received from server.',
	'no_ssl'=>'No SSL support compiled in.',
	'curl_fail'=>'CURL error',
	'invalid_request'=>'Invalid request payload',
	'no_curl'=>'No CURL support compiled in.',
	'server_error'=>'Internal server error',
	'multicall_error'=>'Received from server invalid multicall response',
	'multicall_notstruct'=>'system.multicall expected struct',
	'multicall_nomethod'=>'missing methodName',
	'multicall_notstring'=>'methodName is not a string',
	'multicall_recursion'=>'recursive system.multicall forbidden',
	'multicall_noparams'=>'missing params',
	'multicall_notarray'=>'params is not an array',

	'cannot_decompress'=>'Received from server compressed HTTP and cannot decompress',
	'decompress_fail'=>'Received from server invalid compressed HTTP',
	'dechunk_fail'=>'Received from server invalid chunked HTTP',
	'server_cannot_decompress'=>'Received from client compressed HTTP request and cannot decompress',
	'server_decompress_fail'=>'Received from client invalid compressed HTTP request'
	);

	// The charset encoding used by the server for received messages and
	// by the client for received responses when received charset cannot be determined
	// or is not supported
	$GLOBALS['xmlrpc_defencoding']='UTF-8';

	// The encoding used internally by PHP.
	// String values received as xml will be converted to this, and php strings will be converted to xml
	// as if having been coded with this
	$GLOBALS['xmlrpc_internalencoding']='ISO-8859-1';

	$GLOBALS['xmlrpcName']='XML-RPC for PHP';
	$GLOBALS['xmlrpcVersion']='3.0.0.beta';

	// let user errors start at 800
	$GLOBALS['xmlrpcerruser']=800;
	// let XML parse errors start at 100
	$GLOBALS['xmlrpcerrxml']=100;

	// formulate backslashes for escaping regexp
	// Not in use anymore since 2.0. Shall we remove it?
	/// @deprecated
	$GLOBALS['xmlrpc_backslash']=chr(92).chr(92);

	// set to TRUE to enable correct decoding of <NIL/> and <EX:NIL/> values
	$GLOBALS['xmlrpc_null_extension']=false;

	// set to TRUE to enable encoding of php NULL values to <EX:NIL/> instead of <NIL/>
	$GLOBALS['xmlrpc_null_apache_encoding']=false;

	// used to store state during parsing
	// quick explanation of components:
	//   ac - used to accumulate values
	//   isf - used to indicate a parsing fault (2) or xmlrpcresp fault (1)
	//   isf_reason - used for storing xmlrpcresp fault string
	//   lv - used to indicate "looking for a value": implements
	//        the logic to allow values with no types to be strings
	//   params - used to store parameters in method calls
	//   method - used to store method name
	//   stack - array with genealogy of xml elements names:
	//           used to validate nesting of xmlrpc elements
	$GLOBALS['_xh']=null;

	/**
	* Convert a string to the correct XML representation in a target charset
	* To help correct communication of non-ascii chars inside strings, regardless
	* of the charset used when sending requests, parsing them, sending responses
	* and parsing responses, an option is to convert all non-ascii chars present in the message
	* into their equivalent 'charset entity'. Charset entities enumerated this way
	* are independent of the charset encoding used to transmit them, and all XML
	* parsers are bound to understand them.
	* Note that in the std case we are not sending a charset encoding mime type
	* along with http headers, so we are bound by RFC 3023 to emit strict us-ascii.
	*
	* @todo do a bit of basic benchmarking (strtr vs. str_replace)
	* @todo	make usage of iconv() or recode_string() or mb_string() where available
	*/
	function xmlrpc_encode_entitites($data, $src_encoding='', $dest_encoding='')
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
				$escaped_data = str_replace(array('&', '"', "'", '<', '>'), array('&amp;', '&quot;', '&apos;', '&lt;', '&gt;'), $data);
				$escaped_data = str_replace($GLOBALS['xml_iso88591_Entities']['in'], $GLOBALS['xml_iso88591_Entities']['out'], $escaped_data);
				break;
			case 'ISO-8859-1_UTF-8':
				$escaped_data = str_replace(array('&', '"', "'", '<', '>'), array('&amp;', '&quot;', '&apos;', '&lt;', '&gt;'), $data);
				$escaped_data = utf8_encode($escaped_data);
				break;
			case 'ISO-8859-1_ISO-8859-1':
			case 'US-ASCII_US-ASCII':
			case 'US-ASCII_UTF-8':
			case 'US-ASCII_':
			case 'US-ASCII_ISO-8859-1':
			case 'UTF-8_UTF-8':
			//case 'CP1252_CP1252':
				$escaped_data = str_replace(array('&', '"', "'", '<', '>'), array('&amp;', '&quot;', '&apos;', '&lt;', '&gt;'), $data);
				break;
			case 'UTF-8_':
			case 'UTF-8_US-ASCII':
			case 'UTF-8_ISO-8859-1':
	// NB: this will choke on invalid UTF-8, going most likely beyond EOF
	$escaped_data = '';
	// be kind to users creating string xmlrpcvals out of different php types
	$data = (string) $data;
	$ns = strlen ($data);
	for ($nn = 0; $nn < $ns; $nn++)
	{
		$ch = $data[$nn];
		$ii = ord($ch);
		//1 7 0bbbbbbb (127)
		if ($ii < 128)
		{
			/// @todo shall we replace this with a (supposedly) faster str_replace?
			switch($ii){
				case 34:
					$escaped_data .= '&quot;';
					break;
				case 38:
					$escaped_data .= '&amp;';
					break;
				case 39:
					$escaped_data .= '&apos;';
					break;
				case 60:
					$escaped_data .= '&lt;';
					break;
				case 62:
					$escaped_data .= '&gt;';
					break;
				default:
					$escaped_data .= $ch;
			} // switch
		}
		//2 11 110bbbbb 10bbbbbb (2047)
		else if ($ii>>5 == 6)
		{
			$b1 = ($ii & 31);
			$ii = ord($data[$nn+1]);
			$b2 = ($ii & 63);
			$ii = ($b1 * 64) + $b2;
			$ent = sprintf ('&#%d;', $ii);
			$escaped_data .= $ent;
			$nn += 1;
		}
		//3 16 1110bbbb 10bbbbbb 10bbbbbb
		else if ($ii>>4 == 14)
		{
			$b1 = ($ii & 15);
			$ii = ord($data[$nn+1]);
			$b2 = ($ii & 63);
			$ii = ord($data[$nn+2]);
			$b3 = ($ii & 63);
			$ii = ((($b1 * 64) + $b2) * 64) + $b3;
			$ent = sprintf ('&#%d;', $ii);
			$escaped_data .= $ent;
			$nn += 2;
		}
		//4 21 11110bbb 10bbbbbb 10bbbbbb 10bbbbbb
		else if ($ii>>3 == 30)
		{
			$b1 = ($ii & 7);
			$ii = ord($data[$nn+1]);
			$b2 = ($ii & 63);
			$ii = ord($data[$nn+2]);
			$b3 = ($ii & 63);
			$ii = ord($data[$nn+3]);
			$b4 = ($ii & 63);
			$ii = ((((($b1 * 64) + $b2) * 64) + $b3) * 64) + $b4;
			$ent = sprintf ('&#%d;', $ii);
			$escaped_data .= $ent;
			$nn += 3;
		}
	}
				break;
/*
			case 'CP1252_':
			case 'CP1252_US-ASCII':
				$escaped_data = str_replace(array('&', '"', "'", '<', '>'), array('&amp;', '&quot;', '&apos;', '&lt;', '&gt;'), $data);
				$escaped_data = str_replace($GLOBALS['xml_iso88591_Entities']['in'], $GLOBALS['xml_iso88591_Entities']['out'], $escaped_data);
				$escaped_data = str_replace($GLOBALS['xml_cp1252_Entities']['in'], $GLOBALS['xml_cp1252_Entities']['out'], $escaped_data);
				break;
			case 'CP1252_UTF-8':
				$escaped_data = str_replace(array('&', '"', "'", '<', '>'), array('&amp;', '&quot;', '&apos;', '&lt;', '&gt;'), $data);
				/// @todo we could use real UTF8 chars here instead of xml entities... (note that utf_8 encode all allone will NOT convert them)
				$escaped_data = str_replace($GLOBALS['xml_cp1252_Entities']['in'], $GLOBALS['xml_cp1252_Entities']['out'], $escaped_data);
				$escaped_data = utf8_encode($escaped_data);
				break;
			case 'CP1252_ISO-8859-1':
				$escaped_data = str_replace(array('&', '"', "'", '<', '>'), array('&amp;', '&quot;', '&apos;', '&lt;', '&gt;'), $data);
				// we might as well replave all funky chars with a '?' here, but we are kind and leave it to the receiving application layer to decide what to do with these weird entities...
				$escaped_data = str_replace($GLOBALS['xml_cp1252_Entities']['in'], $GLOBALS['xml_cp1252_Entities']['out'], $escaped_data);
				break;
*/
			default:
				$escaped_data = '';
				error_log("Converting from $src_encoding to $dest_encoding: not supported...");
		}
		return $escaped_data;
	}

	/// xml parser handler function for opening element tags
	function xmlrpc_se($parser, $name, $attrs, $accept_single_vals=false)
	{
		// if invalid xmlrpc already detected, skip all processing
		if ($GLOBALS['_xh']['isf'] < 2)
		{
			// check for correct element nesting
			// top level element can only be of 2 types
			/// @todo optimization creep: save this check into a bool variable, instead of using count() every time:
			///       there is only a single top level element in xml anyway
			if (count($GLOBALS['_xh']['stack']) == 0)
			{
				if ($name != 'METHODRESPONSE' && $name != 'METHODCALL' && (
					$name != 'VALUE' && !$accept_single_vals))
				{
					$GLOBALS['_xh']['isf'] = 2;
					$GLOBALS['_xh']['isf_reason'] = 'missing top level xmlrpc element';
					return;
				}
				else
				{
					$GLOBALS['_xh']['rt'] = strtolower($name);
					$GLOBALS['_xh']['rt'] = strtolower($name);
				}
			}
			else
			{
				// not top level element: see if parent is OK
				$parent = end($GLOBALS['_xh']['stack']);
				if (!array_key_exists($name, $GLOBALS['xmlrpc_valid_parents']) || !in_array($parent, $GLOBALS['xmlrpc_valid_parents'][$name]))
				{
					$GLOBALS['_xh']['isf'] = 2;
					$GLOBALS['_xh']['isf_reason'] = "xmlrpc element $name cannot be child of $parent";
					return;
				}
			}

			switch($name)
			{
				// optimize for speed switch cases: most common cases first
				case 'VALUE':
					/// @todo we could check for 2 VALUE elements inside a MEMBER or PARAM element
					$GLOBALS['_xh']['vt']='value'; // indicator: no value found yet
					$GLOBALS['_xh']['ac']='';
					$GLOBALS['_xh']['lv']=1;
					$GLOBALS['_xh']['php_class']=null;
					break;
				case 'I4':
				case 'INT':
				case 'STRING':
				case 'BOOLEAN':
				case 'DOUBLE':
				case 'DATETIME.ISO8601':
				case 'BASE64':
					if ($GLOBALS['_xh']['vt']!='value')
					{
						//two data elements inside a value: an error occurred!
						$GLOBALS['_xh']['isf'] = 2;
						$GLOBALS['_xh']['isf_reason'] = "$name element following a {$GLOBALS['_xh']['vt']} element inside a single value";
						return;
					}
					$GLOBALS['_xh']['ac']=''; // reset the accumulator
					break;
				case 'STRUCT':
				case 'ARRAY':
					if ($GLOBALS['_xh']['vt']!='value')
					{
						//two data elements inside a value: an error occurred!
						$GLOBALS['_xh']['isf'] = 2;
						$GLOBALS['_xh']['isf_reason'] = "$name element following a {$GLOBALS['_xh']['vt']} element inside a single value";
						return;
					}
					// create an empty array to hold child values, and push it onto appropriate stack
					$cur_val = array();
					$cur_val['values'] = array();
					$cur_val['type'] = $name;
					// check for out-of-band information to rebuild php objs
					// and in case it is found, save it
					if (@isset($attrs['PHP_CLASS']))
					{
						$cur_val['php_class'] = $attrs['PHP_CLASS'];
					}
					$GLOBALS['_xh']['valuestack'][] = $cur_val;
					$GLOBALS['_xh']['vt']='data'; // be prepared for a data element next
					break;
				case 'DATA':
					if ($GLOBALS['_xh']['vt']!='data')
					{
						//two data elements inside a value: an error occurred!
						$GLOBALS['_xh']['isf'] = 2;
						$GLOBALS['_xh']['isf_reason'] = "found two data elements inside an array element";
						return;
					}
				case 'METHODCALL':
				case 'METHODRESPONSE':
				case 'PARAMS':
					// valid elements that add little to processing
					break;
				case 'METHODNAME':
				case 'NAME':
					/// @todo we could check for 2 NAME elements inside a MEMBER element
					$GLOBALS['_xh']['ac']='';
					break;
				case 'FAULT':
					$GLOBALS['_xh']['isf']=1;
					break;
				case 'MEMBER':
					$GLOBALS['_xh']['valuestack'][count($GLOBALS['_xh']['valuestack'])-1]['name']=''; // set member name to null, in case we do not find in the xml later on
					//$GLOBALS['_xh']['ac']='';
					// Drop trough intentionally
				case 'PARAM':
					// clear value type, so we can check later if no value has been passed for this param/member
					$GLOBALS['_xh']['vt']=null;
					break;
				case 'NIL':
				case 'EX:NIL':
					if ($GLOBALS['xmlrpc_null_extension'])
					{
						if ($GLOBALS['_xh']['vt']!='value')
						{
							//two data elements inside a value: an error occurred!
							$GLOBALS['_xh']['isf'] = 2;
							$GLOBALS['_xh']['isf_reason'] = "$name element following a {$GLOBALS['_xh']['vt']} element inside a single value";
							return;
						}
						$GLOBALS['_xh']['ac']=''; // reset the accumulator
						break;
					}
					// we do not support the <NIL/> extension, so
					// drop through intentionally
				default:
					/// INVALID ELEMENT: RAISE ISF so that it is later recognized!!!
					$GLOBALS['_xh']['isf'] = 2;
					$GLOBALS['_xh']['isf_reason'] = "found not-xmlrpc xml element $name";
					break;
			}

			// Save current element name to stack, to validate nesting
			$GLOBALS['_xh']['stack'][] = $name;

			/// @todo optimization creep: move this inside the big switch() above
			if($name!='VALUE')
			{
				$GLOBALS['_xh']['lv']=0;
			}
		}
	}

	/// Used in decoding xml chunks that might represent single xmlrpc values
	function xmlrpc_se_any($parser, $name, $attrs)
	{
		xmlrpc_se($parser, $name, $attrs, true);
	}

	/// xml parser handler function for close element tags
	function xmlrpc_ee($parser, $name, $rebuild_xmlrpcvals = true)
	{
		if ($GLOBALS['_xh']['isf'] < 2)
		{
			// push this element name from stack
			// NB: if XML validates, correct opening/closing is guaranteed and
			// we do not have to check for $name == $curr_elem.
			// we also checked for proper nesting at start of elements...
			$curr_elem = array_pop($GLOBALS['_xh']['stack']);

			switch($name)
			{
				case 'VALUE':
					// This if() detects if no scalar was inside <VALUE></VALUE>
					if ($GLOBALS['_xh']['vt']=='value')
					{
						$GLOBALS['_xh']['value']=$GLOBALS['_xh']['ac'];
						$GLOBALS['_xh']['vt']=$GLOBALS['xmlrpcString'];
					}

					if ($rebuild_xmlrpcvals)
					{
						// build the xmlrpc val out of the data received, and substitute it
						$temp = new xmlrpcval($GLOBALS['_xh']['value'], $GLOBALS['_xh']['vt']);
						// in case we got info about underlying php class, save it
						// in the object we're rebuilding
						if (isset($GLOBALS['_xh']['php_class']))
							$temp->_php_class = $GLOBALS['_xh']['php_class'];
						// check if we are inside an array or struct:
						// if value just built is inside an array, let's move it into array on the stack
						$vscount = count($GLOBALS['_xh']['valuestack']);
						if ($vscount && $GLOBALS['_xh']['valuestack'][$vscount-1]['type']=='ARRAY')
						{
							$GLOBALS['_xh']['valuestack'][$vscount-1]['values'][] = $temp;
						}
						else
						{
							$GLOBALS['_xh']['value'] = $temp;
						}
					}
					else
					{
						/// @todo this needs to treat correctly php-serialized objects,
						/// since std deserializing is done by php_xmlrpc_decode,
						/// which we will not be calling...
						if (isset($GLOBALS['_xh']['php_class']))
						{
						}

						// check if we are inside an array or struct:
						// if value just built is inside an array, let's move it into array on the stack
						$vscount = count($GLOBALS['_xh']['valuestack']);
						if ($vscount && $GLOBALS['_xh']['valuestack'][$vscount-1]['type']=='ARRAY')
						{
							$GLOBALS['_xh']['valuestack'][$vscount-1]['values'][] = $GLOBALS['_xh']['value'];
						}
					}
					break;
				case 'BOOLEAN':
				case 'I4':
				case 'INT':
				case 'STRING':
				case 'DOUBLE':
				case 'DATETIME.ISO8601':
				case 'BASE64':
					$GLOBALS['_xh']['vt']=strtolower($name);
					/// @todo: optimization creep - remove the if/elseif cycle below
					/// since the case() in which we are already did that
					if ($name=='STRING')
					{
						$GLOBALS['_xh']['value']=$GLOBALS['_xh']['ac'];
					}
					elseif ($name=='DATETIME.ISO8601')
					{
						if (!preg_match('/^[0-9]{8}T[0-9]{2}:[0-9]{2}:[0-9]{2}$/', $GLOBALS['_xh']['ac']))
						{
							error_log('XML-RPC: invalid value received in DATETIME: '.$GLOBALS['_xh']['ac']);
						}
						$GLOBALS['_xh']['vt']=$GLOBALS['xmlrpcDateTime'];
						$GLOBALS['_xh']['value']=$GLOBALS['_xh']['ac'];
					}
					elseif ($name=='BASE64')
					{
						/// @todo check for failure of base64 decoding / catch warnings
						$GLOBALS['_xh']['value']=base64_decode($GLOBALS['_xh']['ac']);
					}
					elseif ($name=='BOOLEAN')
					{
						// special case here: we translate boolean 1 or 0 into PHP
						// constants true or false.
						// Strings 'true' and 'false' are accepted, even though the
						// spec never mentions them (see eg. Blogger api docs)
						// NB: this simple checks helps a lot sanitizing input, ie no
						// security problems around here
						if ($GLOBALS['_xh']['ac']=='1' || strcasecmp($GLOBALS['_xh']['ac'], 'true') == 0)
						{
							$GLOBALS['_xh']['value']=true;
						}
						else
						{
							// log if receiveing something strange, even though we set the value to false anyway
							if ($GLOBALS['_xh']['ac']!='0' && strcasecmp($GLOBALS['_xh']['ac'], 'false') != 0)
								error_log('XML-RPC: invalid value received in BOOLEAN: '.$GLOBALS['_xh']['ac']);
							$GLOBALS['_xh']['value']=false;
						}
					}
					elseif ($name=='DOUBLE')
					{
						// we have a DOUBLE
						// we must check that only 0123456789-.<space> are characters here
						// NOTE: regexp could be much stricter than this...
						if (!preg_match('/^[+-eE0123456789 \t.]+$/', $GLOBALS['_xh']['ac']))
						{
							/// @todo: find a better way of throwing an error than this!
							error_log('XML-RPC: non numeric value received in DOUBLE: '.$GLOBALS['_xh']['ac']);
							$GLOBALS['_xh']['value']='ERROR_NON_NUMERIC_FOUND';
						}
						else
						{
							// it's ok, add it on
							$GLOBALS['_xh']['value']=(double)$GLOBALS['_xh']['ac'];
						}
					}
					else
					{
						// we have an I4/INT
						// we must check that only 0123456789-<space> are characters here
						if (!preg_match('/^[+-]?[0123456789 \t]+$/', $GLOBALS['_xh']['ac']))
						{
							/// @todo find a better way of throwing an error than this!
							error_log('XML-RPC: non numeric value received in INT: '.$GLOBALS['_xh']['ac']);
							$GLOBALS['_xh']['value']='ERROR_NON_NUMERIC_FOUND';
						}
						else
						{
							// it's ok, add it on
							$GLOBALS['_xh']['value']=(int)$GLOBALS['_xh']['ac'];
						}
					}
					//$GLOBALS['_xh']['ac']=''; // is this necessary?
					$GLOBALS['_xh']['lv']=3; // indicate we've found a value
					break;
				case 'NAME':
					$GLOBALS['_xh']['valuestack'][count($GLOBALS['_xh']['valuestack'])-1]['name'] = $GLOBALS['_xh']['ac'];
					break;
				case 'MEMBER':
					//$GLOBALS['_xh']['ac']=''; // is this necessary?
					// add to array in the stack the last element built,
					// unless no VALUE was found
					if ($GLOBALS['_xh']['vt'])
					{
						$vscount = count($GLOBALS['_xh']['valuestack']);
						$GLOBALS['_xh']['valuestack'][$vscount-1]['values'][$GLOBALS['_xh']['valuestack'][$vscount-1]['name']] = $GLOBALS['_xh']['value'];
					} else
						error_log('XML-RPC: missing VALUE inside STRUCT in received xml');
					break;
				case 'DATA':
					//$GLOBALS['_xh']['ac']=''; // is this necessary?
					$GLOBALS['_xh']['vt']=null; // reset this to check for 2 data elements in a row - even if they're empty
					break;
				case 'STRUCT':
				case 'ARRAY':
					// fetch out of stack array of values, and promote it to current value
					$curr_val = array_pop($GLOBALS['_xh']['valuestack']);
					$GLOBALS['_xh']['value'] = $curr_val['values'];
					$GLOBALS['_xh']['vt']=strtolower($name);
					if (isset($curr_val['php_class']))
					{
						$GLOBALS['_xh']['php_class'] = $curr_val['php_class'];
					}
					break;
				case 'PARAM':
					// add to array of params the current value,
					// unless no VALUE was found
					if ($GLOBALS['_xh']['vt'])
					{
						$GLOBALS['_xh']['params'][]=$GLOBALS['_xh']['value'];
						$GLOBALS['_xh']['pt'][]=$GLOBALS['_xh']['vt'];
					}
					else
						error_log('XML-RPC: missing VALUE inside PARAM in received xml');
					break;
				case 'METHODNAME':
					$GLOBALS['_xh']['method']=preg_replace('/^[\n\r\t ]+/', '', $GLOBALS['_xh']['ac']);
					break;
				case 'NIL':
				case 'EX:NIL':
					if ($GLOBALS['xmlrpc_null_extension'])
					{
						$GLOBALS['_xh']['vt']='null';
						$GLOBALS['_xh']['value']=null;
						$GLOBALS['_xh']['lv']=3;
						break;
					}
					// drop through intentionally if nil extension not enabled
				case 'PARAMS':
				case 'FAULT':