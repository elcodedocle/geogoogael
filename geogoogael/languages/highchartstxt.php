<?php
namespace geogoogael\languages;
class highchartstxt {
    public static function setText(&$list){
        $text['minute']['setGranularity'] = _('show per-minute values');
        $text['minute']['title1'] = _('Per-minute visits to this domain between ');
        $text['minute']['title2'] = _(' and ');
        $text['minute']['subtitle'] = _('Click and drag in the plot area to zoom in. Data from previous/next pages is not considered (first & last intervals may be truncated)');
        $text['minute']['yAxis']['title'] = _('Visits per minute');
        $text['minute']['series']['name'] = _('Minute hits');
        $text['hour']['setGranularity'] = _('show per-hour values');
        $text['hour']['title1'] = _('Hourly visits to this domain between ');
        $text['hour']['title2'] = _(' and ');
        $text['hour']['subtitle'] = _('Click and drag in the plot area to zoom in. Data from previous/next pages is not considered (first & last intervals may be truncated)');
        $text['hour']['yAxis']['title'] = _('Visits per hour');
        $text['hour']['series']['name'] = _('Hour hits');
        $text['day']['setGranularity'] = _('show per-day values');
        $text['day']['title1'] = _('Daily visits to this domain between ');
        $text['day']['title2'] = _(' and ');
        $text['day']['subtitle'] = _('Click and drag in the plot area to zoom in. Data from previous/next pages is not considered (first & last intervals may be truncated)');
        $text['day']['yAxis']['title'] = _('Visits per day');
        $text['day']['series']['name'] = _('Day hits');
        $text['month']['setGranularity'] = _('show per-month values');
        $text['month']['title1'] = _('Monthly visits to this domain between ');
        $text['month']['title2'] = _(' and ');
        $text['month']['subtitle'] = _('Click and drag in the plot area to zoom in. Data from previous/next pages is not considered (first & last intervals may be truncated)');
        $text['month']['yAxis']['title'] = _('Visits per month');
        $text['month']['series']['name'] = _('Month hits');
        $list['chart']['text']=$text;
    }
 }
?>
