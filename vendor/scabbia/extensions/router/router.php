<?php

	namespace Scabbia;

	/**
	 * Router Extension
	 *
	 * @package Scabbia
	 * @subpackage router
	 * @version 1.0.5
	 *
	 * @scabbia-fwversion 1.0
	 * @scabbia-fwdepends http
	 * @scabbia-phpversion 5.2.0
	 * @scabbia-phpdepends
	 */
	class router {
		/**
		 * @ignore
		 */
		public static $routes = array();

		/**
		 * @ignore
		 */
		public static function extensionLoad() {
			foreach(config::get('/router/routeList', array()) as $tRouteList) {
				self::add($tRouteList['match'], $tRouteList['callback']);
			}
		}

		/**
		 * @ignore
		 */
		public static function run($uParms) {
			if(extensions::isLoaded('profiler')) {
				profiler::start('http', array('action' => 'routing'));
			}

			$tParms = array(
				'queryString' => &http::$queryString,
				'get' => &$_GET
			);

			foreach(self::$routes as $tRoute) {
				if(!is_null($tRoute[2]) && !in_array(http::$methodext, $tRoute[2])) {
					continue;
				}

				$tMatches = framework::pregMatch(ltrim($tRoute[0], '/'), http::$queryString);
				if(count($tMatches) > 0) {
					$tRouteToCall = $tRoute[1];
					break;
				}
			}

			if(isset($tRouteToCall)) {
				call_user_func($tRouteToCall, $tMatches);
			}
			else {
				events::invoke('httpRoute', $tParms);
			}

			if(extensions::isLoaded('profiler')) {
				profiler::stop();
			}
		}

		/**
		 * @ignore
		 */
		public static function add($uMatch, $uMethod) {
			if(!is_array($uMatch)) {
				$uMatch = array($uMatch);
			}

			foreach($uMatch as $tMatch) {
				$tParts = explode(' ', $tMatch, 2);

				$tLimitMethods = ((count($tParts) > 1) ? explode(',', strtolower(array_shift($tParts))) : null);

				self::$routes[] = array($tParts[0], $uMethod, $tLimitMethods);
			}
		}
	}

	?>