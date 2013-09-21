<?php
/**
 * Apache fake access_log file generator script v0.5 by Gael Abadin
 *-----------------------------------------------------------------------------
 *  Copyright (C) 2013 Gael Abadin
 * License: MIT Expat (http://github.com/elcodedocle/geogoogael for more info)
 *-----------------------------------------------------------------------------
 *
 * Usage: php -f ".$argv[0]." -- -n <entries_per_process> [-p <number_of_processes>] [-o <output_file>] [-t <from> <to>] [-q <database_parameters>]
 *        php -f ".$argv[0]." -- -r <entries_per_minute> [-o <output_file>] [-q <database_parameters>]
 *        php -f ".$argv[0]." -- -h
 *  *
 * -n <entries_per_process>  Number of entries per process to be generated (ignored in real time mode).
 * -r <entries_per_minute>  Number of entries per minute to be generated IN REAL TIME.
 * -p <number_of_processes>  Number of processes. (Default: 1; ignored in real time mode)
 * -o <output_file>          Output file name. (Default: "access_log")
 * -t <from> <to>            Date range of the generated entries. Date format: "01/Jan/1984:23:57:01 +0000"
 *                           (Default: from 1 second per entry ago to current timestamp; Ignored in real time mode)
 * -q <databaseparameters>   Query database. Format (all parameters optional): 
 *                           "host=<host>;port=<port>;dbname=<dbname>;table=<table>;user=<user>;password=<password>"
 *                           Your database table needs to have IP_RANGE_START,IP_RANGE_END int fields and TIMEZONE field NULL for non-geographical ranges
 *                           If not provided, database password will be requested from command line without echo.
 *
 * Check out the code below for more juicy options to tinker with.
 */

/*****************************************************************************/
/************************** BEGIN CONFIG PARAMETERS **************************/
/*****************************************************************************/

// I know. This got a little out of hand...

$entries = 1000; //(Generated entries per process. Overrided by command line parameter '-n'.)
$real_time = false; //real time mode. Overrided by command line parameter '-r'
$rpm = 60; //average number of entries generated in one minute in real time mode. Overrided by command line parameter '-r'
$num_processes = 1; //total entries = $num_processes * $entries. Overrided by optional command line parameter '-p'
date_default_timezone_set('Europe/Madrid');
$timezone = '+0200';
//$from=time()-$entries*$num_processes; (Overrided by optional command line parameter '-t'. Default can be altered below, after command line args processing.)
$to=time(); //overrided by optional command line parameter '-t'
$filename='access_log'; //overrided by optional command line parameter '-o'
$random_traffic = 1; //0 = fixed steps, 1 = Poisson process (Not exactly, but it'll try :))
$max_visitors_pool_size = 0; // 0 to infinite pool (warning! High mem. usage when combining with large number of entries and high return_chance!)
$return_chance_percentage = -1;//0 visitors will have the same return chance as a new visit, 100 all visitors remain in the pool, -1 randomize for each visitor.
$cached_percentage = 80;//percentage of returning visitors with resources from resource set cached.
$use_appparams = 1; // 1 query database using connection parameters found in ../appparams.php class 
                    // (overrided  by optional command line parameter '-q' value)
