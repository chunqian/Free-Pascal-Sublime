<?php

// ================================================================================================
// CONSTANTS
// ================================================================================================

const WRAP_REPLACE_SYMBOL = "@";

const SYSTEM_IBTOOL = "/usr/bin/ibtool";
const SYSTEM_PLIST_BUDDY = "/usr/libexec/PlistBuddy";
const SYSTEM_XCRUN = "/usr/bin/xcrun";

const SETTING_COMMON = "common";
const SETTING_COMPILER = "compiler";
const SETTING_MACROS = "macros";
const SETTING_OPTIONS = "options";

const SETTING_SOURCE_PATHS = "source_paths";
const SETTING_RESOURCE_PATHS = "resource_paths";
const SETTING_FRAMEORK_PATHS = "framework_paths";
const SETTING_LIBRARY_PATHS = "library_paths";
const SETTING_INCLUDE_PATHS = "include_paths";

// SETTING CATEGORY KEYS

const SETTING_COMMON_CODESIGN_ENTITELMENTS = "codesign_entitlements";
const SETTING_COMMON_CODESIGN_IDENTITY = "codesign_identity";
const SETTING_COMMON_CODESIGN_ENABLED = "codesign_enabled";
const SETTING_COMMON_INFO_PLIST = "info.plist";
const SETTING_COMMON_PRODUCT_NAME = "product_name";
const SETTING_COMMON_PRODUCT_PATH = "product_path";
const SETTING_COMMON_TARGET = "target";
const SETTING_COMMON_BUNDLE = "bundle";
const SETTING_COMMON_MAIN = "main";

const SETTING_COMPILER_ARCHITECTURE = "architecture";
const SETTING_COMPILER_VERSION = "version";
const SETTING_COMPILER_SDK = "sdk";
const SETTING_COMPILER_PATH = "path";

const SETTING_IS_PATH = true;
const SETTING_REQUIRED = true;
const SETTING_OPTIONAL = false;

// ================================================================================================
// GLOBALS
// ================================================================================================

$settings = array();
$macros = array();

// ================================================================================================
// UTILITIES
// ================================================================================================

function rmovedir(string $src, string $dst): void { 
  $dir = opendir($src); 
  @mkdir($dst); 
  while(false !== ($file = readdir($dir))) { 
    if (($file != '.' ) && ($file != '..')) { 
      if (is_dir($src.'/'.$file)) { 
        rmovedir($src.'/'.$file, $dst.'/'.$file); 
      } else { 
        @rename($src.'/'.$file, $dst.'/'.$file); 
      } 
    } 
  } 
  closedir($dir); 
  rmdir($src);
}

function rcopydir(string $src, string $dst): void { 
  $dir = opendir($src); 
  @mkdir($dst); 
  while(false !== ($file = readdir($dir))) { 
    if (($file != '.' ) && ($file != '..')) { 
      if (is_dir($src.'/'.$file)) { 
        rcopydir($src.'/'.$file, $dst.'/'.$file); 
      } else { 
        copy($src.'/'.$file, $dst.'/'.$file); 
      } 
    } 
  } 
  closedir($dir); 
  copy($src, $dst);
}

function rrmdir(string $src): void {
  $dir = opendir($src);
  while(false !== ($file = readdir($dir))) {
    if (($file != '.') && ($file != '..')) {
      $full = $src.'/'.$file;
      if (is_dir($full)) {
        rrmdir($full);
      } else {
        unlink($full);
      }
    }
  }
  closedir($dir);
  rmdir($src);
}

// converts setting key to variable format
function keytolower(?string $str): string {
	if (!$str) return "";
	$str = str_replace(" ", "_", $str);
	$str = strtolower($str);
	$str = trim($str, "_");
	return $str;
}

function run_in_terminal($script) {
	// just to be sure
	$script = addslashes($script);
	$command = "/usr/bin/osascript <<EOF\n";
	$command .= "tell application \"Terminal\"\n";
	$command .= "	if (count of windows) is 0 then\n";
	$command .= "		do script \"$script\"\n";
	$command .= "	else\n";
	$command .= "		do script \"$script\" in window 1\n";
	$command .= "	end if\n";
	$command .= "	activate\n";
	$command .= "end tell\n";
	$command .= "EOF\n";
	exec($command);
}

function passthru_pattern($command, $pattern, $replacement) {
	$exit_code = -1;
	$command = "$command 2>&1 >/dev/null";
	$ignored_errors = 0;
	if ($handle = popen("$command", "r")) {
		while (!feof($handle)) {
			$buffer = fgets($handle, 1024);

			if ($results = preg_replace($pattern, $replacement, $buffer)) {
				echo $results;
			} else {
				echo $buffer;
			}
		}
		$exit_code = pclose($handle);
	} else {
		fatal("popen failed");
	}
	return $exit_code;
}

function osascript(string $command): string {
	$command = addslashes($command);
	return exec("osascript -e \"$command\"");
}

function ask_dialog(string $message, array $buttons): string {
	$names = null;
	foreach ($buttons as $name) {
		if ($names) $names .= ',';
		$names .= "\"$name\"";
	}
	$result = osascript('tell application (path to frontmost application as text) to display dialog "'.addslashes($message).'" buttons {'.$names.'} with icon stop');
	if (preg_match('/(\w+)$/', $result, $matches)) {
		return $matches[1];
	} else {
		return "";
	}
}

function compare_files ($a, $b) {
	if (!file_exists($a)) return false;
	if (!file_exists($b)) return false;
	return (filemtime($a) == filemtime($b));
}

function basename_no_ext($file) {
	$name = basename($file);
	$path_info = pathinfo($name);
	return $path_info["filename"];
}

function file_ext($path) {
	$info = pathinfo($path);
	return $info["extension"];
}

// ================================================================================================
// FUNCTIONS
// ================================================================================================

// the final stage in building is complete so exit the script
function build_finished(int $error_code=0): void {
	exit($error_code);
}

