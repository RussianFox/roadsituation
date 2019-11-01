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
    $response = $client->indices()->create($params);
}

$need_clean=true;
$ids=array();
for ($ii=1;$ii<96;$ii++) {

    if (in_array($ii,$ex_regs)) { continue; };

    $url = "https://xn--90adear.xn--p1ai/r/".numberFormat($ii, 2)."/milestones/";
    echo "Region $ii\n";

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
        //print_r($matches);

	for ($i=0; $i<count($matches[1]); $i++) {
	    $id = $matches[1][$i];
	    $link="https://гибдд.рф/milestones/".$matches[1][$i]."/";
	    $name=trim(str_replace('&quot;', '"', $matches[2][$i]));
	    $lat=1*trim($matches[3][$i]);
	    $lng=1*trim($matches[4][$i]);
	    //echo "$lat|$lng $name ($link)\n";
	    //echo "$lng\n";
	    if (!update_object_int($id,$lng,$lat,"cam",$name,null,$link,$index)) {
		echo "Failed: $lat|$lng $name ($link)\n";
	    };
	    $ids[]=$id;
	};
	echo "Region loaded success: $i\n";
    } else {
	echo "Region loading failed\n";
	$need_clean=false;
    }
}

if ($need_clean) {
    echo "Starting cleaning...\n";
    clean_objects($index,'must_not',$ids);
};

replace_index_alias($index,"roadsituation_gibdd");

echo "Finish ".date('Y-m-d H:i:s')."\r\n";
echo "----------------------------------------\r\n";
?>