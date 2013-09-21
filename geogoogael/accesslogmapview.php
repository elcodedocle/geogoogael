<?php
/**
 * accessLogMapView class v0.4
 *-----------------------------------------------------------------------------
 *  Copyright (C) 2013 Gael Abadin
 * License: MIT Expat (http://github.com/elcodedocle/geogoogael for more info)
 *-----------------------------------------------------------------------------
 * 
 * It's an HTML5 view!
 * 
 * It imports the proper client functions to implement AJAX calls to get the 
 * required data from the server and present it in a fancy HTML5 doc, that's 
 * all. (Ok, so maybe it's not a view. Leave me alone, I'm an engineer, not 
 * an expert :P)
 * 
 */
namespace geogoogael;
class accesslogmapview {
    public static function getTemplate($filteredData){
?><!doctype html>
<html>
<!-- Geolocator v0.4: Apache access log location pinpoint in a google map. 
 Copyright (C) 2013 Gael Abadin -->
    <head>
        <meta name="viewport" content="initial-scale=1.0, user-scalable=no">
        <meta charset="UTF-8" />
        <title>
            <?=htmlentities(_("Geolocator v0.4 by Gael Abadin - Apache access log location pinpoint in a google map"));?>
        </title>
        <link href="style/style.css" rel="stylesheet" type="text/css" />
        <script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/2.0.3/jquery.min.js"></script> 
        <script type="text/javascript" src="//ajax.aspnetcdn.com/ajax/jquery.validate/1.11.1/jquery.validate.min.js"></script> 
        <script type="text/javascript" src="//maps.googleapis.com/maps/api/js?key=<?= appparams::googleMapsApiKey ?>&amp;v=3.exp&amp;sensor=false"></script>
        <script type="text/javascript" src="//code.highcharts.com/highcharts.js"></script>
        <script type="text/javascript" src="//ajax.aspnetcdn.com/ajax/jquery.dataTables/1.9.4/jquery.dataTables.min.js"></script>
        <script type="text/javascript" src="lib/TableTools.min.js"></script>
        <script type="text/javascript" src="style/DataTablesOnBootstrap.js"></script>
        <script type="text/javascript" src="lib/jquery.tooltipster.min.js"></script>
        <script type="text/javascript" src="viewHelperFunctions/fireGoogleMaps.js"></script>
        <script type="text/javascript" src="viewHelperFunctions/highChart.js"></script>
        <script type="text/javascript" src="viewHelperFunctions/dataTable.js"></script>
        <script type="text/javascript" src="viewHelperFunctions/accesslogmapview.js"></script>
    </head>
    <body>
        <div class="container">
            <div id="preAjaxContent">
                <p>
                    <?= htmlentities(_("Querying your access_log file entries in geolocation database."));?>
                    <br />
                    <?= htmlentities(_("It may take a few minutes to search all entries. Please be patient.")); ?>
                    <br />
                    <?= htmlentities(_("It is highly recommended that you enable and set the 
                    query cache size to avoid overloading your database server 
                    and speed future queries up to just a few microseconds.")); ?>
                    <br />
                    <?= htmlentities(_("Processing")); ?><span id="dots"></span>
                </p>
            </div>
            <div id="postAjaxContent" style="display:none;">
                <div class="Flexible-container">
                    <div class="page-header" id="header">
                        <h1>
                            <?=htmlentities(_("This site was visited from the following IPs/locations"));?>
                            <br />
                            <small id="subtitle">
                                <?=htmlentities(_("(Showing "));?><span id="visitsCount"></span>
                                <?=htmlentities(_(" visits on page "));?><span id="pageNumber"></span><?=htmlentities(_(", starting on record "));?>
                                <span id="firstRecord"></span>
                                <?=htmlentities(_(" from last record)"));?>
                            </small>
                        </h1>
                        
                    </div>
                    <form name="inputData" id="inputData">
                        <div style="text-align:right;margin-bottom:10px;">
                            <small>
                                    <?=htmlentities(_("Displaying page"));?> 
                                    <a href="#" id="firstPage">&lt;&lt;</a> 
                                    <a href="#" id="previousPage">&lt;</a> 
                                    <input type="text" id="page" name="page" class="sendOnIntro" size="4" value="<?=$filteredData['page'];?>"/> 
                                    <span id="pageErrorRequired" style="display:none;"> 
                                        <?=_("Enter a page.");?>
                                    </span>
                                    <span id="pageErrorInvalid" style="display:none;">
                                        <?=_("Please enter a valid page number (positive integer or -1 for last page).");?>
                                    </span>
                                    <a href="#" id="nextPage">&gt;</a> 
                                    <a href="#" id="lastPage">&gt;&gt;</a> 
                                    <?=htmlentities(_("with"));?> 
                                    <input type="text" id="pageSize" name="pageSize" class="sendOnIntro" size="4" value="<?=$filteredData['page_size'];?>"/> 
                                    <input type="hidden" id="minPageSize" value="<?=appparams::minPageSize?>"/> 
                                    <input type="hidden" id="maxPageSize" value="<?=appparams::maxPageSize?>"/> 
                                    <span id="pageSizeErrorRequired" style="display:none;">
                                        <?=_("This field is required.");?>
                                    </span>
                                    <span id="pageSizeErrorInvalid" style="display:none;">
                                        <?=_("Please enter a valid amount (integer between ").appparams::minPageSize._(" and").appparams::maxPageSize._(") of visits per page.");?>
                                    </span> 
                                    <?=htmlentities(_("visits per page and session timeout of"));?> 
                                    <input type="text" id="sessionTimeout" name="sessionTimeout" class="sendOnIntro" size="5" value="<?=$filteredData['session_timeout'];?>"/>  
                                    <span id="sessionTimeoutErrorRequired" style="display:none;">
                                        <?=_("This field is required.");?>
                                    </span> 
                                    <span id="sessionTimeoutErrorInvalid" style="display:none;">
                                        <?=_("Please enter a valid amount of seconds (integer >= 0) for session timeout.");?>
                                    </span>
                                    <?=htmlentities(_("seconds."));?> 
                            </small>
                        </div>
                    </form>
                    <div id="googleMap" class="Flexible-container"></div>
                </div>
                <div id="highChart" style="min-width: 310px; height: 500px; margin: 50px auto 100px auto"></div>
                <div id="dataTable" class="table-responsive table-container">
                </div>
                <div>
                    <?=htmlentities(_("Server script exec. time: "));?>
                    <span id="execTime"></span>
                    <?=htmlentities(_(" seconds (aprox.)"));?>
                </div>
                <br />
                <div class="page-footer">
                    <p class="text-center">
                        <small>
                            <?=htmlentities(_("This product includes IP2Location LITE data available from"));?> 
                            <a href="http://www.ip2location.com">
                                http://www.ip2location.com
                            </a>.
                        </small>
                    </p>
                    <p class="text-center">
                        <small>
                            <?=htmlentities(_("If you want a cool timeline chart like this and thousands of other charts on your web, get highcharts.js on"));?> 
                            <a href="http://www.highcharts.com">
                                http://www.highcharts.com
                            </a>.
                        </small>
                    </p>
                    <p class="text-center">
                        <small>
                            <?=htmlentities(_("Want to contribute to this project?"));?> 
                                    <a href="http://github.com/elcodedocle/geogoogael"><?=htmlentities(_("Fork me on github!!"));?></a>
                                and/or
                                    <?=htmlentities(_("Buy me a beer!!"));?> 
                                    (<a href="http://goo.gl/zCDmg5">Paypal</a> - 
                                    <a href="bitcoin:15QjBzCVckAwtLK5v95M8GS2tbpnTwKm5B">Bitcoin: 15QjBzCVckAwtLK5v95M8GS2tbpnTwKm5B</a>)
                        </small>
                    </p>
                </div>
            </div>
        </div>
    </body>
</html>
<?php
    }
}
?>