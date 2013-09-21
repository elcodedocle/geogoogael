<?php
namespace geogoogael\languages;
class datatablestxt {
    public static function setText(&$list){
        $list['table']['headers']['ips']=\geogoogael\appparams::maskIPs?
            _('IP (obfuscated)'):_('IP');
        $list['table']['headers']['timestamps']=_('Time Stamp');
        if (isset($list['matches'])){
            $list['table']['headers']['ISO-639-1']=_('Country Code');
            $list['table']['headers']['COUNTRY_NAME']=_('Country');
            $list['table']['headers']['REGION_NAME']=_('Area');
            $list['table']['headers']['CITY_NAME']=_('City');
            $list['table']['headers']['LATITUDE']=_('Latitude');
            $list['table']['headers']['LONGITUDE']=_('Longitude');
            $list['table']['headers']['AREA_CODE']=_('Area Code');
            $list['table']['headers']['TIMEZONE']=_('Timezone');
        }
    }
}
?>