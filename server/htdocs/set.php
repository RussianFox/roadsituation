<?php

include "staff/functions.php";

cors_header();

if (isset($_POST['add'])) {
    if (isset($_POST['type'])) {
		if (isset($_POST['lat'])) {
			if (isset($_POST['lng'])) {
				$text="";
				$addition="";
				$aid = null;
				if (isset($_POST['aid'])) {
					$aid = $_POST['aid'];
				}
				if (isset($_POST['text'])) {
					$text = $_POST['text'];
				};
				if (isset($_POST['addition'])) {
					$addition = $_POST['addition'];
				};
				$source=null;
				$source=parse_url($_SERVER['HTTP_REFERER'], PHP_URL_HOST);
				add_object(1*$_POST['lng'],1*$_POST['lat'],$_POST['type'],$text,$addition,$source,$aid);
				add_success("Object added");
			}
			add_error("Not set latitude");
		}
		add_error("Not set longitude");
    }
    add_error("Not set type");
};

if (isset($_POST['confirm'])) {
    if (isset($_POST['index'])) {
	if (isset($_POST['id'])) {
	    if (isset($_POST['val'])) {
		$val = 1*$_POST['val'];
		if ( ($val==1) or ($val==-1) ) {
		    vote_object($_POST['index'],$_POST['id'],$val);
		    add_success("Object confirm added. ".$_POST['index']." | ".$_POST['id']);
		}
		add_error("Value is not correct");
	    }
	    add_error("Not set value");
	}
	add_error("Not set id");
    }
    add_error("Not set index");
};

add_error("Not set action");

?>