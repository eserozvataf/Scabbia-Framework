<?php
/**
 * Scabbia Framework Version 1.1
 * http://larukedi.github.com/Scabbia-Framework/
 * Eser Ozvataf, eser@sent.com
 */

namespace Scabbia;

use Scabbia\Config;
use Scabbia\Events;
use Scabbia\Extensions;
use Scabbia\Io;

/**
 * Methods for essential framework functionality.
 *
 * @package Scabbia
 * @version 1.1.0
 *
 * @todo determine application before apppath, get apppath from application's itself
 * @todo completely independent architecture allows no-application, maybe same with $readonly?
 * @todo Request abstract classes attached to Framework (will be derived CliRequest, HttpRequest etc.)
 * @todo Response abstract classes attached to Framework (will be derived HttpResponse, CliResponse etc.)
 * @todo HttpResponse might have OutputAdapter (Html, Xml, Json, PDF, DownloadFile-Binary etc.) and OutputEncoding
 */
class Framework
{
    /**
     * @var string  Scabbia Framework's version
     */
    const VERSION = '1.1';

    /**
     * @var object  Composer's class loader
     */
    public static $classLoader = null;
    /**
     * @var object  Application instance
     */
    public static $application = null;
    /**
     * @var int     Indicates framework is running in production, development or debug mode
     */
    public static $development = 0;
    /**
     * @var bool    Indicates framework is running in readonly mode or not
     */
    public static $readonly = true;
    /**
     * @var int     The timestamp indicates when the request started
     */
    public static $timestamp = null;
    /**
     * @var string  Indicates the base directory which framework runs in
     */
    public static $basepath = null;
    /**
     * @var string  Indicates the core directory which framework runs in
     */
    public static $corepath = null;
    /**
     * @var string  Indicates the vendor directory which dependencies can be found at
     */
    public static $vendorpath = null;
    /**
     * @var string  Stores relative path of running application
     */
    public static $apppath = null;
    /**
     * @var string  Stores relative path of framework root
     */
    public static $siteroot = null;
    /**
     * @var array   The milestones passed in code
     */
    public static $milestones = array();
    /**
     * @var int     The exit status
     */
    public static $exitStatus = null;


    /**
     * Initializes the framework.
     *
     * @param object|null $uClassLoader composer's class loader
     *
     * @throws \Exception
     */
    public static function load($uClassLoader = null)
    {
        self::$timestamp = microtime(true);
        self::$milestones[] = array('begin', self::$timestamp);

        self::$classLoader = $uClassLoader;

        // Set internal encoding
        mb_internal_encoding('UTF-8');

        // Set error reporting occasions
        error_reporting(defined('E_STRICT') ? E_ALL | E_STRICT : E_ALL);

        if (is_null(self::$basepath)) {
            self::$basepath = strtr(
                pathinfo($_SERVER['SCRIPT_FILENAME'], PATHINFO_DIRNAME),
                DIRECTORY_SEPARATOR,
                '/'
            ) . '/';
        }
        self::$corepath = strtr(realpath(__DIR__ . '/../../'), DIRECTORY_SEPARATOR, '/') . '/';
        self::$vendorpath = self::$basepath . 'vendor/';
    }

    /**
     * Determines application by endpoint.
     *
     * @param array $uEndpoints set of endpoints
     * @param bool  $uReadonly  run in readonly mode
     *
     * @return null|mixed selected application
     */
    public static function runApplicationByEndpoint(array $uEndpoints, $uReadonly = false)
    {
        foreach ($uEndpoints as $tEndpointKey => $tEndpointAddresses) {
            foreach ((array)$tEndpointAddresses as $tEndpointAddress) {
                $tParsed = parse_url($tEndpointAddress);
                if (!isset($tParsed['port'])) {
                    $tParsed['port'] = ($tParsed['scheme'] == 'https') ? 443 : 80;
                }

                if ($_SERVER['SERVER_NAME'] == $tParsed['host'] && $_SERVER['SERVER_PORT'] == $tParsed['port']) {
                    return self::runApplication(new $tEndpointKey (), $uReadonly);
                }
            }
        }

        return false;
    }