$memory_limit = 0; // 0 for default (8M in PHP < 5.2, 16M in PHP 5.2 or 128M in current versions)
$use_ipranges = 0; // 0 for random ips (from database pool if specified), 1 for restricted ranges defined in $ipranges (slow)
$ipranges = array (
    array(
        'from'=>ip2long("0.0.0.0"),
        'to'=>ip2long("255.255.255.255"),
        'weight'=>(ip2long("255.255.255.255")-ip2long("0.0.0.0"))
    )
);
$resources = array( // array indexes are resource names, allowed methods, protocols and response codes. Values are resoponse sizes or cacheable flag and response size array
    'favicon.ico'=>array(
        'GET'=>array(
            'HTTP/1.0'=>array(
                '404'=>315,
            ),
            'HTTP/1.1'=>array(
                '404'=>315,
            )
        )
    ),
    'robots.txt'=>array(
        'GET'=>array(
            'HTTP/1.0'=>array(
                '404'=>314,
            ),
            'HTTP/1.1'=>array(
                '404'=>314,
            )
        )
    ),
    'index.php'=>array(
        'GET'=>array(
            'HTTP/1.0'=>array(
                '200'=>array('cacheable'=>false,'size'=>1090),
                '301'=>320,
                '500'=>10,
            ),
            'HTTP/1.1'=>array(
                '200'=>array('cacheable'=>false,'size'=>1090),
                '301'=>320,
                '500'=>10,
            )
        ),
        'POST'=>array(
            'HTTP/1.0'=>array(
                '200'=>123,
                '500'=>10,
            ),
            'HTTP/1.1'=>array(
                '200'=>123,
                '500'=>10,
            )
        )
    ),
    'page.html'=>array(
        'GET'=>array(
            'HTTP/1.0'=>array(
                '200'=>array('cacheable'=>true,'size'=>47938),
                '301'=>320,
                '500'=>10,
            ),
            'HTTP/1.1'=>array(
                '200'=>array('cacheable'=>true,'size'=>47938),
                '301'=>320,
                '500'=>10,
            )
        )
    ),
    'script.js'=>array(
        'GET'=>array(
            'HTTP/1.0'=>array(
                '200'=>array('cacheable'=>true,'size'=>5430),
                '301'=>320,
                '500'=>10,
            ),
            'HTTP/1.1'=>array(
                '200'=>array('cacheable'=>true,'size'=>5430),
                '301'=>320,
                '500'=>10,
            )
        )
    ),
    'style.css'=>array(
        'GET'=>array(
            'HTTP/1.0'=>array(
                '200'=>array('cacheable'=>true,'size'=>1491),
                '301'=>320,
                '500'=>10,
            ),
            'HTTP/1.1'=>array(
                '200'=>array('cacheable'=>true,'size'=>1491),
                '301'=>320,
                '500'=>10,
            )
        )
    ),
    'image.jpg'=>array(
        'GET'=>array(
            'HTTP/1.0'=>array(
                '200'=>array('cacheable'=>true,'size'=>112332),
                '301'=>320,
                '500'=>10,
            ),
            'HTTP/1.1'=>array(
                '200'=>array('cacheable'=>true,'size'=>112332),
                '301'=>320,
                '500'=>10,
            )
        )
    ),
    'button.png'=>array(
        'GET'=>array(
            'HTTP/1.0'=>array(
                '200'=>array('cacheable'=>true,'size'=>6343),
                '301'=>320,
                '500'=>10,
            ),
            'HTTP/1.1'=>array(
                '200'=>array('cacheable'=>true,'size'=>6343),
                '301'=>320,
                '500'=>10,
            )
        )
    )
);
$resource_sets = array( //order by class: $resource_sets[0] contains class 0 sets, which get more hits than class 1, etc
    array ( // class 0 resource sets (most visited)
        array(
            'index.php'=>$resources['index.php']
        ),
        array(
            'index.php'=>$resources['index.php'],
            'favicon.ico'=>$resources['favicon.ico']
        ),
        array(
            'page.html'=>$resources['page.html'],
            'script.js'=>$resources['script.js'],
            'image.jpg'=>$resources['image.jpg'],
            'style.css'=>$resources['style.css'],
            'button.png'=>$resources['button.png']
        )
    ),
    array( // class 1 resource sets
        array(
            'favicon.ico'=>$resources['favicon.ico'],
            'page.html'=>$resources['page.html'],
            'script.js'=>$resources['script.js'],
            'image.jpg'=>$resources['image.jpg'],
            'style.css'=>$resources['style.css'],
            'button.png'=>$resources['button.png']
        ),
        array(
            'robots.txt'=>$resources['robots.txt']
        )
    ),
    array( // class 2 resource sets
        array(
            'page.html'=>$resources['page.html']
        ),
        array(
            'script.js'=>$resources['script.js']
        ),
        array(
            'image.jpg'=>$resources['image.jpg']
        ),
        array(
            'style.css'=>$resources['style.css']
        )
    ),
    array( //class 3 resource sets
        array(
            'button.png'=>$resources['button.png']
        ),
        array(
            'favicon.ico'=>$resources['favicon.ico']
        )
    )
);

/*****************************************************************************/
/** END CONFIG PARAMETERS (GO BELOW TO TINKER WITH STATISTICAL PARAMETERS) ***/
/*****************************************************************************/

