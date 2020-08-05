<?php

require('build.php');

// enable ansi color since we're running in the terminal
$enable_ansi_colors = true;

function show_help(): void {
	print("--config=\"name\": name of configuration to use (optional).\n");
	print("--target=\"name\": name of target to use (optional).\n");
	print("--program=\"name\": name of program file (optional, can override existing settings).\n");
	print("--codesign=(bool): enable code signing for project (optional).\n");
}

if (count($argv) == 1) {
	show_help();
	exit(1);
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

// find project path
$project_path = null;
$program_file = null;
for ($i=1; $i < count($argv); $i++) { 
	$option = $argv[$i];
	$extension = file_ext($option);

	switch ($extension) {

		case 'sublime-project':
			$fpcbuild = load_fpc_build($option);

			// override settings from command line
			$cmd = parse_cmd_options();

			if ($cmd['config'])
				$fpcbuild['configuration'] = $cmd['config'];
			if ($cmd['target'])
				$fpcbuild['target'] = $cmd['target'];

			// override target based settings
			if ($cmd['codesign'])
				$fpcbuild['targets'][$fpcbuild['target']]['codesign_enabled'] = true;
			if ($cmd['program'])
				$fpcbuild['targets'][$fpcbuild['target']]['program'] = $cmd['program'];

			// print_r($fpcbuild);
			// die;
			$clean_build = ($cmd['clean'] ? true : false);
			$build_variant = ($cmd['run'] ? BUILD_MODE_DEFAULT : BUILD_MODE_NO_RUN);

			run_project('', $option, $fpcbuild, $build_variant, $clean_build);
			break;
		
		case 'pas':
		case 'pp':
			run_quick_file($option);
			break;

		case 'lpi':
			run_lazarus($option);
			build_finished();
			break;

		default:
			break;
	}

}


?>