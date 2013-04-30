<?php
	$files = scandir("./projects");
	$filesArray = array();
	foreach($files as $project)
	{
		if(substr($project, -4) == ".xml")
		{
			array_push($filesArray,$project);
		}
	}
	return $filesArray;
?>