<?php
/**
 * accessLogMapController class v0.4
 *-----------------------------------------------------------------------------
 *  Copyright (C) 2013 Gael Abadin
 * License: MIT Expat (http://github.com/elcodedocle/geogoogael for more info)
 *-----------------------------------------------------------------------------
 *
 * Extends main resource controller.
 * 
 * if (!isset($_GET['requestAndProcessPageJSONData'])):
 * Returns a view that implements AJAX callbacks to the controller
 * 
 * if (isset($_GET['requestAndProcessPageJSONData'])):
 * Processes this calls through a bunch of request processors 
 * (input validator, log parser, DB query and output text) returning a JSON 
 * string with the output to be presented by the view.
 * 
 * In other words: you will get an HTML5 doc containing a google map with 
 * markers, a nice zoomable timeline chart with three (3) granularity 
 * options, and a beautiful table with field sorting capabilities :D
 */
namespace geogoogael;
class accesslogmapcontroller {
    private static function filterInputData($data){
        if (!isset($data['sessionTimeout'])
            ||(($session_timeout = filter_var(
                $data['sessionTimeout'],
                FILTER_VALIDATE_INT
            )) === false) //must be a valid int
            ||$session_timeout<0){ //but not negative
            $session_timeout=0; //otherwise set to default value
        }
        if (!isset($data['pageSize']) //must be set
            ||($page_size = filter_var(
                $data['pageSize'], 
                FILTER_VALIDATE_INT
            )) === false //must be a valid int
            ||$page_size<appparams::minPageSize //must be at least this
            ||$page_size>appparams::maxPageSize){ //but not crazy high
            $page_size=appparams::defaultPageSize; //otherwise set to default
        }
        if (!isset($data['page']) //must be set
            ||($page = filter_var(
                $data['page'], 
                FILTER_VALIDATE_INT
            )) === false //must be a valid int
            ||$page!==-1  //must be either -1 (for requesting last page)
            &&$page<1    //or positive
            ||$page>(PHP_INT_MAX/(32*$page_size))){ //but not crazy high
            $page=1; //otherwise set to default value
        }
        $filtered['page'] = $page;
        $filtered['session_timeout'] = $session_timeout;
        $filtered['page_size'] = $page_size;
        return $filtered;
    }
    public static function process($method, $data){
        $start = microtime(true);
        //validate input params (session timeout, page, visits per page)
        $filteredData=self::filterInputData($data);
        if ($method!=='READ') {trigger_error("400", E_USER_ERROR);}
        if (!isset($_GET['requestAndProcessPageJSONData'])) {
            accesslogmapview::getTemplate($filteredData);
            return array(
                "response_header_HTML_status_code"=>200,
                "response_header_location"=>null
            );
        } 
        //else we are in an AJAX call:
        $list = null; //passed by reference because it can be big...
        //parse log file
        $parser = new accesslogparser(
            appparams::logLocation,
            $filteredData['session_timeout'],
            appparams::maxProcessedEntries
        );
        $parser->parsePage(
            $filteredData['page'],
            $filteredData['page_size'],
            $list
        );
        //query db for location info on parsed entries
        $dB = new db();
        $dB->query($list);
        if (appparams::maskIPs){
            $salt = mt_rand(0,PHP_INT_MAX);
            foreach ($list['ips'] as &$value){
                //should I be using double hashing here?
                $value=hash('sha256',$salt.$value);
            }
        }
        //add output text
        languages\highchartstxt::setText($list);
        languages\datatablestxt::setText($list);
        $list['count'] = count($list['ips']);
        $list['pageSize'] = $filteredData['page_size'];
        $list['sessionTimeout'] = $filteredData['session_timeout'];
        $list['page'] = $filteredData['page'];
        $list['execTime'] = microtime(true)-$start;
        //output all in a json string
        echo json_encode($list);//Efficient? Nope, nie, not, nein... Who knows!
        return array(
            "response_header_HTML_status_code"=>200,
            "response_header_location"=>null
        );
    } // end public static function process($method, $data)
}
?>
