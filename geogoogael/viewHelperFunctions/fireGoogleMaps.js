/*global google:false, $:false, jQuery:false*/
function fireGoogleMaps(data,containerId) {
//will place a google map on current document's containerId with markers on 
//coordinates specified by data.matches[match].LATITUDE and LONGITUDE
//TODO: data is obscure, use meaningful names and clearly structured params instead
    "use strict";
    var myLatlng, mapOptions, map, marker, match, rand;
    myLatlng = new google.maps.LatLng(0,0);
    mapOptions = {
        zoom: 2,
        center: myLatlng,
        scaleControl:true,
        scaleControlOptions: {
            position: google.maps.ControlPosition.BOTTOM_LEFT
        },
        mapTypeId: google.maps.MapTypeId.ROADMAP
    };
    map = new google.maps.Map(document.getElementById(containerId), mapOptions);
    for (match in data.matches){
        if (data.matches.hasOwnProperty(match)&&data.matches[match].hasOwnProperty('LATITUDE')) {
            marker = new google.maps.Marker({
                position: new google.maps.LatLng(parseFloat(data.matches[match].LATITUDE)+(Math.floor(Math.random()*101)-50)*0.0001,parseFloat(data.matches[match].LONGITUDE)+(Math.floor(Math.random()*101)-50)*0.0001),
                map: map,
                title: data.ips[match]+"\n"+data.matches[match].CITY_NAME+"\n"+data.timestamps[match]
            });
        }
    }
}