    /**
     * Invokes the startup methods for framework extensions and runs an application instance.
     *
     * @param Application $uApplication application instance currently running on
     * @param bool        $uReadonly    run in readonly mode
     *
     * @return bool whether other party is called or not
     */
    public static function runApplication(Application $uApplication, $uReadonly = false)
    {
        self::$application = $uApplication;
        self::$apppath = self::$basepath . self::$application->directory;
        self::$readonly = $uReadonly;

        if (!is_null(self::$classLoader)) {
            self::$classLoader->set(self::$application->name, self::$apppath);
        }

        self::run();

        // run extensions
        $tParms = array(
            'onerror' => $uApplication->onError
        );
        Events::invoke('pre-run', $tParms);
        self::$milestones[] = array('extensionsPreRun', microtime(true));

        foreach ($uApplication->callbacks as $tCallback) {
            $tReturn = call_user_func($tCallback);

            if (!is_null($tReturn) && $tReturn === true) {
                break;
            }
        }

        if (!is_null($uApplication->otherwise) && !isset($tReturn) || $tReturn !== true) {
            call_user_func($uApplication->otherwise);
            return false;
        }

        return true;
    }

    /**
     * Invokes the startup methods just for framework extensions so other parties can take over execution.
     */
    public static function run()
    {
        // load config
        Config::$default = Config::load();
        self::$milestones[] = array('configLoad', microtime(true));

        // download files
        foreach (Config::get('downloadList', array()) as $tUrl) {
            Io::downloadFile($tUrl['filename'], $tUrl['url']);
        }
        self::$milestones[] = array('downloads', microtime(true));

        // load extensions
        Extensions::load();
        self::$milestones[] = array('extensions', microtime(true));

        // siteroot
        if (is_null(self::$siteroot)) {
            self::$siteroot = trim(Config::get('options/siteroot', pathinfo($_SERVER['SCRIPT_NAME'], PATHINFO_DIRNAME)), '/');
            if (strlen(self::$siteroot) > 0) {
                self::$siteroot = '/' . self::$siteroot;
            }
        }
        Utils::addVariable('root', self::$siteroot);
        self::$milestones[] = array('siteRoot', microtime(true));

        // include files
        foreach (Config::get('includeList', array()) as $tInclude) {
            $tIncludePath = pathinfo(Io::translatePath($tInclude));

            $tFiles = Io::glob($tIncludePath['dirname'] . '/', $tIncludePath['basename'], Io::GLOB_FILES);
            if ($tFiles !== false) {
                foreach ($tFiles as $tFilename) {
                    //! todo require_once?
                    include $tFilename;
                }
            }
        }
        self::$milestones[] = array('includesLoad', microtime(true));

        // loadClass classes
        foreach (Config::get('loadClassList', array()) as $tClass) {
            class_exists($tClass, true);
        }
        self::$milestones[] = array('loadClassLoad', microtime(true));

        // output handling
        ob_start('Scabbia\\Framework::output');
        ob_implicit_flush(false);
    }

    /**
     * Output callback method which will be called when the output buffer
     * is flushed at the end of the request.
     *
     * @param string    $uValue     the generated content
     * @param int       $uStatus    the status of the output buffer
     *
     * @return string final content
     */
    public static function output($uValue, $uStatus)
    {
        $tParms = array(
            'exitStatus' => &self::$exitStatus,
            'content' => &$uValue
        );

        Events::invoke('output', $tParms);

        if (ini_get('output_handler') == '') {
            $tParms['content'] = mb_output_handler(
                $tParms['content'],
                $uStatus
            ); // PHP_OUTPUT_HANDLER_START | PHP_OUTPUT_HANDLER_END

            if (!ini_get('zlib.output_compression') &&
                (PHP_SAPI != 'cli') &&
                Config::get('options/gzip', true) === true) {
                $tParms['content'] = ob_gzhandler(
                    $tParms['content'],
                    $uStatus
                ); // PHP_OUTPUT_HANDLER_START | PHP_OUTPUT_HANDLER_END
            }
        }

        return $tParms['content'];
    }

    /**
     * Terminates the execution of the framework.
     *
     * @param int       $uLevel         the exit status (0-254)
     * @param string    $uErrorMessage  the error message if available
     */
    public static function end($uLevel = 0, $uErrorMessage = null)
    {
        self::$exitStatus = array($uLevel, $uErrorMessage);
        ob_end_flush();

        exit($uLevel);
    }

    /**
     * Prints milestones.
     */
    public static function printMilestones()
    {
        $tPrevious = Framework::$timestamp;

        foreach (Framework::$milestones as $tMilestone) {
            echo $tMilestone[0], ' = ', number_format($tMilestone[1] - $tPrevious, 5), ' ms.<br />';
            $tPrevious = $tMilestone[1];
        }

        echo '<b>total</b> = ', number_format($tPrevious - Framework::$timestamp, 5), ' ms.<br />';
    }
}
