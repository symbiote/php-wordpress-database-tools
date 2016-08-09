<?php

class WordpressFilter {
	public function __construct() {
		$THIRDPARTY_DIR = dirname(__FILE__).DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR.'thirdparty'.DIRECTORY_SEPARATOR;
		// Include Wordpress formatting
		include_once($THIRDPARTY_DIR.'wordpress'.DIRECTORY_SEPARATOR.'wp-includes'.DIRECTORY_SEPARATOR.'formatting.php');
		// Include ForceUTF8 formatting
		include_once($THIRDPARTY_DIR.'forceutf8'.DIRECTORY_SEPARATOR.'src'.DIRECTORY_SEPARATOR.'ForceUTF8'.DIRECTORY_SEPARATOR.'Encoding.php');
	}

	public function post_title($value) {
		// Fix funky UTF-8 encoding
		$value = \ForceUTF8\Encoding::toUTF8($value);
		$value = htmlspecialchars_decode($value);
		return $value;
	}

	public function post_content($value) {
		$value = \ForceUTF8\Encoding::toUTF8($value);
		$value = wpautop($value);
		return $value;
	}
}