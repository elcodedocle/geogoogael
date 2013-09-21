<?php
/**
 * Googael Analytics main controller v0.3 by Gael Abadin.
 *-----------------------------------------------------------------------------
 *  Copyright (C) 2013 Gael Abadin
 * License: MIT Expat (http://github.com/elcodedocle/geogoogael for more info)
 *-----------------------------------------------------------------------------
 */
namespace geogoogael;
class main {   
/* handles all requests, redirecting to proper extension controller */
    public static function main (){
    /* sets autoloader and error handler, does all of the above */
        ob_start();
        set_include_path('..'.PATH_SEPARATOR.get_include_path());
        spl_autoload_extensions(".php");
        spl_autoload_register();
        date_default_timezone_set(appparams::timeZone);
        if (appparams::execTimeout!==-1) {
            set_time_limit (appparams::execTimeout);
        }
        if (appparams::memLimit!=0) {
            ini_set ('memory_limit', appparams::memLimit);
        } 
        register_shutdown_function(
            array(__NAMESPACE__.'\\main',"fatal_error_handler")
        );
        switch (strtoupper($_SERVER['REQUEST_METHOD'])){
            case 'POST':
                $request = 'CREATE';
                $data = $_POST;
                trigger_error("400", E_USER_ERROR);
                break;
            case 'GET':
                $request = 'READ';
                $data = $_GET;
                break;
            case 'PUT':
                $request = 'UPDATE';
                parse_str(file_get_contents('php://input'), $data);
                trigger_error("400", E_USER_ERROR);
                break;
            case 'DELETE':
                $request = 'DELETE';
                $data = null;
                trigger_error("400", E_USER_ERROR);
                break;
            default:
                trigger_error("400", E_USER_ERROR);
        }
        switch ($_GET['f']){
            //obviously this is to make room for future extensions.
            default: 
                $resource_controller = accesslogmapcontroller::process($request, $data) 
                    or trigger_error("500", E_USER_ERROR);
        }
        if (!isset($resource_controller)){
            trigger_error("400", E_USER_ERROR);
        } else {
            if (isset($resource_controller['response_header_HTML_status_code'])){
                $location = $resource_controller['response_header_location']; //may be null
                if (($status_out = 
                    self::send_HTTP_status_code(
                        $resource_controller['response_header_HTML_status_code'],
                        $location)
                ) !== null){
                    /* equivalent to trigger_error(
                     *  $resource_controller['response_header_HTML_status_code'], 
                     *  E_USER_ERROR
                     * ); */
                    ob_end_clean(); 
                    die($status_out);
                }
            }
        }
        ob_end_flush();
    }
    public static function fatal_error_handler () {
    /* registered as error handling function, all app errors go through it */
        $error = error_get_last();
        //error_log ("shit: ".$error['type']);
        if ($error!==null){ 
        //deploy considering mail($admin,$subject,$error['message']); here.
            if ($error['type'] === E_USER_ERROR){
                ob_end_clean();
                //next line commented because xdebug is prettier...
                //error_log(print_r(debug_backtrace(),true));
                die(self::send_HTTP_status_code(intval($error['message'])));
            } elseif (
                ($error['type'] === E_ERROR) ||
                ($error['type'] === E_CORE_ERROR) || 
                ($error['type'] === E_COMPILE_ERROR)
            )
            {
                ob_end_clean();
                die(self::send_HTTP_status_code(500));
            }
        }
    } 
    private static function send_HTTP_status_code($code, $location = null){
        /**
         * HTML views sent with standard HTTP error response codes are taken from CentOS 6.4 default 
         * Apache 2.2.15 install, which relays on Apache MultiViews feature to deliver the response
         * in the client's preferred language (or english if not determined or unavailable).
         * TODO: Implement own multilanguage HTTP status code HTML responses. 
         */
        switch ($code) {
            case 200:
                $message = " 200 OK";
                break;
            case 201:
                $message = " 201 Created";
                break;
            case 302:
                $message = " 302 Found";
                break;
            case 400:
                $message = " 400 Bad Request";
                $filename = "HTTP_BAD_REQUEST.html.var";
                break;
            case 401:
                $message = " 401 Unauthorized";
                $filename = "HTTP_UNAUTHORIZED.html.var";
                break;
            case 403:
                $message = " 403 Forbidden";
                $filename = "HTTP_FORBIDDEN.html.var";
                break;
            case 404:
                $message = " 404 Not Found";
                $filename = "HTTP_NOT_FOUND.html.var";
                break;
            default:
                $message = " 500 Internal Server Error";
                $filename = "HTTP_INTERNAL_SERVER_ERROR.html.var";
                break;
        }
        header($_SERVER["SERVER_PROTOCOL"].$message);
        header("Status:".$message);
        $_SERVER['REDIRECT_STATUS'] = $code;
        if ($code===302) { header("Location: ".$location); }
        if (!isset($filename)){
            return null;
        } else {
            $opts = array(
                'http'=>array(
                    'method'=>"GET",
                    'header'=>"Accept-language: "
                    .$_SERVER['HTTP_ACCEPT_LANGUAGE'] ."\r\n"
                 )
            );
            $context = stream_context_create($opts);
            $s = empty($_SERVER["HTTPS"]) ? 
                '' : ($_SERVER["HTTPS"] == "on") ? "s" : "";
            $sp = strtolower($_SERVER["SERVER_PROTOCOL"]);
            $protocol = substr($sp, 0, strpos($sp, "/")) . $s;
            $port = (
                ($protocol==="http")&&($_SERVER["SERVER_PORT"] == "80")
                ||($protocol==="https")&&($_SERVER["SERVER_PORT"] == "443")
            ) ? "" : (":".$_SERVER["SERVER_PORT"]);
            $address = "$protocol://$_SERVER[SERVER_NAME]$port/"
                .preg_replace(
                    '/(\/)?(.*)/','${2}',
                    dirname($_SERVER['PHP_SELF'])
                )
                ."/lib/error/".$filename;
            return preg_replace (
                "/(Error )\(none\)/",
                '${1}'.$code,
                file_get_contents(
                    $address,
                    false,
                    $context
                )
            );
        }
    }
}
//entry point, the one and only:
main::main();
?>