if ($use_appparams===1){
    $query = true;
    require_once '../appparams.php';
    $db['host'] = geogoogael\appparams::dBHost;
    $db['port'] = geogoogael\appparams::dBPort;
    $db['dbname'] = geogoogael\appparams::dBName;
    $db['table'] = geogoogael\appparams::dBTableName;
    $db['user'] = geogoogael\appparams::dBUser;
    $db['password'] = geogoogael\appparams::dBPassword;
} else { $query = false; }

/*****************************************************************************/
/***************** BEGIN COMMAND LINE PARAMETERS PROCESSING ******************/
/*****************************************************************************/

$error=false;
if (php_sapi_name()!=="cli"){
    echo  "This is a command line script.\n Try php -f `script_file_name' from the command line.\n";
    exit;
}
if (!isset($argv[1])){
    echo "Usage: php -f ".$argv[0]." -- -n <entries_per_process> [-p <number_of_processes>] [-o <output_file>] [-t <from> <to>] [-q <database_parameters>]\n";
    echo "       php -f ".$argv[0]." -- -r <entries_per_minute> [-o <output_file>] [-q <database_parameters>]\n";
    echo "       php -f ".$argv[0]." -- --help\n";
    exit;
} elseif (($argv[1]=='-h'||$argv[1]=='--help')&&($argc===2)){
    echo "Apache fake access_log file generator script v0.5 by Gael Abadin\n";
    echo "License: GPLv3 (http://www.gnu.org/licenses/gpl.html)\n";
    echo "This product comes with no warranty.\n";
    echo "\n";
    echo "Usage: php -f ".$argv[0]." -- -n <entries_per_process> [-p <number_of_processes>] [-o <output_file>] [-t <from> <to>] [-q <database_parameters>]\n";
    echo "       php -f ".$argv[0]." -- -r <entries_per_minute> [-o <output_file>] [-q <database_parameters>]\n";
    echo "       php -f ".$argv[0]." -- -h\n";
    echo "\n";
    echo "-n <entries_per_process> Number of entries per process to be generated (ignored in real time mode).\n";
    echo "-r <entries_per_minute>  Number of entries per minute to be generated IN REAL TIME.\n";
    echo "-p <number_of_processes> Number of processes. (Default: 1; ignored in real time mode)\n";
    echo "-o <output_file>         Output file name. (Default: \"access_log\")\n";
    echo "-t <from> <to>           Date range of the generated entries. Date format: \"01/Jan/1984:23:57:01 +0000\"\n";
    echo "                         (Default: from 1 second per entry ago to current timestamp; Ignored in real time mode)\n";
    echo "-q <databaseparameters>  Query database. Format (all parameters optional):\n";
    echo "                         \"host=<host>;port=<port>;dbname=<dbname>;dbtable=<dbtable>;user=<user>;password=<password>\"\n";
    echo "                         Your database table needs to have IP_RANGE_START,IP_RANGE_END int fields and TIMEZONE field NULL for non-geographical ranges\n";
    echo "                         If not provided, database password will be requested from command line without echo.\n";
    echo "\n";
    echo "Check out the code of this script for more juicy options to tinker with.\n";
    echo "\n";
    exit;
} else {
    $i=1;
    while ($i<$argc){
        switch ($argv[$i]){
            case '-n':
                $i++;
                if(isset($argv[$i])){
                    /* test value */
                    if(filter_var($argv[$i],FILTER_VALIDATE_INT)){
                        $entries = $argv[$i];
                    } else { $error = true; }
                } else { $error = true; }
                break;
            case '-r':
                $i++;
                if(isset($argv[$i])){
                    /* test value */
                    if(filter_var($argv[$i],FILTER_VALIDATE_INT)){
                        $rpm = $argv[$i];
                        $real_time = true;
                    } else { $error = true; }
                } else { $error = true; }
                break;
            case '-p':
                $i++;
                if(isset($argv[$i])){
                    /* test value */
                    if(filter_var($argv[$i],FILTER_VALIDATE_INT)){
                        $num_processes = $argv[$i];
                    } else { $error = true; }
                } else { $error = true; }
                break;
            case '-o':
                $i++;
                if(isset($argv[$i])){
                    /* test value */
                    if (preg_match("/^[^\/\?\*:;{}\\\\]+$/",$argv[$i])){
                        $filename = $argv[$i];
                    } else { $error = true; }
                } else { $error = true; }
                break;
            case '-t':
                $i++;
                if(isset($argv[$i])){
                    /* test value */
                    if($from = strtotime($argv[$i])){
                    } else { $error = true; }
                } else { $error = true; break; }
                $i++;
                if(isset($argv[$i])){
                    /* test value */
                    if(($to = strtotime($argv[$i]))
                        &&(($to - $from) > 0)){
                    } else { $error = true; }
                } else { $error = true; }
                break;
            case '-q':
                $query = true;
                $i++;
                if(isset($argv[$i])){
                    /* test value */
                    if (!($db_parameters_string = explode(';',$argv[$i]))){$error = true; break;}
                    foreach($db_parameters_string as $db_parameter_string){
                        if (!($db_parameter_string = explode(';',$argv[$i]))
                            || count($db_parameter_string)!==2)
                        {
                            $error = true; 
                            break;
                        }
                        //db parameters are not tested for proper values, the script will just die on connection or query errors.
                        switch ($db_parameter_string[0]){
                            case 'host':
                                $db['host'] = $db_parameter_string[1];
                                break;
                            case 'port':
                                $db['port'] = $db_parameter_string[1];
                                break;
                            case 'dbname':
                                $db['dbname'] = $db_parameter_string[1];
                                break;
                            case 'table':
                                $db['table'] = $db_parameter_string[1];
                                break;
                            case 'user':
                                $db['user'] = $db_parameter_string[1];
                                break;
                            case 'password':
                                $db['password'] = $db_parameter_string[1];
                                break;
                            default: $error = true;
                        }
                        if ($error){ break; }
                    }
                }
                if (!isset($db['host'])) $db['host']='localhost';
                if (!isset($db['port'])) $db['port']='3306';
                if (!isset($db['dbname'])) $db['dbname']='geogoogael';
                if (!isset($db['table'])) $db['table']='records';
                if (!isset($db['user'])) $db['user']='root';
                if (!isset($db['password'])){
                    echo "Please enter database password: ";
                    $unmute = shell_exec('stty -g');
                    shell_exec('stty -echo');
                    $db['password']=trim(fgets(fopen('php://stdin','r')));
                    shell_exec('stty ' . $unmute);
                }
                break;
            default: $error=true;
        }
        if ($error) { break; }
        $i++;
    }
}
if ($error){
    echo $argv[0].": Invalid syntax.\nTry `php -f ".$argv[0]." -- -h' for more information.\n";
    exit;
}

