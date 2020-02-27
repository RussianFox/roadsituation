<?php

include '../staff/functions.php';

function numberFormat($digit, $width) {
    while(strlen($digit) < $width)
          $digit = '0' . $digit;
    return $digit;
};

echo "Start ".date('Y-m-d H:i:s')."\r\n";

$pages=['a','s','o'];

$index="transportmos";

$ids = array();

//$datar = array('action' => 'get_road_closures_coordinates', 'dt' => $date->format('H:i d.m.Y'));
$datar = array('action' => 'get_road_closures_coordinates', 'dt' => date('H:i d.m.Y'));

$context = stream_context_create(array(
        'http' => array(
            'method' => 'POST',
            'header' => "
Pragma: no-cache
Cache-Control: no-cache
Accept: application/json, text/javascript, */*; q=0.01
X-Requested-With: XMLHttpRequest
User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/76.0.3809.132 Safari/537.36
Sec-Fetch-Mode: cors
Content-Type: application/x-www-form-urlencoded; charset=UTF-8
Sec-Fetch-Site: same-origin
Referer: https://transport.mos.ru/info/closures/?type=&district=&address=&dt=&id=
Accept-Encoding: gzip, deflate, br
Accept-Language: en-US,en;q=0.9,ru;q=0.8
            ",
            'content' => http_build_query($datar),
        ),
    ));

    $url = "https://transport.mos.ru/ajax/transport/";
    print_r($url);
    echo "Start loading: $url ..... \r\n";
    $matches=false;
    $file=false;
    $file=@file_get_contents($url,null,$context);
    if ($file) {
		echo "loaded \r\n";
		$file = json_decode($file, true);
		$html=$file['html'];
		$list = preg_match_all('/<li id="closure_id_.*<span class="sp1">(.*)<\/span>.*<span class="sp2">(.*)<\/span>.*class="moreInfo_block">(.*)<\/div>.*<\/li>/sUsi',$html,$matches);
		
		foreach ($file['json']['features'] as $feature) {
		
			$object_id=$feature['options']['object_id'];
			if (strpos($object_id,'_')) {
				continue;
			};
			$type="block";
			$name=$feature['properties']['hintContent']." Type:".$feature['options']['type']." Color: ".$feature['options']['iconColor'].", ".$feature['options']['strokeColor'][0].", ".$feature['options']['strokeColor'][1];
			$name=$feature['properties']['hintContent'];
			
			$kk = array_search($name,$matches[2]);
			if ($kk) {
				$name = strip_tags(trim($matches[2][$kk])."\r\n ".trim($matches[1][$kk])."\r\n ".trim($matches[3][$kk]));
				$name = preg_replace('/\s+/', ' ', $name);
			}
			
			$link="https://transport.mos.ru/";
			$coordinates=array();
			foreach ($feature['geometry']['coordinates'] as $coordinate) {
				array_push($coordinates,array('lat'=>$coordinate[1],"lng"=>$coordinate[0]));
			};
			$center = center_line($coordinates);
			if ($feature['options']['strokeColor'][1]=="#000000") {
				$type="block";
			} else {
				$type="maintenance";
			}
			update_object_int($object_id,$center['lng'],$center['lat'],$type,$name,null,$link,$index,$feature['geometry']);
			$ids[] = $object_id;
		}
		
		clean_objects($index,'must_not',$ids);
		replace_index_alias($index,"roadsituation_transportmos");
    } else {
		echo "failed \r\n";
    }
echo "Finish ".date('Y-m-d H:i:s')."\r\n";
echo "----------------------------------------\r\n";
?>