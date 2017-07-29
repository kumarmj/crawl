<?php

namespace App\Http\Controllers;

use Dingo\Api\Routing\Helpers;
use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use App\Http\Requests;
use Goutte;

class CrawlController extends Controller
{

    private function extract_num($str){
        preg_match_all('!\d+!', $str, $matches);
        return $matches[0][0];
    }

    public function show(Request $request)
    {
        $html = Goutte::request('GET', 'https://my.maerskline.com/schedules/');
        $token = $html->filterXPath('//*/input[@name="authenticityToken"]')->attr('value');
        $query = $request->query();

        $from = $query["from"];
        $to = $query["to"];


        $url = "https://my.maerskline.com/schedules/pointtopointresults?authenticityToken=4da607035e5f5de6df6c37ece42336b9f5db0d38&b.from%5B0%5D.geoId=$from&b.to%5B0%5D.geoId=$to&b.from%5B0%5D.option=CY&b.to%5B0%5D.option=CY&b.dateType=D&b.date=28/07/2016&b.lines%5B0%5D.iso=42G1&b.lines%5B0%5D.quantity=1&b.lines%5B0%5D.weight=1&b.numberOfWeeks=4";

        $result_html = Goutte::request('GET', $url);

        $tr = $result_html->filter('table.schedule-table > tr');

        $vessel_schedules = array();

        for($i = 1; $i < $tr->count(); $i++){
            if($i%2 != 0){
                $td = $tr->eq($i)->filter('td');
                $vessel_schedule = array(
                        'departure_date' => $td->eq(0)->filter('strong')->text(),
                        'arrival_date' => $td->eq(1)->filter('strong')->text(),
                        'vessel_name' => trim($td->eq(2)->filter('a')->text()),
                        'vessel_number' => $this->extract_num($td->eq(2)->text()),
                        'transit_time' => $this->extract_num($td->eq(3)->text())
                    );
            }else{
                $data_tr = $tr->eq($i)->filter('table tr');

                $vessel_routes = array();

                for($j = 0; $j < $data_tr->count(); $j+=3){
                    
                    if(trim($data_tr->eq($j)->filter('td')->eq(0)->filter('span')->text()) == 'Trade Lane')
                        $j = $j + 1;

                    $vessel_route = array(
                            'departure_date' => $data_tr->eq($j)->filter('td')->eq(0)->filter('span')->text(),
                            'arrival_date' => $data_tr->eq($j+2)->filter('td')->eq(0)->filter('span')->text(),
                            'from_terminal' => $data_tr->eq($j)->filter('td')->eq(1)->filter('small > span')->eq(1)->text(),
                            'to_terminal' => $data_tr->eq($j+2)->filter('td')->eq(1)->filter('small > span')->eq(1)->text()
                        );

                    array_push($vessel_routes, $vessel_route);
                }

                $vessel_schedule['routes'] = $vessel_routes;
                array_push($vessel_schedules, $vessel_schedule);
            }
        }

        return response()->json($vessel_schedules);
    }
}
