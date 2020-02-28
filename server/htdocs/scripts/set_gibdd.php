<?php

include '../staff/functions.php';

function numberFormat($digit, $width) {
    while(strlen($digit) < $width)
          $digit = '0' . $digit;
    return $digit;
};

echo "Start ".date('Y-m-d H:i:s')."\r\n";

$ex_regs=array(20,80,81,84,85,88,90,91,93,94);
$index="gibdd";

$params = ['index' => $index];
$bool=$client->indices()->exists($params);
if (!$bool) {
	echo "Index is not exist, creating it \r\n";
    $response = $client->indices()->create($params);
}

$query = $client->count(['index' => $index]);
$docsCount_start=1*$query['count'];

$need_clean=true;
$ids=array();
for ($ii=1;$ii<96;$ii++) {

    if (in_array($ii,$ex_regs)) { continue; };

    $url = "https://xn--90adear.xn--p1ai/r/".numberFormat($ii, 2)."/milestones/";
    echo "Region $ii \r\n";

    $file=false;
    $need_continue=5;
    while ($need_continue>0) {
	$need_continue--;
        $file=@file_get_contents($url);
        if ($file) {
    	    $need_continue=0;
        } else {
    	    echo "Attempt loading failed\n";
    	    sleep(3);
        }
    };
    
    $i=0;
    $matches=false;
    if ($file) {
		preg_match_all('/balloonContentBody.*\/milestones\/(.*)\/.*balloonContentHeader: "(.*)".*coordinates:.*\[(.*), (.*)\]/sUsi',$file, $matches);

		for ($i=0; $i<count($matches[1]); $i++) {
			$id = $matches[1][$i];
			$link="https://гибдд.рф/milestones/".$matches[1][$i]."/";
			$name=trim(str_replace('&quot;', '"', $matches[2][$i]));
			$lat=1*trim($matches[3][$i]);
			$lng=1*trim($matches[4][$i]);
			if (!update_object_int($id,$lng,$lat,"cam",$name,null,$link,$index)) {
				echo "Failed: $lat|$lng $name ($link) \r\n";
			};
			$ids[]=$id;
		};
		echo "Region ".$ii." loaded success: $i \r\n";
    } else {
		echo "Region ".$ii." loading failed \r\n";
		$need_clean=false;
    }
}

$query = $client->count(['index' => $index]);
$docsCount_add=1*$query['count'];
$docsCount_clean=$docsCount_add;

if ($need_clean) {
    echo "Starting cleaning...\r\n";
    clean_objects($index,'must_not',$ids);
} else {
	echo "Cleaning canceled, we have broken regions \r\n";
};
$query = $client->count(['index' => $index]);
$docsCount_clean=1*$query['count'];

replace_index_alias($index,"roadsituation_gibdd");

echo "Statistics. Docs added: ".($docsCount_add-$docsCount_start)." Docs cleaned: ".($docsCount_add-$docsCount_clean)." Docs now: ".$docsCount_clean."\r\n";
echo "Finish ".date('Y-m-d H:i:s')."\r\n";
echo "----------------------------------------\r\n";
?>