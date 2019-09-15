<?php

function run_command($command)
{
	//passthru($command, $exit_code);
	$exit_code = -1;
	$command = "$command";
	$ignored_errors = 0;
	if ($handle = popen("$command", "r")) {
		while (!feof($handle)) {
			$buffer = fgets($handle, 1024);
			// ignore lines
			if (preg_match("/^ld: warning:/i", $buffer)) {
				$ignored_errors++;
				continue;
			}
			if (preg_match("/^(warning|note):/", $buffer)) {
				$ignored_errors++;
				continue;
			}
			if (preg_match("/^(.*)\((\d+),(\d+)\)\s+(error|fatal)+:\s+(.*)$/i", $buffer, $results)) {
				$file = $results[1];
				$line = $results[2];
				$column = $results[3];
				$kind = $results[4];
				$message = $results[5];
				print("ERROR:$file:$line:$column:$message\n");
				continue;
			}
			
			echo $buffer;
		}
		$exit_code = pclose($handle);
		if ($ignored_errors > 0) {
			print("[Ignored $ignored_errors errors]\n");
		}
	} else {
		fatal("popen failed");
	}
	return $exit_code;
}

// load settings
$user_path = "/Users/".exec("/usr/bin/whoami");

// build
$project = $argv[2];
$file = $argv[1];


$file_name = basename($file);
$file_name = trim($file_name, ".pas");
$html = dirname($file)."/$file_name.html";

$root = "/Developer/pas2js";

// run command
$command = "$root/bin/i386-darwin/pas2js -Sc -vbl -Jc -Jirtl.js -Tbrowser -Fu$root/compiler/utils/pas2js/dist -Fu$root/packages/rtl $file";
print("[$command]\n");
$exit_code = run_command($command);
// passthru($command);

if ($exit_code == 0) {
	// exec("open -a Firefox $html");
} else {
	print("failed with exit code $exit_code\n");
	exit($exit_code);
}

?>