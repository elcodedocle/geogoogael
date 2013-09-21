/*global $:false, jQuery:false, ActiveXObject:false, console:false, fireGoogleMaps:false, fireHighCharts:false, dumpTable:false, fireDataTables:false*/
/**
 *  accessLogmapview helper functions v0.4 
 * Copyright (C) 2013 Gael Abadin - License: http://www.wtfpl.net/
 * 
 * Each function's purpose explained on the line below its declaration.
 * 
 * */
function setText(data){
//set some data gathered from server's response
//TODO: data is obscure, use meaningful names and clearly structured params instead
    "use strict";
    $('#visitsCount').text(data.count);
    $('#pageNumber').text(data.page);
    $('#page').val(data.page);
    $('#firstRecord').text((parseInt(data.page,10)-1)*parseInt(data.pageSize,10)+1);
    $('#execTime').text(data.execTime);
}
function processingDots(){
//dots animation shown while the server processes the request
    "use strict";
    var dots = document.getElementById('dots');
    if ($(dots).text().length < 3) {
        $(dots).text($(dots).text()+'.');
    } else {
        $(dots).text('.');
    }
}
function requestAndProcessPageJSONData(){
//on valid input send a request to the web server
    "use strict";
    if (!$('#inputData').valid()) { return; }
    var xmlhttp,intervalID = window.setInterval(processingDots,1000), data = {}, params, stateObj = {}, url, path;
    $("#postAjaxContent").hide();
    $("#preAjaxContent").show();
    if (window.XMLHttpRequest) {// code for IE7+, Firefox, Chrome, Opera, Safari
        xmlhttp = new XMLHttpRequest();
    } else {// code for IE6, IE5
        xmlhttp = new ActiveXObject("Microsoft.XMLHTTP");
    }
    xmlhttp.onreadystatechange = function() {
        if (xmlhttp.readyState === 4 && xmlhttp.status === 200) {
            if (xmlhttp.responseText) {
                window.clearInterval(intervalID);
                $("#preAjaxContent").hide();
                $("#postAjaxContent").show();
                //console.log(xmlhttp.responseText);
                data=JSON.parse(xmlhttp.responseText);
                //initialize google maps
                fireGoogleMaps(data,'googleMap');
                //initialize highcharts, set auto granularity
                fireHighCharts(data,'highChart',-1);
                //generate and dump table on #dataTable div
                dumpTable(data,'dataTable','theMotherOfAllTables');
                //initialize datatables features and styling
                fireDataTables('theMotherOfAllTables');
                //set output text on some other fields
                setText(data);
                //set the url to reflect the parameters of the JSON acquired
                //extract the
                path = /^([\w\W]*\/)\d+\/\d+\/\d+(\/[\w\W]*)$/.exec(window.location.pathname);
                path=(path===null)?window.location.pathname:path[1];
                url = window.location.origin+path+data.pageSize+'/'+data.sessionTimeout+'/'+data.page+'/';
                history.replaceState(stateObj, "", url);
            }
        } else if(xmlhttp.readyState === 4){
            var errorDoc = document.open("text/html");
            errorDoc.write(xmlhttp.responseText);
            errorDoc.close();
        }
    };
    params = '?f=accessLogMap&requestAndProcessPageJSONData=true';
    params += '&page='+document.getElementById('page').value;
    params += '&pageSize='+document.getElementById('pageSize').value;
    params += '&sessionTimeout='+document.getElementById('sessionTimeout').value;
    xmlhttp.open('GET', 'main.php'+params, true);
    xmlhttp.setRequestHeader('Content-type',
            'application/x-www-form-urlencoded');
    xmlhttp.send();
}
$(document).ready(function(){
//set some view component's behaviour and input validator functions
//then call the AJAX request sender to get data to fill the view
    "use strict";
    var $inputs,i;
    $('#firstPage').click(function(){
        $('#page').val(1);
        requestAndProcessPageJSONData();
    });
    $('#previousPage').click(function(){
        var value = parseInt($('#page').val(), 10);
        value = value>1?value-=1:1;
        $('#page').val(value);
        requestAndProcessPageJSONData();
    });
    $('#nextPage').click(function(){
        $('#page').val(parseInt($('#page').val(), 10)+1);
        requestAndProcessPageJSONData();
    });
    $('#lastPage').click(function(){
        $('#page').val(-1);
        requestAndProcessPageJSONData();
    });
    $inputs = $(":input[type='text'].sendOnIntro");
    for (i = 0; i < $inputs.length; i+=1) {
        $inputs[i].nextInput=$inputs[(i+1)%$inputs.length];
    }
    $('.sendOnIntro').each(function(){
        var kk = $(this).nextInput;
        $(this).keyup(function(e){
            if (e.which===13) { requestAndProcessPageJSONData(); }
        });
        $(this).keydown(function(e){
            if (e.which===9) { e.preventDefault();$(this.nextInput).click(); }
        });
        $(this).click(function(e){
            $(this).select();
        });
    });
    jQuery.validator.addMethod("page", function(value, element) {
        return this.optional(element) || (/^\-0{0,10}1$/.test(value)) || (/^[0-9]+$/.test(value));
    }, $('#pageErrorInvalid').text());
    jQuery.validator.addMethod("pageSize", function(value, element) {
        return this.optional(element) || 
            ((/^[0-9]+$/.test(value)) && 
            (parseInt(value,10) >= $('#minPageSize').val()) && 
            (parseInt(value,10) <= $('#maxPageSize').val()));
    }, $('#pageSizeErrorInvalid').text());
    $("#inputData").validate({
        rules :{
            "page" : {
                required : true,
                page: true
            },
            "pageSize" : {
                required : true,
                pageSize: true
            },
            "sessionTimeout" : {
                required : true,
                digits: true
            }
        },
        messages :{
            "page" : {
                required : $('#pageErrorRequired').text()
            },
            "pageSize" : {
                required : $('#pageSizeErrorRequired').text()
            },
            "sessionTimeout" : {
                required : $('#sessionTimeoutErrorRequired').text(),
                digits : $('#sessionTimeoutErrorInvalid').text()
            }
        },
        errorPlacement: function (error, element) {
            $(element).tooltipster('update', $(error).text());
            $(element).tooltipster('show');
        },
        success: function (label, element) {
            $(element).tooltipster('hide');
        }
    });
    $('#inputData input[type="text"]').tooltipster({ 
        trigger: 'custom', 
        onlyOne: false, 
        position: 'bottom' 
    });
    requestAndProcessPageJSONData();
});