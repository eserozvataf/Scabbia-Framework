<?php

	class repository {
		private static $packageKey = null;
		private static $packages = array();

		public static function extension_info() {
			return array(
				'name' => 'repository',
				'version' => '1.0.2',
				'phpversion' => '5.1.0',
				'fwversion' => '1.0',
				'enabled' => true,
				'autoevents' => false,
				'depends' => array('string', 'http', 'database')
			);
		}
		
		public static function extension_load() {
			if(COMPILED) {
				Events::register('run', Events::Callback('repository::run'));
			}

			foreach(Config::get('/repository/packageList', array()) as $tPackage) {
				self::$packages[$tPackage['@name']] = array();

				foreach($tPackage['fileList'] as &$tFile) {
					self::$packages[$tPackage['@name']][] = Framework::translatePath($tFile['@path']);
				}
			}
		}

		public static function run() {
			$tCheckKey = Config::get('/repository/routing/@repositoryCheckKey', 'rep');
			$tCheckValue = Config::get('/repository/routing/@repositoryCheckValue', '');
			$tPackageKey = Config::get('/repository/routing/@repositoryPackageKey', 'rep');

			if(array_key_exists($tCheckKey, $_GET)) {
				if(strlen($tCheckValue) == 0) {
					self::$packageKey = $_GET[$tPackageKey];
				}
				else if($_GET[$tCheckKey] == $tCheckValue) {
					self::$packageKey = $_GET[$tPackageKey];
				}
			}

			if(isset(self::$packageKey)) {
				if(array_key_exists(self::$packageKey, self::$packages)) {
					Framework::printFiles(self::$packages[self::$packageKey]);

					// to interrupt event-chain execution
					return false;
				}
			}
		}

		public static function getPackageKey() {
			return self::$packageKey;
		}
	}

?>
