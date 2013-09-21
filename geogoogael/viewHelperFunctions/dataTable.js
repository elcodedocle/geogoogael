/*global $:false, jQuery:false*/
function dumpTable(data,containerId,tableId){
//creates a table with id tableId using data object from JSON response
//and places it into current document's containerId
//TODO: data is obscure, use meaningful names and clearly structured params instead
    "use strict";
    var ip, table, tbody, propertyName, row, th, matchesPropertyName, index, td, outputNode;
    table = document.createElement('table');
    table.setAttribute('cellpadding',0);
    table.setAttribute('cellspacing',0);
    table.setAttribute('border',0);
    table.setAttribute('class',"table table-striped table-bordered");
    table.setAttribute('id',tableId);
    table.createTHead();
    row = document.createElement('tr');
    for (propertyName in data.table.headers){
        if (data.table.headers.hasOwnProperty(propertyName)) {
            th = document.createElement('th');
            $(th).text(data.table.headers[propertyName]);
            row.appendChild(th);
        }
    }
    table.tHead.appendChild(row);
    tbody = document.createElement('tbody');
    for (index in data.ips){
        if (data.ips.hasOwnProperty(index)) {
            row = document.createElement('tr');
            for (propertyName in data.table.headers){
                if (data.table.headers.hasOwnProperty(propertyName)) {
                    td = document.createElement('td');
                    if (propertyName==='ips'){
                        ip = data[propertyName][index];
                        if (ip.length>12){//ip is obfuscated
                            $(td).text(ip.substring(0,9)+'...');
                        }
                    } else if (propertyName==='timestamps') {
                        $(td).text(data[propertyName][index]);
                    } else {
                        $(td).text(data.matches[index][propertyName]);
                    }
                    row.appendChild(td);
                }
            }
            tbody.appendChild(row);
        }
    }
    table.appendChild(tbody);
    outputNode = document.getElementById(containerId);
    while (outputNode.hasChildNodes()) {//overkill
        outputNode.removeChild(outputNode.lastChild);
    }
    outputNode.appendChild(table);
}
function fireDataTables(tableId) {
//Turns table in current document's tableId into a datatable.js table
//TODO: "records per page" text does not use translation interface
    "use strict";
    $('#'+tableId).dataTable({
    "sDom": "<'row'<'span6'l><'span6'f>r>t<'row'<'span6'i><'span6'p>>",
    "sPaginationType": "full_numbers",
    "oLanguage": {
        "sLengthMenu": "_MENU_ records per page"
        }
    });
}