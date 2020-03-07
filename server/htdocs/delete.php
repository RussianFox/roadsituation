<?php

include "staff/functions.php";

cors_header();

if (isset($_POST['index'])) {
		if (isset($_POST['id'])) {
			if (isset($_POST['aid'])) {
				remove_object($_POST['index'],$_POST['id'],$_POST['aid']);
				add_success("Remove complete");
			}
			add_error("Not set author id");
		}
		add_error("Not set element id");
};
add_error("Not set index");

?>