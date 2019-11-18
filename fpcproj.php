<?php
	// fpcproj --project="/Users/ryanjoseph/Desktop/metal/example" --name="MetalKitExample" --template="cocoa"
	// fpcproj --project="/path/to" --template="cocoa" --name="MyProj"

	// base directory of the script
	$base = dirname($argv[0]);

	// default template:
	$template = "/templates/console";

	function parse_cmd_options(): array {
		global $argv;
		$opts = array();
		foreach ($argv as $option) {
			if (preg_match('/^--(\w+)\s*([=]*\s*(.*))$/', $option, $matches)) {
				if (strlen($matches[1]) == 1) {
					print("option '$matches[1]' must be longer than 1 character.\n");
					exit(1);
				}
				if (!$matches[3]) {
					$opts[$matches[1]] = true;
				} else {
					$opts[$matches[1]] = $matches[3];
				}
			} elseif (preg_match('/^-(\w+)\s*([=]*\s*(.*))$/', $option, $matches)) {
				if (strlen($matches[1]) > 1) {
					print("option '$matches[1]' must be 1 character only.\n");
					exit(1);
				}
				if (!$matches[3]) {
					$opts[$matches[1]] = true;
				} else {
					$opts[$matches[1]] = $matches[3];
				}
			}
		}
		return $opts;
	}

	function template_names(): string {
		global $base;
		$files = scandir("$base/templates");
		$names = "";
		foreach ($files as $file) {
			if ($file[0] == '.') continue;
			$names .= "$file, ";
		}
		return trim($names, ', ');
	}

	function rscandir(string $dir): array {
	  $result = [];
	  foreach(scandir($dir) as $filename) {
	    if ($filename[0] === '.') continue;
	    $filePath = $dir . '/' . $filename;
	    if (is_dir($filePath)) {
	      foreach (rscandir($filePath) as $childFilename) {
	        $result[] = $filename . '/' . $childFilename;
	      }
	    } else {
	      $result[] = $filename;
	    }
	  }
	  return $result;
	}

	function replace_file_macros(string $file, array $macros): void {
		$contents = file_get_contents($file);
		foreach ($macros as $key => $value) {
			$contents = str_replace($key, $value, $contents);
		}
		file_put_contents($file, $contents);
	}

	function show_help(): void {
		print("Make fpc project:\n");
		print("    --project: path to directory make project.\n");
		print("    --template: name of optional template (".template_names().").\n");
		print("    --name: name of project (optional).\n");
	}

	$options = parse_cmd_options();
	// print_r($options);
	// print_r($argv);

  // no params given, show help
	if (count($argv) == 1) {
		show_help();
		die;
	}

	if ($options) {

		if (array_key_exists("h", $options)) {
			show_help();
			die;
		}

		// validate project template

		if (array_key_exists("template", $options)) {
			$template = "/templates/".$options['template'];
			if (!file_exists("$base/$template")) die("template '$template' doesn't exist.\n");
		}

		// use --project flag or last param
		if ($options['project']) {
			$dest = $options['project'];
		} else {
			$dest = realpath($argv[count($argv)-1]);
		}
	} else {
		$dest = $argv[1];
	}

	// append template to base path
	$base .= $template;

	// validate dest
	if (!file_exists($dest)) die("dest '$dest' doesn't exist.\n");
	if (!is_dir($dest)) die("dest '$dest' isn't a directory.\n");

	print("project: '$base'\n");

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

	// source files to insert
	$project_files = rscandir($base);

	// replace macros
	$project_macros = array(
		'${project-name}' => $project_name,
		'${program-name}' => $program_name,
		'${vscode}' => '.vscode',
		// NOTE: on 10.15 Xcode stopped shipping lldb-mi so we need to reference it here
		// https://github.com/microsoft/vscode-cpptools/issues/3829
		'${lldb-mi}' => '/Users/ryanjoseph/Developer/Xcode_10_3.app/Contents/Developer/usr/bin/lldb-mi',
	);

	// copy files and replace macros
	foreach ($project_files as $name) {

		// ignore hidden files
		if ($name[0] == '.') continue;

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

		// create sub directories
		if (!is_dir($file_dest)) @mkdir(dirname($file_dest), 0777, true);

		copy($file_path, $file_dest);
		replace_file_macros($file_dest, $project_macros);
	}

		// launch sublime text
	$command = "open -a \"Sublime Text\" \"$project_file\" ";
	// exec($command);
	passthru("ls -a \"$dest\"");

?>