// standard fatal error to exit script
function fatal(string $error, bool $show_error = true): void {
	global $argv;
	print("FATAL: $error\n");
	// show an error which we can see in the current file
	if ($argv[1]) {
		if ($show_error) show_error($argv[1], $error, false);
		$e = new Exception;
		var_dump($e->getTraceAsString());
	}
	exit(-1);
}

// shows an error in FPC format which the build system can capture
function show_error(string $file, string $message, bool $fatal = true): void {
	print("$file:1: error: 0: $message\n");
	if ($fatal) fatal($message, false);
}

function add_setting(string $key, $value, array &$settings): void {
	switch ($key) {
		case SETTING_COMPILER:
		case SETTING_COMMON:
			if (is_array($value)) {
				foreach ($value as $k => $v) $settings[$key][$k] = $v;
			} else {
				$settings[$key] = $value;
			}
			break;
		case SETTING_MACROS:
			foreach ($value as $macro_key => $macro_value) {
				$settings[$key]["\$$macro_key"] = $macro_value;
			}
			break;
		case SETTING_OPTIONS:
			foreach ($value as $option) {
				// remove option from settings
				if ($option[0] == '*') {
					if ($option_key = array_search(substr($option, 1), $settings[$key])) {
						unset($settings[$key][$option_key]);
					}
				} else {
					$settings[$key][] = $option;
				}
			}
			break;
		default:
			if (is_array($value)) {
				// depending on setting type change type
				switch ($key) {
					case SETTING_RESOURCE_PATHS:
						foreach ($value as $option_key => $option_value) $settings[$key][$option_key] = $option_value;
						break;
					default:
						foreach ($value as $option) $settings[$key][] = $option;
						break;
				}
			} else {
				$settings[SETTING_COMMON][$key] = $value;
			}
			break;
	}
}

function load_settings (array $fpcbuild): array {
	
	if (!$settings) {
		$settings = array();
		$settings[SETTING_COMPILER] = array();
		$settings[SETTING_COMMON] = array();
		$settings[SETTING_MACROS] = array();
		$settings[SETTING_OPTIONS] = array();
	}

	// main requirments
	if (!$fpcbuild['target']) fatal('.fpcbuild must specifiy target.');
	if (!$fpcbuild['targets']) fatal('.fpcbuild must specifiy targets.');
	if (!$fpcbuild['configurations']) fatal('.fpcbuild must specifiy configurations.');
	if (!$fpcbuild['configuration']) fatal('.fpcbuild must specifiy configuration.');

	$settings[SETTING_COMMON]['target'] = $fpcbuild['target'];

	// configuration requirments
	$config = $fpcbuild['configurations'][$fpcbuild['configuration']];
	if (!$config) fatal("configuration '".$fpcbuild['configuration']."' not found.");

	// if (!$config['target']) fatal('configuration must specifiy a valid target.');

	// get the current target
	$target = $fpcbuild['targets'][$fpcbuild['target']];
	if (!$target) fatal('target "'.$fpcbuild['target'].'" doesn\'t exist.');

	// build ordered list of targets
	$order = array($fpcbuild['target']);
	while (true) {
		// inherit from parent
		$parent = $target['parent'];
		if ($parent) {
			array_unshift($order, $parent);
			$target = $fpcbuild['targets'][$parent];
			if (!$target) fatal("target '".$target['parent']."' can't be found.");
		} else {
			break;
		}
	}

	// add settings from target hierarchy
	foreach ($order as $target_name) {
		$target = $fpcbuild['targets'][$target_name];
		foreach ($target as $key => $value) {
			add_setting($key, $value, $settings);
		}
	}

	// add common settings from config
	foreach ($config as $key => $value) {
		add_setting($key, $value, $settings);
	}
	// print_r($settings);die;

	return $settings;
}

function resolve_macro($value) {
	global $macros;
	foreach ($macros as $macro_key => $macro_value) {
		$value = str_replace($macro_key, $macro_value, $value);
	}
	return $value;
}

function verify_setting(string $category, string $name = ""): bool {
	global $settings;
	if ($name != "") {
		$value = $settings[$category][$name];
	} else {
		$value = $settings[$category];
	}
	$value = resolve_macro($value);
	if ($value) {
		return true;
	} else {
		return false;
	}
}

function get_setting(string $category, string $name = "", bool $is_path = false, bool $required = SETTING_REQUIRED) {
	global $settings;
	if ($name != "") {
		$value = $settings[$category][$name];
	} else {
		$value = $settings[$category];
		// allow empty categories without warning
		if ($value == "") return null;
	}
	$value = resolve_macro($value);

	if (($is_path == SETTING_IS_PATH) && ($required) && (!file_exists($value))) fatal("Setting path '$value' for key '$name' doesn't exist.");
	if ($value == "") {
		if ($required) {
			fatal("Setting '$category/$name' is missing");
		} else {
			return null;
		}
	}
	return $value;
}

function get_paths(string $name, bool $as_string = false, string $wrap = "") {
	$settings = get_setting($name);
	if (!$settings) return null;

	$paths = array();
	foreach ($settings as $path) {
		if ($path == "") continue;
		$path = resolve_macro($path);
		require_file($path, "missing path '$path'");
		if ($wrap != "") {
			$path = str_replace(WRAP_REPLACE_SYMBOL, "$path", $wrap);
		}
		$paths[] = $path;
	}
	if ($as_string) {
		$paths = implode(" ", $paths); 
	}
	return $paths;
}

function get_setting_resources($name) {
	$settings = get_setting($name);
	if (!$settings) return null;

	$paths = array();
	foreach ($settings as $key => $value) {
		$path = resolve_macro($key);
		$dest = resolve_macro($value);

		require_file($path);
		// NOTE: we can't require the dest directory because it needs to be created first
		//require_file($dest);

		$paths[$path] = $dest;
	}
	return $paths;
}

