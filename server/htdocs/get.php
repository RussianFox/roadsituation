<?php
    require "staff/functions.php";

    $quadr=$FALSE;
    $coords=$FALSE;


    if(isset($_GET["quadr"])) {
	$quadr=1*$_GET["quadr"];
    }

    if ($quadr) {
	//$coords = coord_quadr($quadr);
	//print_r(get_quadr($quadr));
	header('Content-type: application/json');
	cors_header();
	echo json_encode(get_quadr($quadr));
	exit;
    }

    if ($coords) {
	//print_r($coords);
	exit(0);
    }
    
    print_r("Nothing to do");
    exit(0);



?>