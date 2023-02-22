
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
			case 'US-ASCII_ISO-8859-1':
			case 'UTF-8_UTF-8':
				$escaped_data = str_replace(array('\\', '"', '/', "\t", "\n", "\r", chr(8), chr(11), chr(12)), array('\\\\', '\"', '\/', '\t', '\n', '\r', '\b', '\v', '\f'), $data);
				break;
			case 'UTF-8_':
			case 'UTF-8_US-ASCII':
			case 'UTF-8_ISO-8859-1':
	// NB: this will choke on invalid UTF-8, going most likely beyond EOF
	$escaped_data = "";
	// be kind to users creating string jsonrpcvals out of different php types
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
				case 8:
					$escaped_data .= '\b';
					break;
				case 9:
					$escaped_data .= '\t';
					break;
				case 10:
					$escaped_data .= '\n';
					break;
				case 11:
					$escaped_data .= '\v';
					break;
				case 12:
					$escaped_data .= '\f';
					break;
				case 13:
					$escaped_data .= '\r';
					break;
				case 34:
					$escaped_data .= '\"';
					break;
				case 47:
					$escaped_data .= '\/';
					break;
				case 92:
					$escaped_data .= '\\\\';
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
			$ent = sprintf ('\u%\'04x', $ii);
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
			$ent = sprintf ('\u%\'04x', $ii);
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
			$ent = sprintf ('\u%\'04x', $ii);
			$escaped_data .= $ent;
			$nn += 3;
		}
	}
				break;
			default:
				$escaped_data = '';
				error_log("Converting from $src_encoding to $dest_encoding: not supported...");
		} // switch
		return $escaped_data;

	/*
		$length = strlen($data);
		$escapeddata = "";
		for($position = 0; $position < $length; $position++)
		{
			$character = substr($data, $position, 1);
			$code = ord($character);
			switch($code)
			{
				case 8:
					$character = '\b';
					break;
				case 9:
					$character = '\t';
					break;
				case 10:
					$character = '\n';
					break;
				case 12:
					$character = '\f';
					break;
				case 13:
					$character = '\r';
					break;
				case 34:
					$character = '\"';
					break;
				case 47:
					$character = '\/';
					break;
				case 92:
					$character = '\\\\';
					break;
				default:
					if($code < 32 || $code > 159)
					{
						$character = "\u".str_pad(dechex($code), 4, '0', STR_PAD_LEFT);
					}
					break;
			}
			$escapeddata .= $character;
		}
		return $escapeddata;
		*/
	}

	/**
	* Parse a JSON string.
	* NB: try to accept any valid string according to ECMA, even though the JSON
	* spec is much more strict.
	* Assumes input is UTF-8...
	* @param string $data a json string
	* @param bool $return_phpvals if true, do not rebuild jsonrpcval objects, but plain php values
	* @param string $src_encoding
	* @param string $dest_encoding
	* @return bool
	* @access private
	* @todo support for other source encodings than UTF-8
	* @todo optimization creep: build elements of arrays/objects asap instead of counting chars many times
	* @todo we should move to xmlrpc_defencoding and xmlrpc_internalencoding as predefined values, but it would make this even slower...
	*       Maybe just move those two parameters outside of here into callers?
	*
	* @bug parsing of "[1]// comment here" works in ie/ff, but not here
	* @bug parsing of "[.1]" works in ie/ff, but not here
	* @bug parsing of "[01]" works in ie/ff, but not here
	* @bug parsing of "{true:1}" works here, but not in ie/ff
	* @bug parsing of "{a b:1}" works here, but not in ie/ff
	*/
	function json_parse($data, $return_phpvals=false, $src_encoding='UTF-8', $dest_encoding='ISO-8859-1')
	{
		// optimization creep: this is quite costly. Is there any better way to achieve it?
		// also note that json does not really allow comments...
		$data = preg_replace(array(
			// eliminate single line comments in '// ...' form
			// REMOVED BECAUSE OF BUGS: 1-does not match at end of non-empty line, 2-eats inside strings, too
			//'#^\s*//(.*)$#m',
			// eliminate multi-line comments in '/* ... */' form, at start of string
			'#^\s*/\*(.*)\*/#Us',
			// eliminate multi-line comments in '/* ... */' form, at end of string
			'#/\*(.*)\*/\s*$#Us'
		), '', $data);

		$data = trim($data); // remove excess whitespace

		if ($data == '')
		{
			$GLOBALS['_xh']['isf_reason'] = 'Invalid data (empty string?)';
			return false;
		}

//echo "Parsing string (".$data.")\n";
		switch($data[0])
		{
			case '"':
			case "'":
				$len = strlen($data);
				// quoted string: check for closing char first
				if ($data[$len-1] == $data[0] && $len > 1)
				{
					// UTF8-decode (or encode) string
					// NB: we MUST do this BEFORE looking for \xNN, \uMMMM or other escape sequences
					if ($src_encoding == 'UTF-8' && ($dest_encoding == 'ISO-8859-1' || $dest_encoding == 'US-ASCII'))
					{
						$data = utf8_decode($data);
						$len = strlen($data);
					}
					else
					{
						if ($dest_encoding == 'UTF-8' && ($src_encoding == 'ISO-8859-1' || $src_encoding == 'US-ASCII'))
						{
							$data = utf8_encode($data);
							$len = strlen($data);
						}
						//else
						//{
						//	$GLOBALS['_xh']['value'] = $GLOBALS['_xh']['ac'];
						//}
					}

					$outdata = '';
					$delim = $data[0];
					for ($i = 1; $i < $len-1; $i++)
					{
						switch($data[$i])
						{
							case '\\':
								if ($i == $len-2)
								{
									break;
								}
								switch($data[$i+1])
								{
									case 'b':
									case 'f':
									case 'n':
									case 'r':
									case 't':
									case 'v':
										$outdata .= $GLOBALS['ecma262_entities'][$data[$i+1]];
										$i++;
										break;
									case 'u':
										// most likely unicode code point
										if ($dest_encoding == 'UTF-8')
										{
											/// @todo see if this is faster / works in all cases
											//$outdata .= utf8_encode(chr(hexdec(substr($data, $i+4, 2))));

											// encode the UTF code point into utf-8...
											$ii = hexdec(substr($data, $i+2, 4));
											if ($ii < 0x80)
											{
												$outdata .= chr($ii);
											}
											else if ($ii <= 0x800)
											{
												$outdata .= chr(0xc0 | $ii >> 6) . chr(0x80 | ($ii & 0x3f));
											}
											else if ($ii <= 0x10000)
											{
												$outdata .= chr(0xe0 | $ii >> 12) . chr(0x80 | ($ii >> 6 & 0x3f)) . chr(0x80 | ($ii & 0x3f));
											}
											else
											{
												$outdata .= chr(0xf0 | $ii >> 20) . chr(0x80 | ($ii >> 12 & 0x3f)) . chr(0x80 | ($ii >> 6 & 0x3f)) . chr(0x80 | ($ii & 0x3f));
											}
											$i += 5;
										}
										else
										{
											// Note: we only decode code points below 256, so we take the last 2 chars of the unicode representation
											$outdata .= chr(hexdec(substr($data, $i+4, 2)));
											$i += 5;
										}
										break;
									case 'x':
										// most likely unicode code point in hexadecimal
										// Note: the json spec omits this case, but ECMA-262 does not...
										if ($dest_encoding == 'UTF-8')
										{
											// encode the UTF code point into utf-8...
											$ii = hexdec(substr($data, $i+2, 2));
											if ($ii < 0x80)
											{
												$outdata .= chr($ii);
											}
											else if ($ii <= 0x800)
											{
												$outdata .= chr(0xc0 | $ii >> 6) . chr(0x80 | ($ii & 0x3f));
											}
											else if ($ii <= 0x10000)
											{
												$outdata .= chr(0xe0 | $ii >> 12) . chr(0x80 | ($ii >> 6 & 0x3f)) . chr(0x80 | ($ii & 0x3f));
											}
											else
											{
												$outdata .= chr(0xf0 | $ii >> 20) . chr(0x80 | ($ii >> 12 & 0x3f)) . chr(0x80 | ($ii >> 6 & 0x3f)) . chr(0x80 | ($ii & 0x3f));
											}
											$i += 3;
										}
										else
										{
											$outdata .= chr(hexdec(substr($data, $i+2, 2)));
											$i += 3;
										}
										break;
									case '0':
									case '1':
									case '2':
									case '3':
									case '4':
									case '5':
									case '6':
									case '7':
									case '8':
									case '9':
										// Note: ECMA-262 forbids these escapes, we just skip it...
										break;
									default:
										// Note: Javascript 1.5 on http://developer.mozilla.org/en/docs/Core_JavaScript_1.5_Guide
										// mentions syntax /XXX with X octal number, but ECMA262
										// explicitly forbids it...
										$outdata .= $data[$i+1];
										$i++;
								} // end of switch on slash char found
								break;
							case $delim:
								// found unquoted end of string in middle of string
								$GLOBALS['_xh']['isf_reason'] = 'Invalid data (unescaped quote char inside string?)';
								return false;
							case "\n":
							case "\r":
								$GLOBALS['_xh']['isf_reason'] = 'Invalid data (line terminator char inside string?)';
								return false;
							default:
								$outdata .= $data[$i];
						}
					} // end of loop on string chars
//echo "Found a string\n";
					$GLOBALS['_xh']['vt'] = 'string';
					$GLOBALS['_xh']['value'] = $outdata;
				}
				else
				{
					// string without a terminating quote
					$GLOBALS['_xh']['isf_reason'] = 'Invalid data (string missing closing quote?)';
					return false;
				}
				break;
			case '[':
			case '{':
				$len = strlen($data);
				// object and array notation: use the same parsing code
				if ($data[0] == '[')
				{
					if ($data[$len-1] != ']')
					{
						// invalid array
						$GLOBALS['_xh']['isf_reason'] = 'Invalid data (array missing closing bracket?)';
						return false;
					}
					$GLOBALS['_xh']['vt'] = 'array';
				}
				else
				{
					if ($data[$len-1] != '}')
					{
						// invalid object
						$GLOBALS['_xh']['isf_reason'] = 'Invalid data (object missing closing bracket?)';
						return false;
					}
					$GLOBALS['_xh']['vt'] = 'struct';
				}

				$data = trim(substr($data, 1, -1));
//echo "Parsing array/obj (".$data.")\n";
				if ($data == '')
				{
					// empty array/object
					$GLOBALS['_xh']['value'] = array();
				}
				else
				{
					$valuestack = array();
					$last = array('type' => 'sl', 'start' => 0);
					$len = strlen($data);
					$value = array();
					$keypos = null;
					//$ac = '';
					$vt = '';
					//$start = 0;
					for ($i = 0; $i <= $len; $i++)
					{
						if ($i == $len || ($data[$i] == ',' && $last['type'] == 'sl'))
						{

							// end of element: push it onto array
							$slice = substr($data, $last['start'], ($i - $last['start']));
							//$slice = trim($slice); useless here, sincewe trim it on sub-elementparsing
//echo "Found slice (".$slice.")\n";

							//$valuestack[] = $last; // necessario ???
							//$last = array('type' => 'sl', 'start' => ($i + 1));
							if ($GLOBALS['_xh']['vt'] == 'array')
							{
								if ($slice == '')
								{
									// 'elided' element: ecma supports it, so do we
									// what should happen here in fact is that
									// "array index is augmented and element is undefined"

									// NOTE: Firefox's js engine does not create
									// trailing undefined elements, while IE does...
									//if ($i < $len)
									//{
										if ($return_phpvals)
										{
											$value[] = null;
										}
										else
										{
											$value[] = new jsonrpcval(null, 'null');
										}
									//}
								}
								else
								{
									if (!json_parse($slice, $return_phpvals, $src_encoding, $dest_encoding))
									{
										return false;
									}
									else
									{
										$value[] = $GLOBALS['_xh']['value'];
										$GLOBALS['_xh']['vt'] = 'array';
									}
								}
							}
							else
							{
								if (!$keypos)
								{
									$GLOBALS['_xh']['isf_reason'] = 'Invalid data (missing object member name?)';
									return false;
								}
								else
								{
									if (!json_parse(substr($data, $last['start'], $keypos-$last['start']), true, $src_encoding, $dest_encoding) ||
										$GLOBALS['_xh']['vt'] != 'string')
									{
										// object member name received unquoted: what to do???
										// be tolerant as much as we can. ecma tolerates numbers as identifiers, too...
										$key = trim(substr($data, $last['start'], $keypos-$last['start']));
									}
									else
									{
										$key = $GLOBALS['_xh']['value'];
									}

//echo "Use extension: $use_extension\n";
									if (!json_parse(substr($data, $keypos+1, $i-$keypos-1), $return_phpvals, $src_encoding, $dest_encoding))
									{
										return false;
									}
									$value[$key] = $GLOBALS['_xh']['value'];
									$GLOBALS['_xh']['vt'] = 'struct';
									$keypos = null;
								}
							}
							$last['start'] = $i + 1;
							$vt = ''; // reset type of val found
						}
						else if ($data[$i] == '"' || $data[$i] == "'")
						{
							// found beginning of string: run till end
							$ok = false;
							for ($j = $i+1; $j < $len; $j++)
							{
								if ($data[$j] == $data[$i])
								{
									$ok = true;
									break;
								}
								else if($data[$j] == '\\')
								{
									$j++;
								}
							}
							if ($ok)
							{
								$i = $j; // advance pointer to end of string
								$vt = 'st';
							}
							else
							{
								$GLOBALS['_xh']['isf_reason'] = 'Invalid data (string missing closing quote?)';
								return false;
							}
						}
						else if ($data[$i] == "[")
						{
							$valuestack[] = $last;
							$last = array('type' => 'ar', 'start' => $i);
						}
						else if ($data[$i] == '{')
						{
							$valuestack[] = $last;
							$last = array('type' => 'ob', 'start' => $i);
						}
						else if ($data[$i] == "]")
						{
							if ($last['type'] == 'ar')
							{
								$last = array_pop($valuestack);
								$vt = 'ar';
							}
							else
							{
								$GLOBALS['_xh']['isf_reason'] = 'Invalid data (unmatched array closing bracket?)';
								return false;
							}
						}
						else if ($data[$i] == '}')
						{
							if ($last['type'] == 'ob')
							{
								$last = array_pop($valuestack);
								$vt = 'ob';
							}
							else
							{
								$GLOBALS['_xh']['isf_reason'] = 'Invalid data (unmatched object closing bracket?)';
								return false;
							}
						}
						else if ($data[$i] == ':' && $last['type'] == 'sl' && !$keypos)
						{
//echo "Found key stop at pos. $i\n";
							$keypos = $i;
						}
						else if ($data[$i] == '/' && $i < $len-1 && $data[$i+1] == "*")
						{
							// found beginning of comment: run till end
							$ok = false;
							for ($j = $i+2; $j < $len-1; $j++)
							{
								if ($data[$j] == '*' && $data[$j+1] == '/')
								{
									$ok = true;
									break;
								}
							}
							if ($ok)
							{
								$i = $j+1; // advance pointer to end of string
							}
							else
							{
								$GLOBALS['_xh']['isf_reason'] = 'Invalid data (comment missing closing tag?)';
								return false;
							}
						}

					}
					$GLOBALS['_xh']['value'] = $value;
				}
				//return true;
				break;
			default:
//echo "Found a scalar val (not string): '$data'\n";
				// be tolerant of uppercase chars in numbers/booleans/null
				$data = strtolower($data);
				if ($data == "true")
				{
//echo "Found a true\n";
					$GLOBALS['_xh']['value'] = true;
					$GLOBALS['_xh']['vt'] = 'boolean';
				}
				else if ($data == "false")
				{
//echo "Found a false\n";
					$GLOBALS['_xh']['value'] = false;
					$GLOBALS['_xh']['vt'] = 'boolean';
				}
				else if ($data == "null")
				{
//echo "Found a null\n";
					$GLOBALS['_xh']['value'] = null;
					$GLOBALS['_xh']['vt'] = 'null';
				}
				// we could use is_numeric here, but rules are slightly different,
				// e.g. 012 is NOT valid according to JSON or ECMA, but browsers inetrpret it as octal
				/// @todo add support for .5
				/// @todo add support for numbers in octal notation, eg. 010
				else if (preg_match("#^-?(0|[1-9][0-9]*)(\.[0-9]*)?([e][+-]?[0-9]+)?$#" ,$data))
				{
					if (preg_match('#[.e]#', $data))
					{
//echo "Found a double\n";
						// floating point
						$GLOBALS['_xh']['value'] = (double)$data;
						$GLOBALS['_xh']['vt'] = 'double';
					}
					else
					{
//echo "Found an int\n";
						//integer
						$GLOBALS['_xh']['value'] = (int)$data;
						$GLOBALS['_xh']['vt'] = 'int';
					}
					//return true;
				}
				else if (preg_match("#^0x[0-9a-f]+$#", $data))
				{
					// int in hex notation: not in JSON, but in ECMA...
					$GLOBALS['_xh']['vt'] = 'int';
					$GLOBALS['_xh']['value'] = hexdec(substr($data, 2));
				}
				else
				{
					$GLOBALS['_xh']['isf_reason'] = 'Invalid data';
					return false;
				}
		} // switch $data[0]

		if (!$return_phpvals)
		{
			$GLOBALS['_xh']['value'] = new jsonrpcval($GLOBALS['_xh']['value'], $GLOBALS['_xh']['vt']);
		}

		return true;

	}

	/**
	* Used in place of json_parse to take advantage of native json decoding when available:
	* it parses either a jsonrpc request or a response.
	* NB: php native decoding of json balks anyway at anything but array / struct as top level element
	* @access private
	* @bug unicode chars are handled differently from this and json_parse...
	* @todo add support for src and dest encoding!!!
	*/
	function json_parse_native($data)
	{
//echo "Parsing string - internal way (".$data.")\n";
		$out = json_decode($data, true);
		if (!is_array($out))
		{
			//$GLOBALS['_xh']['isf'] = 2;
			$GLOBALS['_xh']['isf_reason'] = 'JSON parsing failed';
			return false;
		}
		// decoding will be fine for a jsonrpc error response, so we have to
		// check for it by hand here...
		//else if (array_key_exists('error', $out) && $out['error'] != null)
		//{
		//	$GLOBALS['_xh']['isf'] = 1;
			//$GLOBALS['_xh']['value'] = $out['error'];
		//}
		else
		{
			$GLOBALS['_xh']['value'] = $out;
			return true;
		}
	}

	/**
	* Parse a json string, expected to be jsonrpc request format
	* @access private
	*/
	function jsonrpc_parse_req($data, $return_phpvals=false, $use_extension=false, $src_encoding='')
	{
		$GLOBALS['_xh']['isf']=0;
		$GLOBALS['_xh']['isf_reason']='';
		$GLOBALS['_xh']['pt'] = array();
		if ($return_phpvals && $use_extension)
		{
			$ok = json_parse_native($data);
		}
		else
		{
			$ok = json_parse($data, $return_phpvals, $src_encoding);
		}
		if ($ok)
		{
			if (!$return_phpvals)
				$GLOBALS['_xh']['value'] = @$GLOBALS['_xh']['value']->me['struct'];

			if (!is_array($GLOBALS['_xh']['value']) || !array_key_exists('method', $GLOBALS['_xh']['value'])
				|| !array_key_exists('params', $GLOBALS['_xh']['value']) || !array_key_exists('id', $GLOBALS['_xh']['value']))
			{
				$GLOBALS['_xh']['isf_reason'] = 'JSON parsing did not return correct jsonrpc request object';
				return false;
			}
			else
			{
				$GLOBALS['_xh']['method'] = $GLOBALS['_xh']['value']['method'];
				$GLOBALS['_xh']['params'] = $GLOBALS['_xh']['value']['params'];
				$GLOBALS['_xh']['id'] = $GLOBALS['_xh']['value']['id'];
				if (!$return_phpvals)
				{
					/// @todo we should check for appropriate type for method name and params array...
					$GLOBALS['_xh']['method'] = $GLOBALS['_xh']['method']->scalarval();
					$GLOBALS['_xh']['params'] = $GLOBALS['_xh']['params']->me['array'];
					$GLOBALS['_xh']['id'] = php_jsonrpc_decode($GLOBALS['_xh']['id']);
				}
				else
				{
					// to allow 'phpvals' type servers to work, we need to rebuild $GLOBALS['_xh']['pt'] too
					foreach($GLOBALS['_xh']['params'] as $val)
					{
					    // since we rebuild this after converting json values to php,
					    // we've lost the info about array/struct, and we try to rebuild it
					    /// @bug empty objects will be recognized as empty arrays
					    /// @bug an object with keys '0', '1', ... 'n' will be recognized as an array
					    $typ = gettype($val);
					    if ($typ == 'array' && count($val) && count(array_diff_key($val, array_fill(0, count($val), null))) !== 0)
					    {
    					    $typ = 'object';
					    }
						$GLOBALS['_xh']['pt'][] = php_2_jsonrpc_type($typ);
					}
				}
				return true;
			}
		}
		else
		{
			return false;
		}
	}

	/**
	* Parse a json string, expected to be in json-rpc response format.
	* @access private
	* @todo checks missing:
	*       - no extra members in response
	*       - no extra members in error struct
	*       - resp. ID validation