function compile_metal ($src, $dest) {
	// xcrun -sdk macosx metal AAPLShaders.metal -o AAPLShaders.air
	// xcrun -sdk macosx metallib AAPLShaders.air -o AAPLShaders.metallib
	
	$src_dir = dirname($dest);
	$dest_dir = dirname($dest);
	$name = basename_no_ext($src);
	$air = "$src_dir/$name.air";
	$metal_error_pattern = '/^(.*\.metal):(\d+):(\d+):\s*(error):\s*(.*)$/i';
	// $1 = path
	// $2 = line
	// $3 = column
	// $5 = message
	$metal_error_replacement = '$1:$2: error: $3: $5';

	$command = SYSTEM_XCRUN." -sdk macosx metal \"$src\" -o \"$air\"";
	// passthru($command, $err);
	$err = passthru_pattern($command, $metal_error_pattern, $metal_error_replacement);
	if ($err != 0) fatal("failed to compile metal .air '$src'.");

	$command = SYSTEM_XCRUN." -sdk macosx metallib \"$air\" -o \"$dest\"";
	// passthru($command, $err);
	$err = passthru_pattern($command, $metal_error_pattern, $metal_error_replacement);
	if ($err != 0) fatal("failed to compile metal .metallib '$src'.");

	// delete temporary .air file
	if (file_exists($air)) unlink($air);
}

function compile_nib ($src, $dest) {
	$command = SYSTEM_IBTOOL." --errors --warnings --notices --output-format human-readable-text --compile \"$dest\" \"$src\" --flatten YES";
	//print("$command\n");
	passthru($command, $err);
	if ($err != 0) fatal("failed to compile $src.");
}

function is_resource_dir($path) {
	if (is_dir($path)) {
		return true;
	} else {
		$ext = file_ext($path);
		return (($ext == "framework") || ($ext == "bundle"));
	}
}

function copy_metal($src, $dest) {
	// copy src .metal to $dest directory .metallib
	$path_info = pathinfo($src);
	$new_dest = dirname($dest)."/".$path_info["filename"].".metallib";

	if (!compare_files($src, $new_dest)) {
		print("compile metal shader: $src -> $new_dest\n");
		@mkdir(dirname($dest));
		compile_metal($src, $new_dest);
		touch($new_dest, filemtime($src));
	}
}

function copy_nib($src, $dest, $ext) {
	$path_info = pathinfo($src);
	$new_dest = dirname($dest)."/".$path_info["filename"].".$ext";
	if (!compare_files($src, $new_dest)) {
		@mkdir(dirname($dest));
		compile_nib($src, $new_dest);
		touch($new_dest, filemtime($src));
	}
}

function copy_file($src, $dest) {
	$path_info = pathinfo($src);
	$ext = $path_info["extension"];

	if (strcasecmp($ext, "metal") == 0) {
		copy_metal($src, $dest);
		return false;
	}

	if ((strcasecmp($ext, "xib") == 0) || (strcasecmp($ext, "nib") == 0)) {
		copy_nib($src, $dest, "nib");
		return false;
	}

	if (strcasecmp($ext, "storyboard") == 0) {
		copy_nib($src, $dest, "storyboardc");
		return false;
	}

	if (!compare_files($src, $dest)) {
		//print("copy_file $src to $dest\n");
		//@mkdir(dirname($dest));
		if (!copy($src, $dest)) fatal("copy resource failed.");
		if (!touch($dest, filemtime($src))) fatal("copy resource failed.");
	}
}

function copy_link($src, $dest) {
	$link = readlink($src);
	@symlink($link, $dest);
}

function copy_resources($src, $dest) {
	if (is_resource_dir($dest)) {
		@mkdir($dest);
	}
	if (!is_resource_dir($src)) {
		copy_file($src, $dest."/".basename($src));
	} else {
		$files = scandir($src);
		foreach ($files as $name) {
			$path = $src."/".$name;
			if ($name != "." && $name != "..") {
				$file_dest = $dest."/".$name;
				if (is_link($path)) {
					copy_link($path, $file_dest);
				} elseif (is_resource_dir($path)) {
					//print("copy directory $path to $file_dest\n");
					@mkdir($file_dest);
					copy_resources($path, $file_dest);
				} else {
					copy_file($path, $file_dest);
				}
			}
		}
	}
}

function increment_build($info_plist) {
	$command = SYSTEM_PLIST_BUDDY." -c \"Print :CFBundleVersion\" \"$info_plist\"";
	$result = exec($command);
	print("CFBundleVersion: $result\n");

	$result += 1;
	$command = SYSTEM_PLIST_BUDDY." -c \"Set :CFBundleVersion $result\" \"$info_plist\"";
	$result = exec($command);
}

function replace_file_macros($file, $macros) {
	$str = file_get_contents($file);
	foreach ($macros as $key => $value) {
		$str = str_replace($key, $value, $str);
	}
	file_put_contents($file, $str);
}

function require_file($path, $message = "") {
	if (!file_exists($path)) {
		if ($message == "") {
			fatal("File '$path' can't be found.");
		} else {
			fatal($message);
		}
	}
}

function codesign_bundle ($bundle, $entitlements, $identity, $always = true) {
	if ($always) {
		$force = "-f";
	} else {
		$force = "";
	}

	// codesign frameworks
	$frameworks = "$bundle/Contents/Frameworks";
	if (file_exists($frameworks)) {
		$files = scandir($frameworks);
		foreach ($files as $name) {
			// hidden files
			if ($name[0] == ".") continue;
			$path = $frameworks."/".$name;
			$command = "codesign $force -s \"$identity\" \"$path\"";
			print("[$command]\n");
			passthru($command, $exit_code);
			if ($exit_code != 0) fatal("codesign failed.");
		}
	}

	// codesign bundle
	$command = "codesign $force --entitlements \"$entitlements\" -s \"$identity\" \"$bundle\"";
	print("[$command]\n");
	passthru($command, $exit_code);
	if ($exit_code != 0) fatal("codesign failed.");

	// verify 
	$command = "codesign --verify --deep --strict \"$bundle\"";
	print("[$command]\n");
	passthru($command, $exit_code);
	if ($exit_code != 0) fatal("Product '$bundle' failed codesign verification.");

	// verify gatekeeper
	$command = "spctl -a -vvvv \"$bundle\"";
	print("[$command]\n");
	passthru($command, $exit_code);
	if ($exit_code != 0) fatal("Product '$bundle' failed gatekeeper verification.");

}

