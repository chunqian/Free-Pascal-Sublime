<?php
	// print_r($argv);
	print("make fpc project\n");

	// base directory of the script
	$base = dirname($argv[0]);

	// default template:
	$template = "/templates/console";

// fpcproj --project="/Users/ryanjoseph/Desktop/metal/example" --name="MetalKitExample" --template="cocoa"
// fpcproj --project="/path/to" --template="cocoa" --name="MyProj"

	// TODO: we need to make a proper template system now so lets just leave this for later
	$options = getopt("h", array("project:", "template::", "name::"));

	if ($options) {
		// print_r($options);
		// print_r($argv);

		if (array_key_exists("h", $options)) {
			print("Make fpc project:\n\n");
			print("Commands take the format --key=\"value\".\n\n");
			print("    --project: path to directory make project.\n");
			print("    --template: name of optional template.\n");
			print("    --name: name of project (optional).\n");
			die();
		}

		if (!array_key_exists("project", $options)) die("--project is required.\n");

		// validate project template
		// TODO: TEMPLATES ARE BROKEN!
		if (array_key_exists("template", $options)) {
			$template = "/templates/".$options['template'];
			if (!file_exists("$base/$template")) die("template '$template' doesn't exist.\n");
		}

		$dest = $options['project'];

	} else {
		$dest = $argv[1];
	}

	// append template to base path
	$base .= $template;

	// validate dest
	if (!file_exists($dest)) die("dest '$dest' doesn't exist.\n");
	if (!is_dir($dest)) die("dest '$dest' isn't a directory.\n");

	// print("project: '$base'\n");

	// get project name from source directory
	if (array_key_exists("name", $options)) {
		$project_name = $options['name'];
	} else {
		$project_name = basename($dest);
	}

	// TODO: make this an option so we can name projects "main.pas"
	$program_name = $project_name;

	print("project name: '$project_name'\n");

	// final project file
	$project_file = "$dest/$project_name.sublime-project";
	print("project file: '$project_file'\n");

	// files
	$project_files = array(
		"\${project-name}.sublime-project",
		"/Sources",
		"/Sources/\${project-name}.pas",
		"/Settings",
		"/Settings/.fpcbuild",
		"/Settings/base.settings",
		"/Settings/console.settings",
	);

	// replace macros
	$project_macros = array(
		"\${project-name}" => $project_name,
		"\${program-name}" => $program_name,
	);

	// copy files and replace macros
	foreach ($project_files as $name) {

		$file_path = "$base/$name";
		if (!file_exists($file_path)) die("file '$file_path' doesn't exist.\n");

		// replace macros in file name
		$dest_name = $name;
		foreach ($project_macros as $key => $value) {
			$dest_name = str_replace($key, $value, $dest_name);
		}


		$file_dest = "$dest/$dest_name";
		if (file_exists($file_dest)) {
			// die("file '$file_dest' already exists.\n");
			print("file '$file_dest' already exists.\n");
			continue;
		}

		if (is_dir($file_path)) {
			mkdir($file_dest);
		} else {
			copy($file_path, $file_dest);

			// replace macros in text file
			$contents = file_get_contents($file_dest);
			foreach ($project_macros as $key => $value) {
				$contents = str_replace($key, $value, $contents);
			}
			file_put_contents($file_dest, $contents);
		}

		
	}

		// launch sublime text
	$command = "open -a \"Sublime Text\" \"$project_file\" ";
	// exec($command);
	passthru("ls -a \"$dest\"");

?>