<?php

include '../staff/functions.php';

//$rt = array(array(15,68),array(43,92));
//print_r($rt);
//print_r(rollCoordinates($rt));
//exit;

function numberFormat($digit, $width) {
    while(strlen($digit) < $width)
          $digit = '0' . $digit;
    return $digit;
};

echo "Start ".date('Y-m-d H:i:s')."\r\n";

$pages=['a','s','o'];

$index="transportmos";
$index_alias="roadsituation_transportmos"; //FALSE if no need
$bool=$client->indices()->exists(['index' => $index]);
if (!$bool) {
	echo "Index is not exist, creating it \r\n";
    $response = $client->indices()->create(['index' => $index]);
}
$query = $client->count(['index' => $index]);
$docsCount_start=1*$query['count'];
$ids = array();

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
	$url = "https://transport.mos.ru/map/get?action=get_coords&type=road_closures";
    echo "Start loading: $url \r\n";
    $matches=false;
	$rc=10;
	$file=false;
	while (($rc>0) and (!$file)) {
		echo "Trying...\r\n";
		$file=@file_get_contents($url,null,$context);
		$rc--;
	}
    if ($file) {
		echo "loaded \r\n";
		$file = json_decode($file, true);
		$html=@file_get_contents("https://transport.mos.ru/mostrans/closures");
		$list = preg_match_all('/<li id="closure_id_.*<span class="sp1">(.*)<\/span>.*<span class="sp2">(.*)<\/span>.*class="moreInfo_block">(.*)<\/div>.*<\/li>/sUsi',$html,$matches);
		
		foreach ($file['features'] as $feature) {
		
			$object_id=$feature['id'];
			if (strpos($object_id,'_')) {
				continue;
			};
			$type="block";
			//$name=$feature['properties']['hintContent']." Type:".$feature['options']['type']." Color: ".$feature['options']['iconColor'].", ".$feature['options']['strokeColor'][0].", ".$feature['options']['strokeColor'][1];
			$name=$feature['properties']['hintContent'];
			
			$kk = array_search($name,$matches[2]);
			if ($kk) {
				$name = strip_tags(trim($matches[2][$kk])."\r\n ".trim($matches[1][$kk])."\r\n ".trim($matches[3][$kk]));
				$name = preg_replace('/\s+/', ' ', $name);
			}
			
			$link="https://transport.mos.ru/";
			$coordinates=array();
			$geometry = $feature['geometry'];
			$geometry['coordinates']=rollCoordinates($geometry['coordinates']);
			foreach ($geometry['coordinates'] as $coordinate) {
				array_push($coordinates,array('lat'=>$coordinate[1],"lng"=>$coordinate[0]));
			};
			$center = center_line($coordinates);
			if ($feature['options']['strokeColor'][1]=="#000000") {
				$type="block";
			} else {
				$type="maintenance";
			}
			update_object_int($object_id,$center['lng'],$center['lat'],$type,$name,null,$link,$index,$geometry);
			$ids[] = $object_id;
		}
		
		echo "Loading objects success \r\n";
		
		$query = $client->count(['index' => $index]);
		$docsCount_add=1*$query['count'];
		
		if (((count($ids))/$docsCount_start)*100 > 70) {
			clean_objects($index,'must_not',$ids);
			echo "Cleaning success \r\n";
		} else {
			echo "Cleaning cancelled \r\n";
		};
		
		if ($index_alias) { replace_index_alias($index,$index_alias); };
		$query = $client->count(['index' => $index]);
		$docsCount_clean=1*$query['count'];
		echo "Statistics. Docs added: ".($docsCount_add-$docsCount_start)." Docs cleaned: ".($docsCount_add-$docsCount_clean)." Docs now: ".$docsCount_clean."\r\n";
    } else {
		echo "failed \r\n";
    }
echo "Finish ".date('Y-m-d H:i:s')."\r\n";
echo "----------------------------------------\r\n";
?>