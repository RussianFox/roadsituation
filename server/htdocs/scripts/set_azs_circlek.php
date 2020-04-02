<?php

include "../staff/functions.php";

echo "Start ".date('Y-m-d H:i:s')."\r\n";

$index="azs_circlek";
$index_alias="roadsituation_$index"; //FALSE if no need
$bool=$client->indices()->exists(['index' => $index]);
if (!$bool) {
	echo "Index is not exist, creating it \r\n";
    $response = $client->indices()->create(['index' => $index]);
}
$query = $client->count(['index' => $index]);
$docsCount_start=1*$query['count'];

// Create a stream
$opts = [
    "http" => [
        "method" => "GET",
        "header" => "Accept-language: en\r\n" .
        "User-Agent: Mozilla/5.0 (iPad; U; CPU OS 3_2 like Mac OS X; en-us) AppleWebKit/531.21.10 (KHTML, like Gecko) Version/4.0.4 Mobile/7B334b Safari/531.21.102011-10-16 20:23:10\r\n" // i.e. An iPad
    ]
];

$context = stream_context_create($opts);

$files=array();
$files[] = "dk";
$files[] = "ee";
$files[] = "lt";
$files[] = "lv";
$files[] = "no";
$files[] = "se";
$files[] = "pl";
$files[] = "ru";

$ids=false;
$need_clean=true;

foreach ($files as $file) {
    echo "Region: ".$file."\r\n";
    $url = "https://m.circlekrussia.ru/cs/Satellite?pagename=CMS/Stations/DynamicStationsByCountry&countryid=$file";
    echo "File ".$url;
    $filec = @file($url,false,$context);
    if ($filec) {
		echo " loaded success\r\n";
        $filec = json_decode($filec, true);
        foreach ($filec['sites'] as $feature) {
				$id=$feature['id'];
				$lng=$feature['lng'];
				$lat=$feature['lat'];
				$text=$feature['name']."<br>".$feature['city']." ".$feature['street']."<br>".$feature['phonenumber'];
				if (!update_object_int($id,$lng,$lat,"azs",$text,null,"https://m.circlekrussia.ru/",$index)) {
                    echo "Failed $lat $lng $id $text \r\n"
				}
				$ids[]=$fullid;
		};
    } else {
		echo " loading failed\r\n";
		$need_clean=false;
    }
};

echo "Loading objects success \r\n";

$query = $client->count(['index' => $index]);
$docsCount_add=1*$query['count'];
$docsCount_clean=$docsCount_add;
if ($need_clean) {
	if (((count($ids))/$docsCount_start)*100 > 70) {
		clean_objects($index,'must_not',$ids);
		echo "Cleaning success \r\n";
		$query = $client->count(['index' => $index]);
		$docsCount_clean=1*$query['count'];
	} else {
		echo "Cleaning cancelled \r\n";
	};
} else {
	echo "Cleaning canceled, we have broken regions \r\n";
};

if ($index_alias) { replace_index_alias($index,$index_alias); };
echo "Statistics. Docs added: ".($docsCount_add-$docsCount_start)." Docs cleaned: ".($docsCount_add-$docsCount_clean)." Docs now: ".$docsCount_clean."\r\n";

echo "Finish ".date('Y-m-d H:i:s')."\r\n";
echo "----------------------------------------\r\n";
?>