// moves executable to /bin directory (as specified in .fpcbuild)
function move_to_bin(string $exec, array $macros, ?array $fpcbuild): string {
	if ($fpcbuild['bin']) {
		$bin = $fpcbuild['bin'];
		$bin = resolve_fpc_build_macro($macros, $bin);
		if (file_exists($bin)) {
			$dest = "$bin/".basename($exec);
			rename($exec, $dest);
			return $dest;
		} else {
			fatal("bin '$bin' doesn't exist.");
		}
	} else {
		return $exec;
	}
}

function make_bundle(string $binary, string $bundle, ?array $resource_paths): string {

	// make bundle
	@mkdir("$bundle");
	@mkdir("$bundle/Contents");
	@mkdir("$bundle/Contents/MacOS");
	@mkdir("$bundle/Contents/Frameworks");
	@mkdir("$bundle/Contents/Plugins");
	@mkdir("$bundle/Contents/Resources");
	@mkdir("$bundle/Contents/SharedSupport");

	// move binary to bundle
	$dest = "$bundle/Contents/MacOS/".basename($binary);
	// print("move $binary to $dest\n");
	@rename($binary, $dest);
	$binary = $dest;

	// copy info.plist
	$path = get_setting(SETTING_COMMON, SETTING_COMMON_INFO_PLIST, SETTING_IS_PATH);
	$dest = "$bundle/Contents/info.plist";
	increment_build($path);
	copy_file($path, $dest);
	replace_file_macros($dest, get_setting(SETTING_MACROS));

	// copy resources
	if ($resource_paths) {
		foreach ($resource_paths as $source => $dest) {
			copy_resources($source, $dest);
		}
	}
	
	// code sign
	if ($entitlements = get_setting(SETTING_COMMON, SETTING_COMMON_CODESIGN_ENTITELMENTS, SETTING_IS_PATH, SETTING_OPTIONAL)) {
		$identity = get_setting(SETTING_COMMON, SETTING_COMMON_CODESIGN_IDENTITY, false, SETTING_REQUIRED);
		$enabled = (bool)get_setting(SETTING_COMMON, SETTING_COMMON_CODESIGN_ENABLED, false, false);
		if ($enabled) codesign_bundle($bundle, $entitlements, $identity, true);
	}

	return $binary;
}

function build_library() {
	// https://stackoverflow.com/questions/22770389/create-a-statically-linkable-library-in-freepascal
	// fpc -Cn -Mdelphi xxx.dpr
	// ar -q libxxx.a `grep "\.o$" link.res`
	// ranlib libxxx.a
	// "library": {
	//   "parent": "base",
	//   "main": "$project/GLCanvasLibrary.pas",
	//   "product_name": "lib$product_name.a",
	//   "options": [
	//     "-Cn"
	//   ],
	//   "object_files": [
	//     "$output/BeRoPNG.o",
	//     "$output/DefinedClassesIOKit.o",
	//     "$output/GLCanvas.o"
	//   ]
	// },
}

// strips comments from json
function json_clean(string $contents) {
	$contents = preg_replace('/\/\*+(.*?)\*+\//s', '', $contents);
	$contents = preg_replace('/\/\/(.*?)\n/s', '', $contents);
	return $contents;
}

// TODO: merge this into load_fpc_build
function find_fpc_build(string $project): ?string {
	// search paths
	$paths = array(
		"$project/.fpcbuild",
	);
	foreach ($paths as $path) {
		if (file_exists($path)) {
			return $path;
		}
	}
	return null;
}