/*****************************************************************************/
/****************** END COMMAND LINE PARAMETERS PROCESSING *******************/
/*****************************************************************************/

$start=microtime(true);
set_time_limit (0);
if ($memory_limit!==0) ini_set('memory_limit', $memory_limit);

// calculate ip ranges total weight
$ipranges['totalweight'] = 0;
foreach ($ipranges as $range){
    $ipranges['totalweight']+=$range['weight'];
}

// calculate resource set classes weights
$resource_set_classes = array_keys($resource_sets);
$max_class = max($resource_set_classes);
$resource_set_class_weights['total'] = 0;
foreach ($resource_set_classes as $class){
    $resource_set_class_weights[$class]= exp($max_class-$class);
    $resource_set_class_weights['total']+=$resource_set_class_weights[$class];
    //echo "Class ".$class." weight: ".$resource_set_class_weights[$class]."\n";
}
//echo "Sum of classes' weights: ".$resource_set_class_weights['total']."\n";

// calculate average resource_set hits
$average_hits = 0;
foreach ($resource_sets as $class=>$class_resource_sets){
    $resource_set_hits[$class]=0;
    foreach ($class_resource_sets as $resource_set){
        $resource_set_hits[$class] += count($resource_set);
    }
    $average_hits+=($resource_set_hits[$class]/count($class_resource_sets))*($resource_set_class_weights[$class]/$resource_set_class_weights['total']);
}

// some loop constants
$from = $to-$entries*$num_processes;

