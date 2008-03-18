<?php
/**
 * 
 * @author shacky@poczta.fm
 * @copyright shacky@poczta.fm
 * @license SPL
 * @version 0.1
 * @package apps-twistergame
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

class Apps_TwisterGameInstall extends ModuleInstall {

	public function install() {
		return true;
	}
	
	public function uninstall() {
		return true;
	}
	
	public function version() {
		return array("0.1");
	}
	
	public function requires($v) {
		return array();
	}
	
	public static function info() {
		return array(
			'Description'=>'',
			'Author'=>'shacky@poczta.fm',
			'License'=>'SPL');
	}
	
	public static function simple_setup() {
		return true;
	}
	
}

?>