function run_command(string $command, bool $redirect_stdout = true): int {
	//passthru($command, $exit_code);
	print("[$command]\n");
	$exit_code = -1;
	if ($redirect_stdout) $command = "$command 2>&1 >/dev/null";
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
			// ignore errors in ppu files
			if (preg_match("/^(.*)\.ppu:(\w+)\.pp:(\d+)/", $buffer)) {
				$ignored_errors++;
				continue;
			}

			// TODO: this isn't getting captured, why not??
			// fileless error
			if (preg_match("/^(error|fatal)+: (.*)/", $buffer, $matches)) {
				echo "/main.pas:0: error: 0: ".$matches[2]."\n";
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

function lazbuild(string $project, string $options = ""): int {
	//passthru($command, $exit_code);
	$exit_code = -1;
	$command = "lazbuild \"$project\" $options";
	print("[$command]\n");
	if ($handle = popen("$command", "r")) {
		while (!feof($handle)) {
			$buffer = fgets($handle, 1024);
			// redirect laz errors to fpc style with -vbr
			// this is the usual format we expect in FPC.sublime-text
			if (preg_match("/^([\/]+.*)\((\d+),(\d+)\) (error|fatal)+: \((\d+)\) (.*)/i", $buffer, $matches)) {
				$error_path = $matches[1];
				$error_line = $matches[2];
				$error_column = $matches[3];
				$error_id = $matches[5];
				$error_message = $matches[6];
				echo "$error_path:$error_line: error: $error_column: $error_message\n";
				// echo $buffer;
			} else if (preg_match("/^(error|fatal)+: (.*)/i", $buffer, $matches)) {
				// fileless error
				echo $buffer;
			} else {
				echo $buffer;
			}
		}
		$exit_code = pclose($handle);
	} else {
		fatal("popen failed");
	}
	return $exit_code;
}

function default_fpc_options(): string {
	$options = array( "-vbr",
									  "-gw",
									  "-godwarfcpp",
									  "-XR".find_sdk_path()
										);
	return " ".implode(" ", $options);
}

function find_sdk_path(): string {
	$file = sys_get_temp_dir().'/system_sdk_path.txt';
	if (!file_exists($file)) {
		$sdk_path = exec('xcrun --show-sdk-path');
		file_put_contents($file, $sdk_path);
		return $sdk_path;
	}
	return file_get_contents($file);
}

function find_fpc_version(bool $latest): string {
	if ($latest) {
		$files = scandir('/usr/local/lib/fpc');
		$compilers = array();
		foreach ($files as $file) {
			if (preg_match('/\d+\.\d+\.\d+/', $file)) {
				$compilers[] = $file;
			}
		}
		rsort($compilers);
		return $compilers[0];
	} else {
		if (!$_ENV['FPC_STABLE_VERSION']) fatal("FPC_STABLE_VERSION must be set in shell profile.\n");
		return $_ENV['FPC_STABLE_VERSION'];
	}
}

function fpc_latest(bool $trunk = false): string {
	if ($trunk) {
		return '3.3.1';
	} else {
		return '3.0.4';
	}
}

function fpc($program, $version = "3.3.1", $arch = "ppcx64", $options = "") {

	// since PHP doesn't have oveloads if version is a full path 
	// then use this as the fpc compiler
	if (file_exists($version)) {
		$fpc = $version;
	} else {
		$fpc = "/usr/local/lib/fpc/$version/$arch";
		if (!file_exists($fpc)) {
			fatal("path to fpc '$fpc' doesn't exist.");
		}
	}

	$options .= default_fpc_options();

	// TODO: allow using other units
	$base = "/Users/ryanjoseph/Downloads/freepascal";
	$rtl = "-Fu$base/rtl/units/x86_64-darwin";
	// $packages = "-Fu$base/packages/rtl-extra/units/x86_64-darwin";
	$options .= " -CR $packages $rtl";

	$command = "$fpc $options \"$program\"";
	return run_command($command);
}

function build_and_run($compiler, $program, $options) {
	$options .= default_fpc_options();
	$command = "$compiler $program $options";
	if (run_command($command) == 0) {
		$name = basename_no_ext($program);
		$exec = dirname($program)."/$name";
		if (file_exists($exec)) {
			run_in_terminal($exec);
		} else {
			fatal("can't find executable $exec");
		}
	}
}

function prepare_debug_settings($project, $extra_commands = "") {

	// break points not found, bail!
	if (!file_exists("$project/Settings/bp.json")) return;

	// parse break points json to lldb command file
	if ($str = file_get_contents("$project/Settings/bp.json")) {
		$json = json_decode($str, true);
		$break_points = array();
		foreach ($json as $name => $lines) {
			foreach ($lines as $line) {
				$break_points[] = "b $name:$line";
			}
		}
		//print_r($break_points);
		$str = implode("\n", $break_points);
		file_put_contents("$project/Settings/break_points.txt", $str);
	}

	// merge break points and commands into final debug commands (--source for lldb)
	$break_points = file_get_contents("$project/Settings/break_points.txt");
	$commands = file_get_contents("$project/Settings/commands.txt");
	$commands .= "b fpc_raiseexception\n";
	$final = $break_points."\n".$commands;
	if ($extra_commands) $final = $final."\n".implode("\n", $extra_commands);
	file_put_contents("$project/Settings/debug.txt", $final);
}

function consume_token($handle, $token, $match_first, &$out_index, &$buffer, $maxlength) {
	$text = "";
	$curlen = 0;
	$curindex = ftell($handle);
	$buflen = 128;
	$out_index = NULL;
	$white_space = array(" ", "	");
	if ($buflen > $maxlength) {
		$buflen = $maxlength;
	}
	while ($text .= fread($handle, $buflen)) {
		for ($t=0; $t < strlen($text); $t++) {
			$buffer .= $text[$t];
			for ($i=0; $i < strlen($token); $i++) {
				// reading past text chunk, break before the current chunk
				// is reset so we can append the remaining string
				if ($t+$i > strlen($text)) break;

				if ($text[$t+$i] != $token[$i]) {
					// the first charater in the chunk isn't in the token
					// which means we broke the match_first rule
					if ($match_first && $t == 0) return false;
					break;
				}
			}
			if ($i == strlen($token)) {
				$out_index = $curindex + $i;
				// append remaining chars in buffer
				$buffer .= substr($text, $out_index, strlen($text) - $out_index);
				// print("found token $token at $out_index\n");
				return true;
			}
			$curindex += 1;
		}

		// clear chunk for next pass
		$text = "";
	}
	return false;
}

function parse_run_script($file) {
	$handle = fopen($file, "r");
	if ($handle) {
		$buffer = "";
		fseek($handle, 0);
		// search first 12 chars for start of script tag
    if (consume_token($handle, "(*", true, $start_index, $buffer, 12)) {
    	if (consume_token($handle, "*)", false, $end_index, $buffer, 1024)) {
    		// print("found scrip tag at $start_index/$end_index\n");
    		$script = substr($buffer, $start_index, ($end_index-$start_index)-3);
    		// print($script);
    		// declare globals which are available in eval()
    		$program = $GLOBALS["argv"][1];
    		$info = pathinfo($program);
    		$exec_name = $info["filename"];
    		$executable = $info["dirname"]."/".$info["filename"];
    		$dir = $GLOBALS["argv"][2];
    		// fallback to cwd for single files
    		if ($dir == "") $dir = getcwd();
    		eval($script);
    		return true;
    	}
    }
    fclose($handle);
	} else {
	  fatal("can't open file '$file' for reading.");
	} 
	return false;
}


function load_fpc_build(string $path): ?array {
	if (file_exists($path)) {
		$contents = file_get_contents($path);
		$contents = json_clean($contents);

		// replace macros
		$contents = str_replace('$dir', dirname($path), $contents);

		$json = json_decode($contents, true);
		if ($json == null) fatal("failed to decode json file (".json_last_error_msg().") '$path'.\n");

		// if we're loading from a sublime project then extract correct value
		if (file_ext($path) == "sublime-project") {
			$json = $json['settings']['fpcbuild'];
			// if ($json == null) fatal("failed to decode json file (".json_last_error_msg().") '$path'.\n");
		}

		// add built-in values
		if ($json) {
			$json['FPCBUILD_PATH'] = $path;
		}

		return $json;
	} else {
		return null;
	}
}

function try_to_load_fpc_build(?string $project_file, string $project_path, string $file = null, bool $no_program = false): ?array {
	
	// build list of paths to search for settings
	$paths = array(
		"$project_path/.fpcbuild",
	);
	if ($project_file) array_push($paths, $project_file);
	if ($file) array_push($paths, $file);

	foreach ($paths as $path) {
		if ($json = load_fpc_build($path)) {
			return $json;
		}
	}
	return null;
}

function resolve_fpc_build_macro(array $macros, string $value): string {
	foreach ($macros as $macro_key => $macro_value) {
		$value = str_replace($macro_key, $macro_value, $value);
	}
	return $value;
}

// merge custom options from .fpcbuild into an fpc command line string
function merge_fpc_build_options(string $options, array $macros, array $fpcbuild): string {
	if ($fpcbuild['output']) {
		$output = resolve_fpc_build_macro($macros, $fpcbuild['output']);
		$options .= " -FU\"$output\" ";
	}
	if ($fpcbuild['options']) {
		if (is_array($fpcbuild['options'])) {
			foreach ($fpcbuild['options'] as $line) {
				$line = resolve_fpc_build_macro($macros, $line);
				$options .= " $line";
			}
		} else {
			$line = resolve_fpc_build_macro($macros, $fpcbuild['options']);
			$options .= " $line";
		}
	}
	return $options;
}

function create_default_settings(string $parent, string $template, array $macros): bool {
	// open dialog to create new .fpcbuild in directory
	if (ask_dialog("Create new template at $parent/.fpcbuild?", array('No','Yes')) == 'Yes') {
		$contents = file_get_contents(__DIR__.'/'.$template);
		// replace settings macros
		foreach ($macros as $key => $value) {
			$contents = preg_replace('/\$\(('.$key.')\)/', $macros[$key], $contents);
		}
		$dest = "$parent/.fpcbuild";
		file_put_contents($dest, $contents);
		// exec("open $dest");
		return true;
	} else {
		return false;
	}
}

function create_project_settings(string $project_file, string $template, array $macros): void {
	print("creating new fpcbuild settings in project '$project_file'.\n");
	
	// load settings template
	$contents = file_get_contents(__DIR__.'/'.$template);
	$contents = json_clean($contents);
	// replace settings macros
	foreach ($macros as $key => $value) {
		$contents = preg_replace('/\$\(('.$key.')\)/', $macros[$key], $contents);
	}
	$template = json_decode($contents, true);

	// load sublime-project json
	$contents = file_get_contents($project_file);
	$data = json_decode($contents, true);
	$data["settings"]["fpcbuild"] = $template;

	$contents = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	file_put_contents($project_file, $contents);
	// if (!create_default_settings($project, 'settings/bundle_settings.json', $macros)) {
	// 	print("there are no build settings available for project\n");
	// 	exit;
	// }
}

function run_single_file(string $file, array $fpcbuild = null) {

	// sanity test
	if (!file_exists($file)) die("no input file!");

	// make macros
	if ($fpcbuild['macros']) {
		$macros = $fpcbuild['macros'];
	} else {
		$macros = array();
	}

	$macros['$dir'] = dirname($fpcbuild['FPCBUILD_PATH']);
	$macros['$fpc_latest'] = fpc_latest();

	// default single file behavior
	if ($fpcbuild['compiler']) {
		$fpc = resolve_fpc_build_macro($macros, $fpcbuild['compiler']);
	} else {
		$version = fpc_latest();
		$arch = "ppcx64";
		$fpc = "/usr/local/lib/fpc/$version/$arch";
	}
	if (!file_exists($fpc)) {
		print("compiler '$fpc' doesn''t exist, reverting to default.\n");
		$fpc = "/usr/local/bin/fpc";
	}
	$options = default_fpc_options();

	// TODO: move this into default_fpc_options so we have access to the array first
	if ($fpcbuild) $options = merge_fpc_build_options($options, $macros, $fpcbuild);
	
	$command = "$fpc $options \"$file\"";

	// run command
	$exit_code = run_command($command);
	if ($exit_code == 0) {
		$program_name = pathinfo($file)['filename'];
		$cwd = getcwd();
		$exec = $cwd.'/'.$program_name;
		// move exec to bin
		$exec = move_to_bin($exec, $macros, $fpcbuild);
		$command = $exec;
		// add 'env' string to command
		if ($fpcbuild['env']) {
			$env = implode(" ", $fpcbuild['env']);
			$command = "$command $env";
		}
		run_in_terminal($command);
	} else {
		print("failed with exit code $exit_code\n");
		build_finished($exit_code);
	}
}

function run_lazarus(string $project_path, ?string $project_file): void {

	// use cwd if project doesn't exist
	if (file_exists($project_path)) {
		$dir = $project_path;
	} else {
		$dir = getcwd();
	}

	$settings_macros = array(
		'project-name' => basename($dir),
		'project-path' => dirname($dir)
	);

	if (file_exists($project_file)) {
		$json = try_to_load_fpc_build($project_file, $dir);
		if (!$json) {
			create_project_settings($project_file, 'settings/lazarus_settings.json', $settings_macros);
			$json = try_to_load_fpc_build($project_file, $dir);
		}
	} else {
		// create a default .fpcbuild for lazarus
		if (!find_fpc_build($dir)) {
			$files = scandir($dir);
			foreach ($files as $file) {
				if (file_ext($file) == 'lpi') {
					$settings_macros['project-name'] = basename_no_ext($file);
					break;
				}
			}
			create_default_settings($dir, 'settings/lazarus_settings.json', $settings_macros);
		}
		$json = try_to_load_fpc_build(null, $dir);
	}


	if ($json) {		

		// $json['project'] = str_replace("\$dir", $dir, $json['project']);
		// $json['binary'] = str_replace("\$dir", $dir, $json['binary']);

		if ($json['options']) {
			$options = implode(" ", $json['options']);
		} else {
			$options = "";
		}

		// TODO: should we keep supporting "output" for lazarus?
		if ($json['output']) {
			// make build dir
			$output = $json['output'];
			if (!file_exists($output)) mkdir($output);
			// clean output before building
			if ($json['clean'] && file_exists($output)) {
				foreach (scandir($output) as $file) {
					if (in_array(file_ext($file), array('o','ppu'))) {
						unlink("$output/$file");
					}
				}
			}
		}

		if (lazbuild($json['project'], $options) == 0) {
			if ($json['mode'] == "terminal") {
				run_in_terminal($json['binary']);
			} else if ($json['mode'] == "console") {
				run_command($json['binary']);
			} else {
				fatal("invalid build mode ".$json['mode']);
			}
		}
	} else {
		// search for .lpi in directory 
		$files = scandir($dir);
		foreach ($files as $file) {
			$ext = file_ext($file);
			if ($ext == 'lpi') {
				lazbuild("$dir/$file", $options);
				return;
			}
		}
		fatal("no .lpi project file found in '$dir'");
	}
}

function run_build_script(string $file, string $project_file, array $fpcbuild) {

	// change directory to the current file
	$dir = dirname($project_file);
	chdir($dir);

	// declare dynamic variables using $args
	if ($fpcbuild['args']) {
		foreach ($fpcbuild['args'] as $key => $value) {
			${"$key"} = $value;
		}
	}

	// if there was no $program argument specific then use the current file
	if (!$program) $program = $file;

	// show_error($file,"run build script at ".$fpcbuild['build_script']);

	// declare globals which are available in eval()
	$info = pathinfo($program);
	$exec_name = $info["filename"];
	$executable = $info["dirname"]."/".$info["filename"];

	$build_script = $fpcbuild['build_script'];
	print("running build script ".getcwd()."/$build_script\n");

	require($build_script);
	build_finished();
}

function run_project(string $file, string $project_file, ?array $fpcbuild, ?string $build_variant): void {
	global $macros;
	global $settings;

	$project = dirname($project_file);

	require_file($project, "Missing project '$project'");
	print("Load project '$project'\n");

	// $target = find_target($project);

	$user_path = "/Users/".exec("/usr/bin/whoami");
	$run_after_build = true;
	$clean_build = false;

	// setup default macros
	$macros = array(
		'~' => $user_path,
		'$project' => $project,
		'$parent' => dirname($project)
	);

	// macros which are replaced in new settings file
	$settings_macros = array(
		'program-file' => basename_no_ext($file)
	);

	// load fpc build if it wasn't provided
	if (!$fpcbuild) $fpcbuild = load_fpc_build($project_file);

	if (!$fpcbuild) {
		create_project_settings($project_file, 'settings/bundle_settings.json', $settings_macros);
		$fpcbuild = load_fpc_build($project_file);

		/*
		print("creating new fpcbuild settings in project '$project_file'.\n");
		// load settings template
		$contents = file_get_contents(__DIR__.'/settings/bundle_settings.json');
		$contents = json_clean($contents);
		// replace settings macros
		foreach ($settings_macros as $key => $value) {
			$contents = preg_replace('/\$\(('.$key.')\)/', $settings_macros[$key], $contents);
		}
		$template = json_decode($contents, true);

		// load sublime-project json
		$contents = file_get_contents($project_file);
		$data = json_decode($contents, true);
		$data["settings"]["fpcbuild"] = $template;

		$contents = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
		file_put_contents($project_file, $contents);
		// if (!create_default_settings($project, 'settings/bundle_settings.json', $settings_macros)) {
		// 	print("there are no build settings available for project\n");
		// 	exit;
		// }
		*/
	}

	$settings = load_settings($fpcbuild);
	// print_r($settings);
	$macros = array_merge($macros, $settings["macros"]);

	// common
	$target_name = get_setting(SETTING_COMMON, SETTING_COMMON_TARGET);
	$product_name = get_setting(SETTING_COMMON, SETTING_COMMON_PRODUCT_NAME);
	$product_path = get_setting(SETTING_COMMON, SETTING_COMMON_PRODUCT_PATH);
	$target_config = $fpcbuild['configuration'];
	$main = get_setting(SETTING_COMMON, SETTING_COMMON_MAIN, true);
	$bundle = get_setting(SETTING_COMMON, SETTING_COMMON_BUNDLE, true, false);

	// add extra macros from settings
	if ($main) $macros['$main'] = $main;
	if ($bundle) $macros['$bundle'] = $bundle;

	// compiler
	$arch = get_setting(SETTING_COMPILER, SETTING_COMPILER_ARCHITECTURE);
	$version = get_setting(SETTING_COMPILER, SETTING_COMPILER_VERSION);
	$system_sdk = get_setting(SETTING_COMPILER, SETTING_COMPILER_SDK, SETTING_IS_PATH);

	// paths
	$source_paths = get_paths(SETTING_SOURCE_PATHS, true, "-Fu\"".WRAP_REPLACE_SYMBOL."\"");
	$resource_paths = get_setting_resources(SETTING_RESOURCE_PATHS);
	$framework_paths = get_paths(SETTING_FRAMEORK_PATHS, true, "-Ff\"".WRAP_REPLACE_SYMBOL."\"");
	$library_paths = get_paths(SETTING_LIBRARY_PATHS, true, "-Fl\"".WRAP_REPLACE_SYMBOL."\"");
	$include_paths = get_paths(SETTING_INCLUDE_PATHS, true, "-Fi\"".WRAP_REPLACE_SYMBOL."\"");

	// define built-in macros for compiler path
	$macros['$compiler_version'] = $version;
	$macros['$compiler_arch'] = $arch;

	if ($compiler_path = get_setting(SETTING_COMPILER, SETTING_COMPILER_PATH, true, SETTING_OPTIONAL)) {
		$fpc = $compiler_path;
	} else {
		$fpc = "/usr/local/lib/fpc/$version/$arch";
	}

	if (!file_exists($fpc)) {
		fatal("can't find compiler at $fpc");
	}
	$output = "$project/$target_name.$target_config.$arch";

	// build final options string
	$options = get_setting(SETTING_OPTIONS);

	// add additional options from settings
	if ($min_system_version = get_setting(SETTING_COMPILER, 'minimum_system_version')) {
		// -k"-macosx_version_min $min_system_version"
		$options[] = "-WM$min_system_version";
	}

	$options[] = "-FU\"$output\"";
	$options[] = "-XR\"$system_sdk\"";
	$options[] = "-o\"$product_path\"";

	$options = @implode(" ", $options);

	// clean output directory
	if ($clean_build && file_exists($output)) {
		print("Cleaning output directory at '$output'...");
		rrmdir($output);
	}

	// make output directory
	@mkdir($output);

	// build
	// NOTE: -k"-macosx_version_min $min_system_version" is being replaced by -WM10.10 option
	$command = <<<command
$fpc "$main" $options $source_paths $framework_paths $library_paths $include_paths
command;
	$command = trim($command);

	// run command
	$exit_code = run_command($command);

	if ($exit_code == 0) {
		$path_info = pathinfo($main);

		// verify the binary/executable location
		$binary = $product_path;
		if (!file_exists($binary)) {
			fatal("compiled binary doesn't exist '$binary'");
		}

		// move dSYM to output directory
		$dsym = dirname($product_path).'/'.basename_no_ext($product_path).".dSYM";
		if (file_exists($dsym)) {
			$dest = $output."/".basename($dsym);
			rmovedir($dsym, $dest);
		}

		// make the actual bundle if the target is of type 'bundle'
		if ($target_name == 'bundle') {
			$binary = make_bundle($binary, $bundle, $resource_paths);
		}

		// run the executable
		if ($run_after_build) {

			// TODO: add env common setting to $binary string

			if ($build_variant == "debug") {
				prepare_debug_settings($project, array("r"));
				$debug_commands = "$project/Settings/debug.txt";
				if (file_exists($debug_commands)) {
					run_in_terminal("/usr/bin/lldb --source \"$debug_commands\" $binary");
				} else {
					run_in_terminal("/usr/bin/lldb $binary");
				}
			} else if ($build_variant == "vscode") {
				// $vscode_project = "";
				$command = "open -a \"Visual Studio Code\"";
				print("[$command]\n");
				passthru($command, $exit_code);
				build_finished(0);
			} else {
				run_in_terminal($binary);
			}
		}
		
	} else {
		print("failed with exit code $exit_code\n");
		build_finished($exit_code);
	}
}

// runs a program file quickly and saves output to temporary location
// meant to be used with shell alias "fpcbuild" as a quick replacemnet
// for "fpc" on the commandline
function run_quick_file(string $file): void {
	
	// sanity test
	if (!file_exists($file)) die("no input file!");

	$temp = sys_get_temp_dir().'/fpc';
	@mkdir($temp);

	$executable = $temp.'/'.basename_no_ext($file);
	$sdk_path = find_sdk_path();
	$version = find_fpc_version(true);
	$options = array( "-vbr",
									  // "-gw",
									  // "-godwarfcpp",
									  "-XR$sdk_path",
									  "-o$executable"
										);
	$options = implode(" ", $options);

	$arch = "ppcx64";
	$fpc = "/usr/local/lib/fpc/$version/$arch";

	if (!file_exists($fpc)) {
		print("compiler '$fpc' doesn''t exist, reverting to default.\n");
		$fpc = "/usr/local/bin/fpc";
	}
	
	$command = "$fpc $options \"$file\"";

	if (run_command($command) == 0) {
		run_in_terminal($executable);
		// passthru($executable);
	}

	build_finished();
}

function show_inputs() {
	global $argv;
	global $file;
	global $project_path;
	global $project_file;

	print_r($argv);
	print("\$file: $file\n");
	print("\$project_path: $project_path\n");
	print("\$project_file: $project_file\n");
}

// ================================================================================================
// MAIN
// ================================================================================================

// TODO: we broke command line options which we need for fpc app build scripts
$longopts  = array(
    "target::",
    "laz",
    "quick::"
);
if ($cmd = getopt('t:l', $longopts)) {
	// print_r($cmd);
	// print_r($argv);
	// last argv is always the project path
	$project_path = $argv[count($argv) - 1];
	if (!file_exists($project_path))
		fatal("project path '$project_path' doesn't exist.");
	
	if (array_key_exists('laz', $cmd)) {
		run_lazarus($project_path);
		build_finished();
	} else if (array_key_exists('quick', $cmd)) {
		run_quick_file($project_path);
	} else {
		// nothing to do
		build_finished();
	}

}

// TODO: make an alias for 'fpcbuild' and use a single file run that moves binary to temp file (like pps)
// TODO: make a proper fpc_latest lookup OR $FPC_VERSION variable to put into .bash_profile so we can set
// the correct version for the entire shell

// must supply command line arguments
if (count($argv) == 1) {
	die("no options provided.\n");
}

$file = $argv[1];						// path to current file
$project_path = $argv[2];		// path to directory containing sublime-project file
$project_file = $argv[3];		// path to *.sublime-project file
$build_variant = $argv[4];	// sublime-build "variants" key

// show_inputs();

// run as lazarus project
if ($build_variant == "lazarus") {
	run_lazarus($project_path, $project_file);
	build_finished();
}

// parse run script from header
if (file_exists($file)) {
	if (parse_run_script($file)) build_finished();
}

// try to use .fpcbuild in the project directory
if (file_exists($project_file)) {
	
	$fpcbuild = load_fpc_build($project_file);
	// if there is a build_script defined then override the project settings
	if ($fpcbuild['build_script']) {
		run_build_script($file, $project_file, $fpcbuild);
	} else {
		run_project($file, $project_file, $fpcbuild, $build_variant);
	}
} else {
	// TODO: killing this for now because I'm not sure .fpcbuild for single files
	// makes anysense at all. thinking about maybe making meta data editor to add plists
	// $parent = dirname($file);
	// if (!find_fpc_build($parent)) {
	// 	if (!create_default_settings($parent, 'settings/fpc_settings.json', array())) {
	// 		die("must have a .fpcbuild to run\n");
	// 	}
	// }
	// $fpcbuild = try_to_load_fpc_build(null, $parent, $file);
	// run_single_file($file, $fpcbuild);
	// build_finished(0);
	run_single_file($file);
	build_finished(0);
}


?>