<?php

include "../staff/functions.php";

$date = new DateTime("now", new DateTimeZone("UTC"));
$index="speedcamonline_".($date->format('Y-m-d-H-i'));

if ($handle = opendir('/srv/www/htdocs/roadsituation/scripts/speedcamonline')) {
    while (false !== ($file = readdir($handle))) {
	
        if ($file != "." && $file != ".." && count(explode(".",$file))==1) {
    	    echo $file;
	    $filec = file("/srv/www/htdocs/roadsituation/scripts/speedcamonline/".$file);
	    foreach ($filec as $line) {
	        $cam = explode(",", $line);
	        if (count($cam)==6) {
		    $lng=1*trim($cam[0]);
		    $lat=1*trim($cam[1]);
		    $speed=1*trim($cam[3]);
		    $direction=1*trim($cam[4]);
	
		    if ( ($speed>0) and ($direction>0) ) {
			$text="Speed: ".$speed." ".($direction==1?"one way":"in both direction");
			//echo $lat."|".$lng." ".$text."\n";
			add_object_int($lng,$lat,"cam",$text,null,"http://speedcamonline.ru/",$index);
		    }
		}
	    }
	}
    };
    closedir($handle);
}

replace_index_alias($index,"roadsituation_speedcamonline");


?>