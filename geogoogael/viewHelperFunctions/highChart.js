/*global $:false,jQuery:false*/
/**
 * highCharts timeline chart selective data granularization mod v0.2
 * 
 * Copyright (C) 2013 Gael Abadin - License: http://www.wtfpl.net/
 * 
 * Replaces highCharts link on bottom right corner of the chart with a 
 * granularization switch link
 * 
 */
function getGranularizedCounts(data,granularity){
//gets hits count for each fixed length time interval between first and last entries
//TODO: data is obscure, use meaningful names and clearly structured params instead
    "use strict";
    var ranges,range,endkey,min,max,lastkey,counts = [],i,j;
    ranges = {'minute':60,'hour':3600,'day':86400,'month':2629800};
    range = ranges[granularity];
    endkey = data.unixtimestamps.length-1;
    min=data.unixtimestamps[0]-data.unixtimestamps[0]%range;
    max=data.unixtimestamps[endkey];
    lastkey = 0;
    for (i=0;i<=Math.floor((max-min)/range);i+=1){
        counts[i]=0;
        for(j=lastkey;j<=endkey;j+=1){
            if (data.unixtimestamps[j]<min+range*(i+1)){
                counts[i]+=1;
            } else {
                lastkey=j;
                break;
            }
        }
    }
    return counts;
}
function fireHighCharts(data,containerId,gIndex){
//sets highcharts options for a zoomable timeline granularized chart
//then places the chart on current document's containerId
//TODO: data is obscure, use meaningful names and clearly structured params instead
    "use strict";
    /**
    * maxzoom defaults:
    *   - about one minute in ms for minutely
    *   - about one hour in ms for hourly
    *   - about 1 day in ms for daily
    *   - about 1 month in ms for monthly
    * pointinterval defaults:
    *   - 1 minute in ms for minutely,
    *   - 1 hour in ms for hourly,
    *   - 1 day in ms for daily,
    *   - (365*4+1)/(12*4) days in ms for monthly (avg. month)
    */
    var maxZoom, pointInterval, setDataFromTimeStamps, dateStart, dateEnd, interval, granularity, chartData, chartText, options, granularities, i, gNextIndex;
    granularities = ['minute','hour','day','month'];
    maxZoom = {'minute':62000,'hour':3700000,'day':88400000,'month':2659800000};
    pointInterval = {'minute':60000,'hour':3600000,'day':86400000,'month':2629800000};
    dateStart = new Date(Math.min.apply(null,data.unixtimestamps)*1000);
    dateEnd = new Date(Math.max.apply(null,data.unixtimestamps)*1000);
    interval = dateEnd.getTime()-dateStart.getTime();
    if (gIndex>=0&&gIndex<granularities.length){
        granularity = granularities[gIndex];
    } else {
        for (i=granularities.length-1;i>=0;i-=1){
            granularity = granularities[i];
            if (interval>4*pointInterval[i]){ break; }
        }
    }
    chartData = getGranularizedCounts(data, granularity);
    chartText = data.chart.text[granularity];
    options = {
        "chart":{
            "zoomType":"x",
            "spacingRight":20
        },
        "title":{
            "text":chartText.title1+dateStart.toLocaleString()+chartText.title2+dateEnd.toLocaleString()
        },
        "subtitle":{
            "text":chartText.subtitle
        },
        "xAxis":{
            "type":"datetime",
            "maxZoom":maxZoom[granularity],
            "title":{
                "text":null
            }
        },
        "yAxis":{
            "title":{
                "text":chartText.yAxis.title
            }
        },
        "tooltip":{
            "shared":true
        },
        "legend":{
            "enabled":false
        },
        "plotOptions":{
            "area":{
                "fillColor":{
                    "linearGradient":{
                        "x1":0,
                        "y1":0,
                        "x2":0,
                        "y2":1
                    },
                    "stops":[
                        [0,"#2f7ed8"],
                        [1,"rgba(47,126,216,0)"]
                    ]
                },
                "lineWidth":1,
                "marker":{
                    "enabled":false
                },
                "shadow":false,
                "states":{
                    "hover":{
                        "lineWidth":1
                    }
                },
                "threshold":null
            }
        },
        "series":[
            {
                "type":"area",
                "name":chartText.series.name,
                "pointInterval":pointInterval[granularity],
                "pointStart":(dateStart.getTime()-dateStart.getTime()%pointInterval[granularity]),
                "data":chartData
            }
        ]
    };
    gNextIndex = ((granularities.indexOf(granularity)+1)%granularities.length);
    granularity = granularities[gNextIndex];
    $(function(){
        $('#'+containerId).highcharts(options);
        $('#'+containerId+" :last-child :last-child :last-child :last")
        .text(data.chart.text[granularity].setGranularity)
        .click(function(event){
            event.stopPropagation();
            fireHighCharts(data,containerId,gNextIndex);
        });
    });
}