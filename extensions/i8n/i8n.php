<?php

if(extensions::isSelected('i8n')) {
	/**
	* I8N Extension
	*
	* @package Scabbia
	* @subpackage i8n
	* @version 1.0.2
	*
	* @scabbia-fwversion 1.0
	* @scabbia-fwdepends
	* @scabbia-phpversion 5.2.0
	* @scabbia-phpdepends mbstring
	*
	* @todo translitIt
	* @todo intl extension methods
	*/
	class i8n {
		/**
		* @ignore
		*/
		public static $languages = array();
		/**
		* @ignore
		*/
		public static $language = null;

		/**
		* @ignore
		*/
		public static function extension_info() {
			return array(
				'name' => 'i8n',
				'version' => '1.0.2',
				'phpversion' => '5.2.0',
				'phpdepends' => array('mbstring'),
				'fwversion' => '1.0',
				'fwdepends' => array()
			);
		}

		/**
		* @ignore
		*/
		public static function extension_load() {
			foreach(config::get(config::MAIN, '/i8n/languageList', array()) as $tLanguage) {
				self::$languages[$tLanguage['id']] = array(
					'key' => $tLanguage['id'],
					'locale' => $tLanguage['locale'],
					// 'localewin' => $tLanguage['@localewin'],
					'internalEncoding' => $tLanguage['internalEncoding'],
					'name' => $tLanguage['name']
				);
				//! temporarily solution for kibristayim.com project.
				if(is_null(self::$language)) {
					self::setLanguage($tLanguage['id']);
				}
			}
		}

		/**
		* @ignore
		*/
		public static function setLanguage($uLanguage, $uLastChoice = false) {
			if(array_key_exists($uLanguage, self::$languages)) {
				self::$language = &self::$languages[$uLanguage];
			}
			else if($uLastChoice) {
				$tExploded = explode('-', $uLanguage, 2);

				if(array_key_exists($tExploded[0], self::$languages)) {
					self::$language = &self::$languages[$tExploded[0]];
				}
			}

			if(!is_null(self::$language)) {
				// if(PHP_OS_WINDOWS) {
				// 	$tLocale = explode('.', self::$language['localewin'], 2);
				// }
				// else {
					$tLocale = explode('.', self::$language['locale'], 2);
				// }

				$tLocale['all'] = implode('.', $tLocale);

				// putenv('LC_ALL=' . $tLocale[0]);
				if(!PHP_SAFEMODE) {
					putenv('LANG=' . $tLocale[0]);
				}
				$tTest = setlocale(LC_ALL, $tLocale[0]);

				mb_internal_encoding(self::$language['internalEncoding']);
				mb_http_output(self::$language['internalEncoding']);

				bindtextdomain('core', QPATH_CORE . 'locale');
				bind_textdomain_codeset('core', self::$language['internalEncoding']);

				bindtextdomain('application', framework::$applicationPath . 'locale');
				bind_textdomain_codeset('application', self::$language['internalEncoding']);

				textdomain('application');

				return true;
			}

			return false;
		}
	}
}

?>