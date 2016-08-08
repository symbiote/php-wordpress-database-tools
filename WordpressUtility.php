<?php

class WordpressHTMLAttribute {
	public $name = '';
	public $value = '';
}

class WordpressUtility {
	/**
	 * json_last_error_msg is PHP 5.5+
	 * Need to support PHP 5.3+
	 */
	protected static $json_error_msg = array(
		JSON_ERROR_NONE => 'No errors',
		JSON_ERROR_DEPTH => 'Maximum stack depth exceeded',
		JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
		JSON_ERROR_CTRL_CHAR => 'Unexpected control character found',
		JSON_ERROR_SYNTAX => 'Syntax error',
		JSON_ERROR_UTF8 => 'Malformed UTF-8 characters, possibly incorrectly encoded',
		/*JSON_ERROR_RECURSION => 'One or more recursive references in the value to be encoded',
		JSON_ERROR_RECURSION => 'One or more recursive references in the value to be encoded',*/
	);

	public static function json_last_error_msg() {
		$json_error_msg = 'Unknown error';
		if (function_exists('json_last_error_msg')) {
			$json_error_msg = json_last_error_msg();
		} else {
			$json_error_code = json_last_error();
			if (isset(static::$json_error_msg[$json_error_code])) {
				$json_error_msg = static::$json_error_msg[$json_error_code];
			}
		}
		return $json_error_msg;
	}

	public static function modify_html_attributes($html, $function) {
		// Ensure that \r (carriage return) characters don't get replaced with "&#13;" entity by DOMDocument
		// This behaviour is apparently XML spec, but we don't want this because it messes up the HTML
		$htmlFixed = str_replace(chr(13), '', $html);

		$doc = new DOMDocument('1.0', 'UTF-8');
		$doc->strictErrorChecking = false;
		$doc->formatOutput = false;
		$errorState = libxml_use_internal_errors(true);
		$doc->loadHTML(
			'<html><head><meta http-equiv="content-type" content="text/html; charset=utf-8"></head>' .
			'<body>'.$htmlFixed.'</body></html>'
		);
		libxml_clear_errors();
		libxml_use_internal_errors($errorState);
		$xp = new DOMXPath($doc);

		// If there's no body, the content is empty string (ie. no attributes to loop over)
		if (!$doc->getElementsByTagName('body')->length) {
			return $html;
		}

		// saveHTML Percentage-encodes any URI-based attributes. We don't want this, since it interferes with
		// shortcodes. So first, save all the attribute values for later restoration.
		$attrs = array(); $i = 0;
		foreach ($xp->query('//body//@*') as $attr) {
			$key = "__HTMLVALUE_".($i++);
			$htmlAttr = new WordpressHTMLAttribute;
			$htmlAttr->name = $attr->name;
			$htmlAttr->value = $attr->value;
			$attrs[$key] = $htmlAttr;
			$attr->value = $key;
		}

		$oldAttrsSerialized = serialize($attrs);
		$function($attrs);

		// If no attributes were modified, return HTML, unmodified
		$hasChanged = ($oldAttrsSerialized !== serialize($attrs));
		if (!$hasChanged) {
			return $html;
		}

		// Then, call saveHTML & extract out the content from the body tag
		$res = preg_replace(
			array(
				'/^(.*?)<body>/is',
				'/<\/body>(.*?)$/isD',
			),
			'',
			$doc->saveHTML()
		);

		// Then replace the saved attributes with their original versions
		$res = preg_replace_callback('/__HTMLVALUE_(\d+)/', function($matches) use ($attrs) {
			return htmlspecialchars($attrs[$matches[0]]->value, ENT_QUOTES, 'UTF-8');
		}, $res);
		return $res;
	}

	/**
	 * Encodes an array of data to be UTF8 over using html entities
	 * 
	 * @var array
	 */
	public static function utf8_json_encode($arr, $options = 0, $depth = 512) {
		// NOTE(Jake): Might be able to get more speed out of this (if need be) by making it just json_encode
		//			   and if it fails with JSON_ERROR_UTF8, then do the recursive walk
		$utf8_arr = $arr;
		array_walk_recursive($utf8_arr, array(__CLASS__, '_utf8_json_encode_recursive'));
		$result = json_encode($utf8_arr, $options, $depth);
		return $result;
	}
	public static function _utf8_json_encode_recursive(&$item, $key) {
	    $item = \ForceUTF8\Encoding::toUTF8($item);
	}
}