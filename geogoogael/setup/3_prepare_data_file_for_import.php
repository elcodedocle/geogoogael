<?php
    function prepare_data_file_for_import($buffer_size = 524288){
        $ifh = fopen("IP2LOCATION-LITE-DB11.CSV", "r");
        $ofh = fopen("database.csv", "w");
        $cRFlag = false;
        (string) $chunk = null;
        (string) $previous_chunk = null;
        while ($chunk=fread($ifh,$buffer_size)){
            $cRFlag = (($chunk[0] === "\n") && ($previous_chunk[strlen($previous_chunk)-1]==="\r"));
            if($cRFlag) $previous_chunk = substr($previous_chunk, 0, -1);
            $previous_chunk=preg_replace(array("/\"-\"/","/\\r\\n/"),array("NULL","\n"),$previous_chunk);
            fwrite($ofh,$previous_chunk);
            $previous_chunk = $chunk;
        }
        $chunk=preg_replace(array("/\"-\"/","/\\r\\n/"),array("NULL","\n"),$chunk);
        fwrite($ofh,$chunk);
        fclose($ifh);
        fclose($ofh);
    }
?>