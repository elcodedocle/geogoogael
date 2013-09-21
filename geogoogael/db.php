<?php
/**
 * db access class v0.3
 *-----------------------------------------------------------------------------
 *  Copyright (C) 2013 Gael Abadin
 * License: MIT Expat (http://github.com/elcodedocle/geogoogael for more info)
 *-----------------------------------------------------------------------------
 * 
 * Designed to work with ip2location free database, available for download at 
 * http://lite.ip2location.com/database-ip-country-region-city-latitude-longitude-zipcode-timezone
 * 
 * MySQL PDO driver module required.
 * 
 * Enable MySQL query cache in my.cnf and set query cache size > 0 or all 
 * queries will last forever! 
 */
namespace geogoogael;
use \PDO;
class db {
    private $dbh = null;
    public function __construct(){
        try {
            $this->dbh = new PDO(
                "mysql:host=".appparams::dBHost.
                ";port=".appparams::dBPort.
                ";dbname=".appparams::dBName.
                ";charset=utf8",
                appparams::dBUser,
                appparams::dBPassword, 
                array(
                    PDO::ATTR_PERSISTENT => true,
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8'",
                    PDO::MYSQL_ATTR_USE_BUFFERED_QUERY => true
                )
            );
            $this->dbh->setAttribute(PDO::ATTR_EMULATE_PREPARES,false);
            $this->dbh->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            trigger_error("501", E_USER_ERROR);
        }
        return true;
    }
    public function query(&$list, $max=0){
        $valid_count=0;
        $count = count($list['ips']);
        $list['matches'] = array();
        for ($i=0;$i<$count;$i++) {
            $ip2long = ip2long($list['ips'][$i]);
            /* You are about to see this weird WHERE clause equivalent of 
             * 'WHERE $ip2long BETWEEN IP_RANGE_START AND IP_RANGE_END' for 
             * using index fseek instead of sequential index fscan when 
             * matching the non-overlapped unique IP ranges stored on this 
             * database. More info: http://goo.gl/1mOy7C
             */ 
            $sql = "SELECT * 
                    FROM `".appparams::dBTableName."`
                    WHERE `IP_RANGE_START` =
                          ( SELECT MAX(`IP_RANGE_START`) 
                            FROM `".appparams::dBTableName."`
                            WHERE `IP_RANGE_START` <= $ip2long
                          )
                      AND $ip2long <= `IP_RANGE_END` ;";
            $stmt=$this->dbh->query($sql);
            if ($row=$stmt->fetch(PDO::FETCH_ASSOC)){
                $list['matches'][] = $row;
                if ($max>0) { $count++; if ($count>=$max) { break; } }
            } else {
                // no idea how we could end up here, but just in case.
                $list['matches'][] = array(
                    'IP_RANGE_START'=>null, 'IP_RANGE_END'=>null, 
                    'ISO-639-1'=>null, 
                    'COUNTRY_NAME'=>null, 'REGION_NAME'=>null, 
                    'CITY_NAME'=>null, 
                    'LATITUDE'=>null,'LONGITUDE'=>null, 
                    'AREA_CODE'=>null,'TIMEZONE'=>null
                );
                error_log("Unable to fetch a database match for ".
                            "$ip2long = ip2long(".$list['ips'][$i].')');
            }
        }
    }
}
?>