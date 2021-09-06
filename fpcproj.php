<?php
	// fpcproj --project="/Users/ryanjoseph/Desktop/metal/example" --name="MetalKitExample" --template="cocoa"
	// fpcproj --project="/path/to" --template="cocoa" --name="MyProj"
	// fpcproj --project="." --template="xcodeproj" --name="Tester" --company="alchemistguild" --bundleid="com.alchemistguild"

	// base directory of the script
	$base = dirname($argv[0]);

	// default template
	// this path will be resolved to the sublime text packages directory
	$template = "/console";

	const ANSI_FORE_BLACK           = 30;
	const ANSI_FORE_RED             = 31;
	const ANSI_FORE_GREEN           = 32;
	const ANSI_FORE_YELLOW          = 33;
	const ANSI_FORE_BLUE            = 34;
	const ANSI_FORE_MAGENTA         = 35;
	const ANSI_FORE_CYAN            = 36;
	const ANSI_FORE_WHITE           = 37;
	const ANSI_FORE_RESET           = 39;

	const ANSI_BACK_BLACK           = 40;
	const ANSI_BACK_RED             = 41;
	const ANSI_BACK_GREEN           = 42;
	const ANSI_BACK_YELLOW          = 43;
	const ANSI_BACK_BLUE            = 44;
	const ANSI_BACK_MAGENTA         = 45;
	const ANSI_BACK_CYAN            = 46;
	const ANSI_BACK_WHITE           = 47;
	const ANSI_BACK_RESET           = 49;

	const ANSI_STYLE_BOLD           = 1;
	const ANSI_STYLE_ITALIC         = 3;
	const ANSI_STYLE_UNDERLINE      = 4;
	const ANSI_STYLE_BLINK          = 5;

	function printc(int $color, string $text) {
		print("\033[".$color."m".$text."\033[0m");
	}

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

	function rglob($pattern, $flags = 0) {
    $files = glob($pattern, $flags); 
    foreach (glob(dirname($pattern).'/*', GLOB_ONLYDIR|GLOB_NOSORT) as $dir) {
        $files = array_merge($files, rglob($dir.'/'.basename($pattern), $flags));
    }
    return $files;
	}

	function rscandir(string $dir): array {
		if (!file_exists($dir)) die('rscandir path '.$dir." doesn't exist.\n");
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

	function is_template_xcode(string $path): bool {
		$files = rglob("$path/*.xcodeproj");
		return count($files);
	}

	function resolve_template_path(string $name): string {
		global $base;
		$template = "$base/templates/$name";
		if (!file_exists($template)) die("template '$template' doesn't exist.\n");
		return $template;
	}

	function print_header(string $header, string $message): void {
		printc(ANSI_FORE_RED, "$header: ");
		print("$message\n");
	}

	function prompt(string $prompt, string $default = ''): string {
		if ($default) {
			printc(ANSI_FORE_YELLOW, "$prompt (default: $default): ");	
		} else {
			printc(ANSI_FORE_YELLOW, "$prompt: ");
		}
		$value = readline();
		$value = trim($value);
		readline_add_history($value);
		if (!$value) $value = $default;
		return $value;
	}

	function interactive_mode() {
		global $options;

		$location = prompt('Location');
		$location = str_replace('~', getenv('HOME'), $location);
		@mkdir($location, 0777, true);
		if (file_exists($location))
			$location = realpath($location);
		$options['project'] = $location;
		printc(ANSI_FORE_GREEN, "\t$location\n");

		printc(ANSI_FORE_YELLOW, "Template:\n");
		printc(ANSI_FORE_CYAN, "\t".template_names()."\n");
		$options['template'] = readline();
		readline_add_history($options['template']);
		$template = resolve_template_path($options['template']);

		$options['name'] = prompt('Name', basename($options['project']));
		$options['program'] = prompt('Program', basename($options['name']));

		if (is_template_xcode($template)) {
			$options['company'] = prompt('Company');
			$options['bundleid'] = 'com.'.$options['company'].'.'.$options['name'];
		}

		print("\n\n");
		// print_r($options);
	}

	function show_help(): void {
		print("Make fpc project:\n");
		print("    --project: path to directory make project.\n");
		print("    --template: name of optional template (".template_names().").\n");
		print("    --name: name of project (optional).\n");
		print("    --company: name of company (optional).\n");
		print("    --bundleid: name of company (optional).\n");
		print("    --program: name of the .pas program file (optional).\n");
	}

	$options = parse_cmd_options();
	// print_r($options);

	// start interactive mode
	if (count($argv) == 1) {
		interactive_mode();
	}

	if ($options) {

		if (array_key_exists("h", $options)) {
			show_help();
			die;
		}

		// validate project template
		if (array_key_exists("template", $options)) {
			$template = $options['template'];
		}

		// use --project flag or last param
		if ($options['project']) {
			$dest = $options['project'];
		} else {
			$dest = $argv[count($argv)-1];//realpath($argv[count($argv)-1]);
		}
	} else {
		$dest = $argv[1];
	}

	$dest = str_replace('~', getenv('HOME'), $dest);
	// make directory just in case so realpath doesn't return null
	@mkdir($dest);
	$dest = realpath($dest);

	// validate dest
	if (!file_exists($dest)) die("dest '$dest' doesn't exist.\n");
	if (!is_dir($dest)) die("dest '$dest' isn't a directory.\n");

	// options
	$project_name = array_key_exists('name', $options) ? $options['name'] : basename($dest);
	$program_name = array_key_exists('program', $options) ? $options['program'] : $project_name;
	$company = array_key_exists('company', $options) ? $options['company'] : 'my_company';
	$bundle_id = array_key_exists('bundleid', $options) ? $options['bundleid'] : "com.my_company.$project_name";
	
	// resolve template to the package
	$template = resolve_template_path($template);

	// show options
	print_header("Location", "$dest");
	print_header("Template", "$template");
	print_header("Name", "$project_name");

	if (is_template_xcode($template)) {
		print_header("Company", "$company");
		print_header("Bundle ID", "$bundle_id");
	}

	// final project file
	$project_file = "$dest/$project_name.sublime-project";
	if (file_exists($project_file))
		print_header("Project File:", "$project_file");

	// source files to insert
	$project_files = rscandir($template);

	// replace macros
	$project_macros = array(
		'${project-name}' => $project_name,
		'${program-name}' => $program_name,
		'${project-home}' => $dest,
		'${vscode}' => '.vscode',
		'${company}' => $company,
		// NOTE: on 10.15 Xcode stopped shipping lldb-mi so we need to reference it here
		// https://github.com/microsoft/vscode-cpptools/issues/3829
		'${lldb-mi}' => '/Users/ryanjoseph/Developer/Xcode_10_3.app/Contents/Developer/usr/bin/lldb-mi',
	);

	$xcode_macros = array(
		'__FPCPROJ_NAME' => $project_name,
		'__FPCPROJ_ORG_NAME' => $company,
		'__FPCPROJ_BUNDLE_IDENTIFIER' => $bundle_id,
		'__FPCPROJ_SHELL_SCRIPT' => '# Parasitic FPC binary injection\nset -e\ncp -f \"$SRCROOT/../$TARGET_NAME\" \"$BUILT_PRODUCTS_DIR/$EXECUTABLE_PATH\"'
	);

	$project_macros = array_merge($project_macros, $xcode_macros);

	// copy files and replace macros
	foreach ($project_files as $name) {

		// ignore hidden files
		if ($name[0] == '.') continue;

		$file_path = "$template/$name";
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