/*****************************************************************************/
/*********************** BEGIN STATISTICAL PARAMETERS ************************/
/*****************************************************************************/
if ($real_time){
    $average_step=60/$rpm;
} else {
    $average_step=($to-$from)/($entries*$num_processes); //average available time per hit
}
$average_processing_step = min(0.8*$average_step,1); //average time (in seconds) required to process a hit
$processing_lambda = 1/$average_processing_step; //lambda parameter for exp distribution
$processing_tau = 12*$average_processing_step; //maximum time required to process a hit
$average_time_between_visits = max($average_step-$average_hits*$average_processing_step, 0.2*$average_step*$average_hits);
$lambda = ($average_time_between_visits>0)?(1/$average_time_between_visits):null;
$tau = 12*$average_time_between_visits;
/*****************************************************************************/
/************************ END STATISTICAL PARAMETERS *************************/
/*****************************************************************************/


// ip pool initialization
$ips = array();

// timestamp initializiation
$timestamp=$from;
$ftimestamp = $timestamp;

if ($real_time){
    //create access_log file to append data, overwrite if exists
    $fh=fopen($filename,'w');
} else {
    // create shared memory block for sharing $ftimestamp between child processes
    $shm_key = ftok($argv[0], 'f');
    $shm_id = shmop_open($shm_key, 'c', 0666, $shm_size = 2097152);
    $shm_header_size=256;
    $offset = $shm_header_size; //for $ftimestamp and $offset
    $shm_bytes_written = shmop_write($shm_id, serialize($ftimestamp)."\0".serialize($offset)."\0", 0);
    
    // create or empty output file if exists
    $fh=fopen($filename,'w');
    fclose($fh);
    
    // create and get semaphore handler
    $sem_id = sem_get(ftok($filename, 'f'), 1, 0666, -1);
}
/*****************************************************************************/
/************************* SPAWN CHILD PROCESSES HERE ************************/
/*****************************************************************************/
$parent = true; $parent_pid=posix_getpid(); $num_childs=0;
while ($parent&&$num_childs<$num_processes) {
    $parent = pcntl_fork();
    $num_childs++;
    if ($real_time) { $microtime=microtime(true); $microlastwrite=$microtime; break; }
}
if (!$parent){ //child
    pcntl_sigprocmask(SIG_BLOCK, array(SIGUSR1));
    $pid=posix_getpid();
    $num_processes = 1;
    //process visits
    $i=0;$entry="";$sleep=0;
    do{ //while ($i<$entries)

        //select resource set
        $resource_set_cummulative_weight=mt_rand(0,$resource_set_class_weights['total']);
        $cummulative_weight = 0;
        $resource_set_class=max($resource_set_classes);
        foreach ($resource_set_classes as $class){
            if($resource_set_cummulative_weight<=$cummulative_weight) {
                $resource_set_class = $class;
                break;
            } else {
                $cummulative_weight+=$resource_set_class_weights[$class];
            }
        }
        $resource_set = $resource_sets[$resource_set_class][array_rand($resource_sets[$resource_set_class])];

        //select ip
        if (($max_visitors_pool_size===0||(count($ips)<$max_visitors_pool_size))&&mt_rand(0,count($ips))===0){ 
            $increase_pool = true; 
        } else{
            // try to get value from pool
            $ipsi=array_rand($ips);
            if (mt_rand(1,100)>$ips[$ipsi]['return_chance']){
                // entry is leaving the pool
                unset($ips[$ipsi]);
                $increase_pool = true;
            } else {
                $ip = $ipsi;
                $increase_pool = false;
            }
        }
        if ($increase_pool){
            if ($use_ipranges){
                $range_cummulative_weight=mt_rand(0,$ipranges['totalweight']);
                $cummulative_weight = 0;
                foreach ($ipranges as $range){
                    if($range_cummulative_weight<=$cummulative_weight) {
                        $iprange = $range;
                        break;
                    } else {
                        $iprange = $range;
                        $cummulative_weight+=$range['weight'];
                    }
                }
                $ip=mt_rand($iprange['from'],$iprange['to']);
            } else {
                if ($query){ 
                /*
                 * This is an efficient equivalent of "SELECT * FROM `".$table."` ORDER BY RAND() LIMIT 1;", 
                 * much better than my "SELECT * FROM `".$table."` LIMIT ".mt_rand(0,$num_rows-1).",1" 
                 * Thanks to http://jan.kneschke.de
                 */
                    $sql = "
    SELECT r1.`IP_RANGE_START`, r1.`IP_RANGE_END`, r1.`TIMEZONE` FROM `".$db['table']."` AS r1 
    JOIN(
        SELECT (RAND() * (
            SELECT MAX(`IP_RANGE_START`) 
            FROM `".$db['table']."`)) 
        AS `IP_RANGE_START`
    ) AS r2
    WHERE r1.`IP_RANGE_START` >= r2.`IP_RANGE_START` AND r1.`TIMEZONE` IS NOT NULL
    ORDER BY r1.`IP_RANGE_START` ASC
    LIMIT 1
    ";
                    try {
                        $dbh = new PDO('mysql:host='.$db['host'].';port='.$db['port'].';dbname='.$db['dbname'].';charset=utf8',
                            $db['user'], $db['password']) or die("database connection error.") or die("Error: Cannot connect to database.\n");
                        if ($stmt = $dbh->query($sql)){
                            if ($row=$stmt->fetch()){
                                $ip = mt_rand($row['IP_RANGE_START'],$row['IP_RANGE_END']);
                            } else die("Error: cannot fetch database query result.\nQuery: ".$sql."\n");
                        } else die("MySQL error. Query: ".$sql."\n");
                    } catch (PDOException $e) {
                        die("PDO exception: ".$e->getMessage()."\nQuery: ".$sql."\nError code: ".$dbh->errorCode()."\nError message: ".$dbh->errorInfo()."\n");
                    }
                } else {
                    $ip = mt_rand(0,ip2long("255.255.255.255"));
                }
            }
            $return_chance = ($return_chance_percentage >= 0)?$return_chance_percentage:mt_rand(0,100);
            $ips[$ip]=array('return_chance'=>$return_chance, 'visited_resources'=>array());
        }
     
        //Time between visits follows an exponential distribution with parameter $lambda = 1/$average_time_between_visits truncated at $tau.
        //More info: "Teoria de Colas y Simulacion de Eventos Discretos" Ed. Pearson/Prentice Hall 2003, Chapter 3
        $resource_set_step = ($random_traffic===1)?(
            - log(1 - (1 - exp(-$lambda*$tau)) * (mt_rand(0,PHP_INT_MAX)/PHP_INT_MAX)) * $average_time_between_visits
        ):0;
        
        //process hits for selected resource_set
        foreach ($resource_set as $ri=>$resource){
            $step = $resource_set_step;
            $resource_set_step = 0;
            $method=array_rand($resources[$ri]);
            $protocol=array_rand($resources[$ri][$method]);
            $header = array_rand($resources[$ri][$method][$protocol]);
            if (isset($resources[$ri][$method][$protocol][$header]['cacheable'])){ 
                if (($resources[$ri][$method][$protocol][$header]['cacheable']===true) 
                    && $increase_pool===false 
                    && array_key_exists($ri,$ips[$ip]['visited_resources']) 
                    && (mt_rand(1,100)<=$cached_percentage))
                {
                    $header = 304;
                    $size = '-';
                } else {
                    $size = $resources[$ri][$method][$protocol][$header]['size'];
                }
            } else {
                $size = $resources[$ri][$method][$protocol][$header];
            }
            $step += ($random_traffic===1)?(
                //processing time follows an exponential distribution with parameter $processing_lambda truncated in $processing_tau
                - log(1 - (1 - exp(-$processing_lambda*$processing_tau)) * (mt_rand(0,PHP_INT_MAX)/PHP_INT_MAX)) * $average_processing_step
            ):$average_step;
            if($real_time){
                $entry .= long2ip($ip)
                    .' - - ['
                    .date("d/M/Y:H:i:s",time())
                    .' '.$timezone.'] "'.$method.' /'.$ri.' '.$protocol.'" '.$header.' '.$size."\n";
                $microstart = $microtime;
                /** BEGIN WRITE LOG ENTRY **/
                if (($microtime = microtime(true))-$microlastwrite > 0.2){
                    fwrite($fh,$entry);
                    $entry="";
                    $microtime = microtime(true);
                    $microlastwrite = $microtime;
                }
                /*** END WRITE LOG ENTRY ***/
                if (($sleep += $step - ($microtime-$microstart))>1){
                    usleep(round(1000000*$sleep));
                    $sleep=0;
                }
                $i=0; //tweak to ignore $entries limit
            /*********************************************************************/
            /*************************** BEGIN MUTEX HERE ************************/
            /*********************************************************************/
            } elseif (sem_acquire($sem_id)){
                //read shared memory
                $shm_data = shmop_read($shm_id, 0, $shm_header_size);
                $ftimestamp = unserialize(substr($shm_data,0,$offsetoffset = strpos($shm_data,"\0")));
                $offset = unserialize(substr($shm_data,$offsetoffset+1,strpos($shm_data,"\0",$offsetoffset+1)));
                
                $timestamp=min(round($ftimestamp),$to);
                $entry = long2ip($ip)
                    .' - - ['
                    .date("d/M/Y:H:i:s",$timestamp)
                    .' '.$timezone.'] "'.$method.' /'.$ri.' '.$protocol.'" '.$header.' '.$size."\n";
                if ($offset+strlen($entry)>$shm_size){
                    //send SIGUSR1 signal to parent and stop until signal is received back
                    $shm_bytes_written = shmop_write($shm_id, serialize($ftimestamp)."\0".serialize($offset)."\0".serialize($pid)."\0", 0);
                    posix_kill($parent_pid,SIGUSR1);
                    echo $pid.": waiting for parent...\n";
                    pcntl_sigwaitinfo(array(SIGUSR1), $info);
                    echo $pid.": resuming\n";
                    $offset = $shm_header_size;
                }
                $ftimestamp+=$step; 
                //write shared memory
                $shm_bytes_written = shmop_write($shm_id, $entry, $offset);
                $offset+=$shm_bytes_written;
                $shm_bytes_written = shmop_write($shm_id, serialize($ftimestamp)."\0".serialize($offset)."\0", 0);
                sem_release($sem_id);
            } else {
                die("Couldn't acquire semaphore.\n");
            }
            /*********************************************************************/
            /**************************** END MUTEX HERE *************************/
            /*********************************************************************/
            $i++;
            if ($i == $entries) { break; }
        }
        
    } while ($i<$entries);
    echo "Child ".$num_childs." ";
} elseif(!$real_time) { //parent, non real time
    $fh=fopen($filename,'w');
    pcntl_sigprocmask(SIG_BLOCK, array(SIGCHLD,SIGUSR1));
    while ($num_childs>0) {
        pcntl_sigwaitinfo(array(SIGCHLD,SIGUSR1), $info);
        if ($info['signo']===SIGCHLD) {
            //a child has finished its execution
            $num_childs--;
        } 
        if ($info['signo']===SIGUSR1||$num_childs===0){
            //shared memory buffer is full: copy buffer to string, resume and dump to file.
            $shm_data = shmop_read($shm_id, 0, $shm_header_size);
            $ftimestamp = unserialize(substr($shm_data,0,$offsetoffset = strpos($shm_data,"\0")));
            $offset = unserialize(substr($shm_data,$offsetoffset+1,$pidoffset = strpos($shm_data,"\0",$offsetoffset+1)));
            if ($info['signo']===SIGUSR1) {
                $pid = unserialize(substr($shm_data,$pidoffset+1,strpos($shm_data,"\0",$pidoffset+1)));
            }
            $shm_data = shmop_read($shm_id, $shm_header_size, $offset-$shm_header_size);
            //reset offset
            $offset = $shm_header_size;
            $shm_bytes_written = shmop_write($shm_id,serialize($ftimestamp)."\0".serialize($offset)."\0",0);
            //send signal to halted child to restart child processes
            if ($info['signo']===SIGUSR1){
                echo "parent: resuming ".$pid."\n";
                posix_kill($pid,SIGUSR1);
            }
            //write string with copied buffer to file
            fwrite($fh,$shm_data);
        }
    }
    fclose($fh);
    shmop_delete($shm_id);
    shmop_close($shm_id);
    sem_remove($sem_id);
} else { //parent, real time. $parent contains child PID
    $unmute = shell_exec('stty -g');
    shell_exec('stty -echo cbreak');
    $fp = fopen('php://stdin', 'r');
    echo "Starting apache access log generator in real time generation mode.\nPress q to exit.\n";
    while ('q' !== ($char = fgetc($fp))) { echo "Press q to exit.\n"; }
    shell_exec('stty ' . $unmute);
    posix_kill($parent,SIGKILL);
    pcntl_wait($status);
    fclose($fh);
}
$time = microtime(true)-$start;
echo "Execution time: ".$time." seconds (".($real_time?("Aprox. ".($rpm/60)):($entries*$num_processes/$time))." entries per second).\nMemory: ".memory_get_peak_usage()." bytes\n";
?>