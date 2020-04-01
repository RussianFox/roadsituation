<?php

include '../staff/functions.php';

echo "Start ".date('Y-m-d H:i:s')."\r\n";

$pages=['a','s','o'];

$index="ekaterinburg";
$index_alias="roadsituation_ekaterinburg"; //FALSE if no need

$bool=$client->indices()->exists(['index' => $index]);
if (!$bool) {
	echo "Index is not exist, creating it \r\n";
    $response = $client->indices()->create(['index' => $index]);
}
$query = $client->count(['index' => $index]);
$docsCount_start=1*$query['count'];
$ids = array();

$datar = array('rubric_id' => '3');

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

	$url = "https://xn--80acgfbsl1azdqr.xn--p1ai/data-send/infomap/items";
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
		foreach ($file['data']['items'] as $feature) {
		
			$object_id=$feature['id'];
			$type="block";
			$name=$feature['title'].'<br>'.$feature['comment'];
			$link="https://екатеринбург.рф/жителям/инфокарта#".$object_id;
			
			$coordinates=array();
			
			foreach ($feature['points'] as $point) {
			
                array_push($coordinates,array('lat'=>$point[1],"lng"=>$point[0]));
			
			}
			$center = center_line($coordinates);
			
			update_object_int($object_id,$center['lng'],$center['lat'],$type,$name,null,$link,$index);
			$ids[] = $object_id;
		}
		
		$query = $client->count(['index' => $index]);
		$docsCount_add=1*$query['count'];
		$docsCount_clean=$docsCount_add;
		if (((count($ids))/$docsCount_start)*100 > 70) {
			clean_objects($index,'must_not',$ids);
			echo "Cleaning success \r\n";
			$query = $client->count(['index' => $index]);
			$docsCount_clean=1*$query['count'];
		} else {
			echo "Cleaning cancelled \r\n";
		};
		
		if ($index_alias) { replace_index_alias($index,$index_alias); };
		echo "Statistics. Docs added: ".($docsCount_add-$docsCount_start)." Docs cleaned: ".($docsCount_add-$docsCount_clean)." Docs now: ".$docsCount_clean."\r\n";
    } else {
		echo "failed \r\n";
    }
echo "Finish ".date('Y-m-d H:i:s')."\r\n";
echo "----------------------------------------\r\n";
?>
