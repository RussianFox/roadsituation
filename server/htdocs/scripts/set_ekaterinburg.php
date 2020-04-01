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

	$url = "https://xn--80acgfbsl1azdqr.xn--p1ai/data-send/infomap/items?rubric_id=3";
    echo "Start loading: $url \r\n";
    $matches=false;
	$rc=10;
	$file=false;
	while (($rc>0) and (!$file)) {
		echo "Trying...\r\n";
		$file=@file_get_contents($url);
		$rc--;
	}
    if ($file) {
		echo "loaded \r\n";
		$file = json_decode($file, true);
		foreach ($file['items'] as $feature) {
		
			$object_id=$feature['id'];
			$type="block";
			$name=$feature['data']['title'].'<br>'.$feature['comment'];
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
