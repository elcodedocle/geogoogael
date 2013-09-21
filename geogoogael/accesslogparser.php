<?php
/**
 * accessLogParser class v0.4
 *-----------------------------------------------------------------------------
 *  Copyright (C) 2013 Gael Abadin
 * License: MIT Expat (http://github.com/elcodedocle/geogoogael for more info)
 *-----------------------------------------------------------------------------
 * 
 * It parses. An access log.
 * 
 * Be sure it's an Apache access log and httpd.conf LogFormat directive is
 * set to default or"%h *whatever* %t", meaning the first thing in a log entry 
 * must be the host (IPv4), then comes the timestamp, like [01/Jan/2001 +01:00]
 * 
 * sessionTimeout determines for how long in seconds entries with 
 * the same ip are discarded as duplicates.
 */
namespace geogoogael;
class accesslogparser {
    private $activeSessions = array();
    private $sessionTimeout;
    private $maxEntries;
    private $filename;
    private $entriesProcessed = 0;
    public function __construct($filename, 
                                $session_timeout = 0, 
                                $max = 0){
        //get memory limit for setting access log file parser chunk size
        $memory_limit_in_bytes = trim(ini_get('memory_limit'));
        switch(strtolower(
            $memory_limit_in_bytes[strlen($memory_limit_in_bytes)-1])) {
            case 'g': $memory_limit_in_bytes *= 1024;
            case 'm': $memory_limit_in_bytes *= 1024;
            case 'k': $memory_limit_in_bytes *= 1024;
        }
        $this->memoryLimit = $memory_limit_in_bytes;
        $this->filename = $filename;
        $this->sessionTimeout = $session_timeout;
        $this->maxEntries = $max; 
    }
    private function parseIPAndTimeStamp(&$chunk,&$list){
        /** 
         * returns a timestamp string, formatted [17/Aug/2013:20:56:47 +0200] 
         * if found after IP on $line string
         */
        $pattern = "/^(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})". //ipv4 (aprox.)
                   ".*\[".//forward till open bracket is found
                   "(\d{1,2}\/([A-Z][a-z]{1,8}|d{1,2})\/\d{4,4}". //date
                   ":\d{1,2}:\d{1,2}:\d{1,2} \+\d{4,4})\]/m"; //time,timezone
        $matched = preg_match_all($pattern, $chunk, $matches);
        if ($matched) {
            if ($this->maxEntries>0){
                $matches_count = count($matches[1]);
                $this->entriesProcessed+=$matches_count;
                if ($this->entriesProcessed>$this->maxEntries){
                    error_log("Request exceeds maximum allowed amount of processed entries.");
                    array_splice($matches[1],0,
                        $this->entriesProcessed-$this->maxEntries);
                    array_splice($matches[2],0,
                        $this->entriesProcessed-$this->maxEntries);
                    if (count($matches[1])===0) { return; }
                }
            }
            foreach ($matches[2] as $index=>$timestamp){
                $chunkUnixTimeStamps[]=strtotime($timestamp);
            }
            $list['ips'] = array_merge($matches[1],$list['ips']);
            $list['timestamps'] = array_merge($matches[2],$list['timestamps']);
            $list['unixtimestamps'] = array_merge($chunkUnixTimeStamps,
                                                    $list['unixtimestamps']);
            if ($this->sessionTimeout>0) {
                 $this->unique_visits($list); 
            }
        }
    }
    private function unique_visits(&$list){
        /*error_log(microtime(true).
        ': Entering unique_visits(&$list,$timeout) with count($list["ips"]) = '.
        count($list['ips']));*/
        $index = null;
        for($index = count($list['ips'])-1;$index>=0;$index--){
            //Sorry.
            if (
                isset($this->activeSessions[$list['ips'][$index]])
                && 
                (
                    (
                        $this->activeSessions[$list['ips'][$index]]
                        -$list['unixtimestamps'][$index]
                    )
                    < $this->sessionTimeout
                )
            )
            {
                //update first hit for that session
                $this->activeSessions[$list['ips'][$index]] = 
                    $list['unixtimestamps'][$index];
                //eliminate entry belonging to an already recorded session
                unset($list['ips'][$index]);
                unset($list['timestamps'][$index]);
                unset($list['unixtimestamps'][$index]);
            } else {
                //update first hit for that session
                $this->activeSessions[$list['ips'][$index]] = 
                    $list['unixtimestamps'][$index];
            }
        }
        //compact list arrays to eliminate unset holes. Not very efficient.
        $list['ips']=array_values($list['ips']);
        $list['timestamps']=array_values($list['timestamps']);
        $list['unixtimestamps']=array_values($list['unixtimestamps']);
        /** 
         * In order to re-search sessionTimeout seconds after first
         * entry of this chunk when next chunk comes, we keep those values.
         */
        asort($this->activeSessions);
        $lastunixtimestamp=reset($this->activeSessions)+$this->sessionTimeout;
        $i=0; 
        foreach ($this->activeSessions as $unixtimestamp){
            if ($unixtimestamp>$lastunixtimestamp){ break; }
            $i++;
        }
        array_splice($this->activeSessions, $i);
        /*error_log(microtime(true).
        ': Leaving unique_visits(&$list,$timeout) with count($list["ips"]) = '
        .count($list['ips']). ' $i='.$i.' count($list->activeSessions)='.count($this->activeSessions));*/
    }
    public function parsePage(&$page, $page_size,  &$list){
        $chunk_size = min(round($this->memoryLimit/32), $page_size*512);
        $list['ips'] = array();
        $list['timestamps'] = array();
        $list['unixtimestamps'] = array();
        $fileHandler = fopen($this->filename, 'r') 
            or trigger_error("501", E_USER_ERROR);
        $truncated = false;
        (string) $truncatedLine = null;
        $count = 0;
        $lastchunk = false;$chunk_number=1;
        /**
         * The log file is read in chunks backwards, from last to first chunk 
         * so we can retrieve and process recent chunks faster than old ones. 
         * (It's not that I haven't heard of logrotate. I'm crazy this way.)
         *
         */
        if ($lastchunk = (fseek($fileHandler,-$chunk_size,SEEK_END)<0)){
                rewind($fileHandler);
        }
        if ($page==-1) {$get_last = true; $previous_last_page = 0;} 
        else {$get_last=false;}
        //The following `while` is just a speedup. Remove in case of confusion.
        while (($count<$page*$page_size)||$get_last){
            $chunk = fread($fileHandler,$chunk_size);
            $chunk .= $truncatedLine;
            if (!$lastchunk){
                $truncatedLine = substr($chunk,0,strpos($chunk,"\n")+1);
                $chunk = substr($chunk,strpos($chunk,"\n")+1);
            }
            $last_count=substr_count($chunk, "\n");
            $count+=$last_count;
            $last_page = ceil($count/$page_size);
            if($get_last){
                if ($last_page>$previous_last_page){
                    $last_page_chunk_cur = ftell($fileHandler) - $chunk_size;
                    $last_page_truncated_line=$truncatedLine;
                    $last_page_count=$count-$last_count;
                    $previous_last_page = $last_page;
                    $last_page_chunk_size = $chunk_size;
                }
                if ($lastchunk){
                    fseek($fileHandler,$last_page_chunk_cur, SEEK_SET);
                    $chunk = fread($fileHandler,$last_page_chunk_size);
                    $chunk.=$last_page_truncated_line;
                    $count = $last_page_count;
                    if ($last_page_chunk_cur>0){
                        $truncatedLine = 
                            substr($chunk,0,strpos($chunk,"\n")+1);
                        $chunk = substr($chunk,strpos($chunk,"\n")+1);
                    }
                    break;
                }
            } elseif ($last_page>=$page) {
                $count-=$last_count;
                break;
            }
            if ($lastchunk) {
                break;
            } else {
                if ($lastchunk = 
                    (fseek($fileHandler,-2*$chunk_size,SEEK_CUR)<0)){
                    $chunk_size = ftell($fileHandler)-$chunk_size;
                    rewind($fileHandler);
                }
            }
        }
        while (($count<$page*$page_size)||$get_last){
            //Unless the page is splited between two chunks or
            //atrociously huge this should never need more than one pass.
            $last_list['ips'] = $list['ips'];
            $last_list['timestamps'] = $list['timestamps'];
            $last_list['unixtimestamps'] = $list['unixtimestamps'];
            $list['ips']=array();
            $list['timestamps']=array();
            $list['unixtimestamps']=array();
            $last_chunk_visits = count($list['ips']);
            $visits = 0;
            do {
                //Unless the page is atrociously huge this should never need 
                //more than one pass.
                $this->parseIPAndTimeStamp($chunk, $list);
                $visits+=count($list['ips']);
                if ($lastchunk || 
                    ($this->maxEntries>0) && 
                    ($this->entriesProcessed>=$this->maxEntries)) {
                    break;
                }
                if ($visits-$last_chunk_visits<$page_size) {
                    if ($lastchunk = 
                        (fseek($fileHandler,-2*$chunk_size,SEEK_CUR)<0)){
                        $chunk_size = ftell($fh)-$chunk_size;
                        rewind($fileHandler);
                    }
                }
            } while ($visits-$last_chunk_visits<$page_size);
            $count+=$visits; //increase count and process next chunk
            if(!$get_last&&($count<($page-1)*$page_size)) {
                //Not the page we're looking for.
                //(The flow will only get here if the optimization
                //loop before the current `while` is removed)
                $list['ips']=array();
                $list['timestamps']=array();
                $list['unixtimestamps']=array();
            } else {
                $last_page = ceil($count/$page_size);
                if ($get_last) { $page = $last_page; }
                $first_page = ceil(($count-$visits+1)/$page_size);
                if ($first_page<$page){
                    $partial_page_offset = $page_size 
                                         - ($count-$visits)%$page_size;
                    $full_pages_offset = (
                                            $page
                                           -ceil(($count-$visits+1)/$page_size)
                                           -1
                                       ) * $page_size;
                    $offset = $partial_page_offset + $full_pages_offset;
                    //remove page_size-(count-visits)%page_size offset 
                    //and full pages at the end of our chunk
                    array_splice($list['ips'], -$offset);
                    array_splice($list['timestamps'], -$offset);
                    array_splice($list['unixtimestamps'], -$offset);
                }
                if ($last_page>$page){
                    $partial_page_offset = $count%$page_size;
                    $full_pages_offset = (floor($count/$page_size)-$page)
                                        *$page_size;
                    $offset = $partial_page_offset + $full_pages_offset;
                    //remove count%page_size offset and full pages 
                    //at the beginning of our chunk
                    array_splice($list['ips'],0,$offset);
                    array_splice($list['timestamps'],0,$offset);
                    array_splice($list['unixtimestamps'],0,$offset);
                }
                $list['ips']=array_merge($list['ips'],$last_list['ips']);
                $list['timestamps']=array_merge($list['timestamps'],
                                                $last_list['timestamps']);
                $list['unixtimestamps']=array_merge($list['unixtimestamps'],
                                                $last_list['unixtimestamps']);
            }
            if (($this->maxEntries>0) && 
                ($this->entriesProcessed>$this->maxEntries) &&
                (count($list['ips'])<$page_size)){
                trigger_error("400", E_USER_ERROR);
            }
            if ($lastchunk){
                break; 
            } else {
                if ($lastchunk = 
                    (fseek($fileHandler,-2*$chunk_size,SEEK_CUR)<0)){
                    $chunk_size = ftell($fh)-$chunk_size;
                    rewind($fileHandler);
                }
            }
            $chunk = fread($fileHandler,$chunk_size);
            //append first line of previous chunk to this chunk.
            $chunk .= $truncatedLine;
            if (!$lastchunk){
                //strip first line of this chunk, because it may be truncated.
                //(why the fuck have I spent one fucking hour looking for a
                //more efficient way to do this and found fucking nothing?)
                $truncatedLine = substr($chunk,0,strpos($chunk,"\n")+1);
                $chunk = substr($chunk,strpos($chunk,"\n")+1);
            }
        }
        fclose($fileHandler);
    }
}
?>