<?php

// ================================================================================================
// CONSTANTS
// ================================================================================================

const WRAP_REPLACE_SYMBOL = "@";

const SYSTEM_IBTOOL = "/usr/bin/ibtool";
const SYSTEM_PLIST_BUDDY = "/usr/libexec/PlistBuddy";
const SYSTEM_XCRUN = "/usr/bin/xcrun";

// SETTING CATEGORIES

const SETTING_COMMON = "common";
const SETTING_COMPILER = "compiler";
const SETTING_MACROS = "macros";
const SETTING_OPTIONS = "options";
const SETTING_XCODEBUILD = "xcodebuild";

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
const SETTING_COMMON_PROGRAM = "program";
const SETTING_COMMON_PLATFORM = "platform";

// macos only
const SETTING_COMMON_BUNDLE = "bundle";

const SETTING_COMPILER_ARCHITECTURE = "architecture";
const SETTING_COMPILER_VERSION = "version";
const SETTING_COMPILER_SDK = "sdk";
const SETTING_COMPILER_PATH = "path";
const SETTING_COMPILER_MINIMUM_SYSTEM_VERSION = "minimum_system_version";

const SETTING_XCODEBUILD_PROJECT = "project";
const SETTING_XCODEBUILD_SCHEME = "scheme";
const SETTING_XCODEBUILD_PRODUCT = "product";
const SETTING_XCODEBUILD_LAUNCH = "launch";

const SETTING_IS_PATH = true;
const SETTING_REQUIRED = true;
const SETTING_OPTIONAL = false;

const BUILD_MODE_DEFAULT = 'default';			// run fpcbuild project
const BUILD_MODE_DEBUG = 'debug';					// build with debug configuration and run with lldb
const BUILD_MODE_VSCODE = 'vscode';				// open VSCode after building
const BUILD_MODE_LAZARUS = 'lazarus';			// run project with lazbuild and .fpcbuild file for settings
const BUILD_MODE_QUICK = 'quick';					// build with standard settings and output to temp directory
const BUILD_MODE_NO_RUN = 'build-only';		// build but do not run


const PLATFORM_DARWIN = 'darwin';
const PLATFORM_WINDOWS = 'windows';
const PLATFORM_LINUX = 'linux';
const PLATFORM_IPHONE = 'iphone';
const PLATFORM_IPHONE_SIMULATOR = 'iphonesim';

// ================================================================================================
// Makefile
// ================================================================================================

class Makefile {
	private $rules = array( 'clean' => array(),
													'all' => array(),
													'install' => array()
												);
	private $root = '';
	private $install_rule = null;

	function push(string $rule, string $command): void {
		$command = str_replace($this->root, '.', $command);
		printc(ANSI_FORE_YELLOW, $command."\n");
		$this->rules[$rule][] = $command;
	}

	function write_to_file(string $path): void {
		foreach ($this->rules as $name => $commands) {
			$output .= "$name:\n";
			foreach ($commands as $command) {
				$output .= "	$command\n";
			}
		}
		file_put_contents($path, $output);
	}

	function __construct(string $root) {
		$this->root = $root;
	}
}

// ================================================================================================
// GLOBALS
// ================================================================================================

$settings = array();
$macros = array();
$enable_ansi_colors = false;
$shared_makefile = null;
$target_platform = strtolower(PHP_OS_FAMILY);

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
	global $enable_ansi_colors;
	if ($enable_ansi_colors) {
		print("\033[".$color."m".$text."\033[0m\n");
	} else {
		print($text);
	}
}

function run_in_simulator(string $bundle_path, string $bundle_id): void {
	$command = "open -a Simulator.app\n";
	$command .= SYSTEM_XCRUN." simctl terminate booted $bundle_id\n";
	$command .= SYSTEM_XCRUN." simctl install booted \"$bundle_path\"\n";
	$command .= SYSTEM_XCRUN." simctl launch booted $bundle_id\n";

	// TODO: make this an option since it's instrusive
	// https://shashikantjagtap.net/simctl-control-ios-simulators-command-line/
	// show debug log
	// $command .= 'tail -f `xcrun simctl getenv booted SIMULATOR_LOG_ROOT`/system.log\n';

	// print($command);
	run_in_terminal($command);
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

function xcodebuild(string $project, string $scheme, string $sdk) {
	$command = 'xcodebuild -project "'.$project.'" -scheme '.$scheme.' -sdk '.$sdk;
	passthru2($command, $exit_code, true);
	return $exit_code;
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

function error_dialog(string $message): void {
	$result = osascript('tell application (path to frontmost application as text) to display dialog "'.addslashes($message).'" buttons {"Ok"} with icon stop');
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

function passthru2(string $command, ?int &$exit_code, bool $makefile_eligable = false): void {
	printc(ANSI_FORE_BLUE, "[$command]\n");
	if ($makefile_eligable) push_makefile($command);
	passthru($command, $exit_code);
	if ($exit_code != 0) fatal("command failed ($command).");
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
function build_finished(int $error_code = 0): void {
	exit($error_code);
}

// standard fatal error to exit script
function fatal(string $error, bool $show_trace = true, bool $show_dialog = true): void {
	global $argv;
	printc(ANSI_BACK_RED, "FATAL: $error\n");
	if ($show_trace) {
		$e = new Exception;
		var_dump($e->getTraceAsString());
	}
	if ($show_dialog) error_dialog($error);
	exit(-1);
}

// shows an error in FPC format which the build system can capture
function show_error(string $file, string $message, bool $fatal = true): void {
	print("$file:1: error: 0: $message\n");
	if ($fatal) fatal($message, true, false);
}

function add_setting(string $key, $value, array &$settings): void {
	switch ($key) {
		case SETTING_XCODEBUILD:
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
				// macros have two formats:
				// 1) $NAME
				// 2) $(NAME)
				$settings[$key]["\$$macro_key"] = $macro_value;
				$settings[$key]["\$($macro_key)"] = $macro_value;
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
	
	// default settings
	$settings = array();
	$settings[SETTING_COMPILER] = array();
	$settings[SETTING_COMMON] = array();
	$settings[SETTING_MACROS] = array();
	$settings[SETTING_OPTIONS] = array();
	$settings[SETTING_XCODEBUILD] = array();

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
			if (!$target) fatal("parent target '$parent' can't be found.");
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

function get_macro(string $name, bool $resolve = true, bool $required = true, string $message = '') {
	global $macros;
	// try both macro formats
	$value = $macros['$'.$name];
	if (!$value) 
		$value = $macros['$('.$name.')'];
	if ($required && $value == null) {
		if ($message) 
			fatal("$message (macro '$name' doesn't exist)");
		else
			fatal("macro '$name' doesn't exist.");
	}
	if ($resolve) 
		$value = resolve_macro($value);
	return $value;
}

function resolve_macro($value) {
	global $macros;
	global $project_path;

	foreach ($macros as $macro_key => $macro_value) {
		$value = str_replace($macro_key, $macro_value, $value);
	}

	// replace system macros
	$value = preg_replace('/^~/', $_ENV['HOME'], $value);
	$value = preg_replace('/^\./', getcwd(), $value);

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


	if (($is_path == SETTING_IS_PATH) && ($required) && (!file_exists($value))) {
		if ($value) {
			fatal("Setting path '$value' for key '$name' doesn't exist.");
		} else {
			fatal("Required setting key '$name' for category '$category' doesn't exist.");
		}
	}
	if ($value == "") {
		if ($required) {
			fatal("Required setting key '$name' for category '$category' doesn't exist.");
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
	passthru2($command, $err, true);
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

function push_makefile(string $command): void {
	global $shared_makefile;
	if (!$shared_makefile) return;
	$shared_makefile->push('all', $command);
}

function make_dir(string $path): void {
	push_makefile("mkdir -p $path");
	@mkdir($path);
}


function move_file(string $binary, string $dest): void {
	push_makefile("mv -f $binary $dest");
	@rename($binary, $dest);
}

function copy_metal($src, $dest) {
	// copy src .metal to $dest directory .metallib
	$path_info = pathinfo($src);
	$new_dest = dirname($dest)."/".$path_info["filename"].".metallib";

	if (!compare_files($src, $new_dest)) {
		print("compile metal shader: $src -> $new_dest\n");
		make_dir(dirname($dest));
		// compile_metal($src, $new_dest);
		touch($new_dest, filemtime($src));
	}
}

function copy_nib($src, $dest, $ext) {
	$path_info = pathinfo($src);
	$new_dest = dirname($dest)."/".$path_info["filename"].".$ext";
	if (!compare_files($src, $new_dest)) {
		make_dir(dirname($dest));
		compile_nib($src, $new_dest);
		touch($new_dest, filemtime($src));
	}
}

function copy_file($src, $dest, bool $makefile_eligable = true) {
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
		// TODO: this doesn't copy file permissions! try to copy untrunc binary and see what happens
		if (!copy($src, $dest)) fatal("copy resource failed ($src -> $dest).");
		if (!touch($dest, filemtime($src))) fatal("copy resource failed ($src -> $dest).");
		if ($makefile_eligable) push_makefile("cp $src $dest");
	}
}

function copy_link($src, $dest) {
	$link = readlink($src);
	// TODO: add this to makefile
	// ln -s "$src" "$dest"
	@symlink($link, $dest);
}

function copy_resources($src, $dest) {
	if (is_resource_dir($dest)) {
		make_dir($dest);
	}
	if (!is_resource_dir($src)) {
		copy_file($src, $dest."/".basename($src));
	} else {
		// if source folder has trailing / than copy entire directory structure
		if ($src[strlen($src) - 1] == '/') {
			$name = basename($src);
			$dest = "$dest/$name";
			make_dir($dest);
		}
		$files = scandir($src);
		foreach ($files as $name) {
			$path = $src."/".$name;
			if ($name != "." && $name != "..") {
				$file_dest = $dest."/".$name;
				if (is_link($path)) {
					copy_link($path, $file_dest);
				} elseif (is_resource_dir($path)) {
					make_dir($file_dest);
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
	// always accept wildcard pattern
	if (basename($path) == '*') return;
	if (!file_exists($path)) {
		if ($message == "") {
			fatal("File '$path' can't be found.");
		} else {
			fatal($message);
		}
	}
}

function find_symlinks (string $dir): int {
	// ls -la shows links in terminal
	$errors = 0;
	$files = scandir($dir);
	foreach ($files as $name) {
		if ($name[0] == ".") continue;
		$path = $dir."/".$name;
		if (is_link($path)) {
			$link = readlink($path);
			$full = "$dir/$link";
			//print("$full\n");
			if (!file_exists($full)) {
				printc("WARNING: $path -> $full\n");
				$errors++;
			}
			continue;
		}
		if (is_dir($path)) find_symlinks($path);
	}
	return $errors;
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
			// print("[$command]\n");
			passthru2($command, $exit_code, true);
			if ($exit_code != 0) fatal("codesign failed.");
		}
	}

	// codesign bundle
	// note: add hardened runtime for notarization in Catalina
	$hardened_runtime_options = "--options=runtime --timestamp";
	passthru2("codesign $force --deep $hardened_runtime_options --entitlements \"$entitlements\" -s \"$identity\" \"$bundle\"", $exit_code, true);

	// verify 
	passthru2("codesign --verify --deep --strict \"$bundle\"", $exit_code, true);
	passthru2("codesign -dv --verbose=4 \"$bundle\"", $exit_code, true);	

	// verify gatekeeper
	passthru2("spctl -a -vvvv \"$bundle\"", $exit_code, true);
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

function make_bundle(string $platform, string $binary, string $bundle, ?array $resource_paths): string {
	global $shared_makefile;

	// Bundle Structures:
	// https://developer.apple.com/library/archive/documentation/CoreFoundation/Conceptual/CFBundles/BundleTypes/BundleTypes.html
	print("make bundle $bundle.\n");

	if ($platform == PLATFORM_DARWIN) {
		make_dir("$bundle");
		make_dir("$bundle/Contents");
		make_dir("$bundle/Contents/MacOS");
		make_dir("$bundle/Contents/Frameworks");
		make_dir("$bundle/Contents/Resources");

		$binary_dest = "$bundle/Contents/MacOS/".basename($binary);
		$info_plist_dest = "$bundle/Contents/Info.plist";
	} elseif ($platform == PLATFORM_IPHONE || $platform == PLATFORM_IPHONE_SIMULATOR) {
		make_dir("$bundle");
		$binary_dest = "$bundle/".basename($binary);
		$info_plist_dest = "$bundle/Info.plist";
	} else {

		return $binary;
	}

	// TODO: -o should move this directly into the bundle! we're doing it backwards still...
	// "product_path": "$project/$product_name" should be overriden for bundle targets
	// move binary to bundle
	move_file($binary, $binary_dest);
	$binary = $binary_dest;

	// copy info.plist
	$path = get_setting(SETTING_COMMON, SETTING_COMMON_INFO_PLIST, SETTING_IS_PATH);
	increment_build($path);
	copy_file($path, $info_plist_dest, false);
	replace_file_macros($info_plist_dest, get_setting(SETTING_MACROS));

	// if makefiles are enabled then copy the output info.plist
	// so it can be copied into the bundle later
	if ($shared_makefile) {
		$plist_name = basename($path).'.out';
		$plist_out = dirname($path).'/'.$plist_name;
		@copy($info_plist_dest, $plist_out);
		$shared_makefile->push('all', "cp -f $plist_out $info_plist_dest");
	}

	// copy resources
	if ($resource_paths) {
		foreach ($resource_paths as $source => $dest) {
			// match all files in directory
			if (basename($source) == '*') {
				$dir = dirname($source);
				$names = scandir($dir);
				foreach ($names as $name) {
					if ($name[0] == '.') continue;
					copy_resources("$dir/$name", $dest);
				}
			} else {
				copy_resources($source, $dest);
			}

		}
	}
	
	// code sign
	if ($entitlements = get_setting(SETTING_COMMON, SETTING_COMMON_CODESIGN_ENTITELMENTS, SETTING_IS_PATH, SETTING_OPTIONAL)) {
		$identity = get_setting(SETTING_COMMON, SETTING_COMMON_CODESIGN_IDENTITY, false, SETTING_REQUIRED);
		$enabled = (bool)get_setting(SETTING_COMMON, SETTING_COMMON_CODESIGN_ENABLED, false, false);
		if ($enabled) codesign_bundle($bundle, $entitlements, $identity, true);
	}

	// verify symlinks in bundle
	find_symlinks($bundle);

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

function run_command(string $command): int {
	printc(ANSI_FORE_BLUE, "[$command]\n");
	$exit_code = -1;
	// if ($redirect_stdout) $command = "$command 2>&1 >/dev/null";
	$ignored_errors = 0;
	$fatal_errors = 0;
	$fatal_error_message = "";
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

			// fatal linker errors
			// ld: library not found for libfreetype
			// ld: framework not found OpenGLES
			if (preg_match("/ld: (library|framework)+ not found .*/", $buffer)) {
				$fatal_errors += 1;
				$fatal_error_message = $buffer;
			}

			// TODO: this isn't getting captured, why not??
			// fileless error
			// if (preg_match("/^(error|fatal)+: (.*)/", $buffer, $matches)) {
			// 	echo "/main.pas:0: error: 0: ".$matches[2]."\n";
			// 	continue;
			// }

			echo $buffer;
		}
		$exit_code = pclose($handle);
		if ($ignored_errors > 0) {
			print("[Ignored $ignored_errors errors]\n");
		}
	} else {
		fatal("popen failed");
	}

	// if there was only 1 fatal error then show a message
	// otherwise just return an error code and continue
	if ($fatal_errors == 1) {
		fatal($fatal_error_message);
	} elseif ($fatal_errors > 0) {
		$exit_code = -2;
	}

	return $exit_code;
}

function lazbuild(string $project, string $options = ""): int {
	//passthru($command, $exit_code);
	$exit_code = -1;
	$command = "lazbuild \"$project\" $options";
	// print("[$command]\n");
	printc(ANSI_FORE_BLUE, "[$command]\n");
	if ($handle = popen("$command", "r")) {
		while (!feof($handle)) {
			$buffer = fgets($handle, 1024);
			// redirect laz errors to fpc style with -vbr
			// this is the usual format we expect in FPC.sublime-build
			// note: this patten is from FPC 3.0.4 and is not correct
			if (preg_match("/^([\/]+.*):(\d+): (error|fatal|warning|hint)+: (\d+): \((\d+)\) (.*)$/i", $buffer, $matches)) {
				$error_path = $matches[1];
				$error_line = $matches[2];
				$error_column = $matches[4];
				$error_message = $matches[6];
				$error_kind = strtolower($matches[3]);
				echo "$error_path:$error_line:$error_column: $error_kind: $error_message\n";
			} else if (preg_match("/^([\/]+.*)\((\d+),(\d+)\) (Error|Fatal|Warning|Hint)+: \((\d+)\) (.*)$/i", $buffer, $matches)) {
				$error_path = $matches[1];
				$error_line = $matches[2];
				$error_column = $matches[3];
				$error_message = $matches[6];
				$error_kind = strtolower($matches[4]);
				echo "$error_path:$error_line:$error_column: $error_kind: $error_message\n";
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

function xcode_path(): string {
	return '/Applications/Xcode.app';
}

function platform_sdk(): string {
	global $target_platform;
	if ($target_platform == PLATFORM_DARWIN) {
		return 'macosx';
	} elseif ($target_platform == PLATFORM_IPHONE_SIMULATOR) {
		return 'iphonesimulator';
	} elseif ($target_platform == PLATFORM_IPHONE) {
		return 'iphoneos';
	} else {
		fatal("can't find sdk for platform '$target_platform'");
	}
}

function find_sdk_path(): string {
	global $target_platform;
	$file = sys_get_temp_dir()."/${target_platform}_sdk_path.txt";
	if (!file_exists($file)) {
		$sdk_path = exec('xcrun --sdk '.platform_sdk().' --show-sdk-path');
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
	if (!$contents) fatal("invalid settings template '$template'.");

	// replace settings macros
	foreach ($macros as $key => $value) {
		$contents = preg_replace('/\$\(('.$key.')\)/', $macros[$key], $contents);
	}
	$template = json_decode($contents, true);
	if (!$template) fatal("template json failed to decode.");

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

/**
 * performs the 'xcodebuild' command for the active target
 *
 * @return path of the xcode product
 **/
function run_xcode_project(): string {
	$xcodeproj = get_setting(SETTING_XCODEBUILD, SETTING_XCODEBUILD_PROJECT, SETTING_IS_PATH);
	$exit_code = xcodebuild($xcodeproj, get_setting(SETTING_XCODEBUILD, SETTING_XCODEBUILD_SCHEME), platform_sdk());
	$xcode_product = get_setting(SETTING_XCODEBUILD, SETTING_XCODEBUILD_PRODUCT, SETTING_IS_PATH);
	// codesign --force is supposed to do this I thought but I need to touch it
	// otherwise io-deploy gives me an error
	passthru2("touch \"$xcode_product/_CodeSignature\"", $exit_code, true);
	return $xcode_product;
}

/**
 * Launches xcode project using xcodebuild->launch settings
 * options are "xcode" to run as if pressing the run button in xcode
 * or "terminal" which uses a custom command
 *
 * @return void
 **/
function launch_xcode_project($func): void {
	global $argv;
	$launch = get_setting(SETTING_XCODEBUILD, SETTING_XCODEBUILD_LAUNCH, false, SETTING_OPTIONAL);
	if (!$launch) {
		$launch = 'xcode';
		printc(ANSI_FORE_CYAN, "Xcode launch mode was not specifified ('xcode' or 'terminal'), '$launch' being used as default.\n");
	}
	$project = get_setting(SETTING_XCODEBUILD, SETTING_XCODEBUILD_PROJECT, SETTING_IS_PATH, true);
	if ($launch == 'xcode') {
		$dir = dirname($argv[0]);
		$name = 'xcode_build.applescript';
		$path = "$dir/scripts/$name";
		if (!file_exists($path)) fatal("xcode build script can't be found ($path).");
		passthru2("osascript \"$path\" $project", $exit_code);
	} elseif ($launch == 'terminal') {
		$func();
	} else {
		fatal(SETTING_XCODEBUILD.' -> launch must be either "xcode" or "terminal"');
	}
}

function run_single_file(string $file, array $fpcbuild = null) {

	// sanity test
	if (!file_exists($file)) fatal("no input file!");

	// make macros
	if ($fpcbuild['macros']) {
		$macros = $fpcbuild['macros'];
	} else {
		$macros = array();
	}

	$macros['$dir'] = dirname($fpcbuild['FPCBUILD_PATH']);
	$macros['$fpc_latest'] = find_fpc_version(true);

	// default single file behavior
	if ($fpcbuild['compiler']) {
		$fpc = resolve_fpc_build_macro($macros, $fpcbuild['compiler']);
	} else {
		$version = find_fpc_version(true);
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
			} else if ($json['mode'] == "build-only") {
				// do nothing
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

function run_project(string $file, string $project_file, ?array $fpcbuild, ?string $build_variant, bool $clean_build = false): void {
	global $argv;
	global $macros;
	global $settings;
	global $shared_makefile;
	global $target_platform;

	$project = dirname($project_file);

	require_file($project, "Missing project '$project'");
	print("* Project '$project'\n");

	$run_after_build = true;

	// setup default macros
	$macros = array(
		'$project' => $project,
		'$parent' => dirname($project),
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
	}

	$settings = load_settings($fpcbuild);
	// print_r($settings);

	// add macros from settings
	$macros = array_merge($macros, $settings['macros']);

	// enable the makefiles
	if ($fpcbuild['makefile'])
		$shared_makefile = new Makefile($project);

	// common
	$target_name = get_setting(SETTING_COMMON, SETTING_COMMON_TARGET);
	$macros['$target'] = $target_name;

	$product_name = get_setting(SETTING_COMMON, SETTING_COMMON_PRODUCT_NAME);
	$product_path = get_setting(SETTING_COMMON, SETTING_COMMON_PRODUCT_PATH);
	$target_config = $fpcbuild['configuration'];
	$program = get_setting(SETTING_COMMON, SETTING_COMMON_PROGRAM, SETTING_IS_PATH);
	$platform = get_setting(SETTING_COMMON, SETTING_COMMON_PLATFORM, false, SETTING_OPTIONAL);
	$bundle = get_setting(SETTING_COMMON, SETTING_COMMON_BUNDLE, SETTING_IS_PATH, SETTING_OPTIONAL);
	
	// use current platform
	// 'Windows', 'BSD', 'Darwin', 'Solaris', 'Linux' or 'Unknown'
	if (!$platform)
		$platform = strtolower(PHP_OS_FAMILY);

	// set the global target platform
	$target_platform = $platform;

	// add the sdk path macro once the target platform is established
	$macros['$sdk_full'] = find_sdk_path();
	$macros['$sdk_path'] = dirname($macros['$sdk_full']);

	print("* Target '$target_name'\n");
	print("* Configuration '$target_config'\n");
	print("* Plaforform '$platform'\n");

	// add extra macros from settings
	if ($program) $macros['$program'] = $program;
	if ($bundle) $macros['$bundle'] = $bundle;

	// compiler
	$arch = get_setting(SETTING_COMPILER, SETTING_COMPILER_ARCHITECTURE);
	$version = get_setting(SETTING_COMPILER, SETTING_COMPILER_VERSION);
	$system_sdk = get_setting(SETTING_COMPILER, SETTING_COMPILER_SDK, SETTING_IS_PATH, SETTING_OPTIONAL);
	
	// use sdk for current platform
	if (!$system_sdk)
		$system_sdk = find_sdk_path();

	// paths
	$source_paths = get_paths(SETTING_SOURCE_PATHS, true, "-Fu\"".WRAP_REPLACE_SYMBOL."\"");
	$resource_paths = get_setting_resources(SETTING_RESOURCE_PATHS);
	$framework_paths = get_paths(SETTING_FRAMEORK_PATHS, true, "-Ff\"".WRAP_REPLACE_SYMBOL."\"");
	$library_paths = get_paths(SETTING_LIBRARY_PATHS, true, "-Fl\"".WRAP_REPLACE_SYMBOL."\"");
	$include_paths = get_paths(SETTING_INCLUDE_PATHS, true, "-Fi\"".WRAP_REPLACE_SYMBOL."\"");

	// use version macros
	if ($version == 'latest') {
		$version = find_fpc_version(true);
	} else if ($version == 'stable') {
		$version = find_fpc_version(false);
	}

	// define built-in macros for compiler path
	$macros['$compiler_version'] = $version;
	$macros['$compiler_arch'] = $arch;

	// resolve all macro in macros
	foreach ($macros as $key => $value) {
		$macros[$key] = resolve_macro($value);
	}

	// get final compiler path
	if ($compiler_path = get_setting(SETTING_COMPILER, SETTING_COMPILER_PATH, true, SETTING_OPTIONAL)) {
		$fpc = $compiler_path;
	} else {
		if (strtolower(PHP_OS_FAMILY) == PLATFORM_DARWIN || strtolower(PHP_OS_FAMILY) == PLATFORM_LINUX) {
			$fpc = "/usr/local/lib/fpc/$version/$arch";
		} else {
			fatal("no compiler search paths for this platform");
		}
	}

	if (!file_exists($fpc)) {
		fatal("can't find compiler at $fpc");
	}

	// TODO: make this an option with this being the default
	$output = "$project/$target_name.$target_config.$arch";

	// build final options string
	$options = get_setting(SETTING_OPTIONS);

	// add additional options from settings
	if ($min_system_version = get_setting(SETTING_COMPILER, SETTING_COMPILER_MINIMUM_SYSTEM_VERSION, !SETTING_IS_PATH, SETTING_OPTIONAL)) {

		if ($platform == PLATFORM_DARWIN) {
			$options[] = "-WM$min_system_version";
		} elseif ($platform == PLATFORM_IPHONE_SIMULATOR) {
			// TODO: -WP is broken for iphonesim now
			// $options[] = "-WP$min_system_version";
		} elseif ($platform == PLATFORM_IPHONE) {
			$options[] = "-WP$min_system_version";
		} else {
			fatal(SETTING_COMPILER_MINIMUM_SYSTEM_VERSION." is not supported for this target platform.");
		}
	}

	// standard options
	$options[] = "-FU\"$output\"";
	$options[] = "-o\"$product_path\"";

	// platform specific options
	if ($platform == PLATFORM_IPHONE_SIMULATOR) {
		$options[] = '-Tiphonesim';
	} else {
		// NOTE: -XR is broken for iphonesim now so we need to use -Ff
		$options[] = "-XR\"$system_sdk\"";
	}

	$options = @implode(" ", $options);

	// clean output directory
	if ($clean_build && file_exists($output)) {
		print("Cleaning output directory at '$output'...\n");
		// TODO: this isn't portable! use mv -f "$output" ~/.Trash
		exec("trash $output");
	}

	// make output directory
	make_dir($output);

	// build command
	$command = "$fpc \"$program\" $options $source_paths $framework_paths $library_paths $include_paths";

	// run command
	push_makefile($command);
	$exit_code = run_command($command);

	if ($exit_code == 0) {
		$path_info = pathinfo($program);

		// verify the binary/executable location
		$binary = $product_path;
		if (!file_exists($binary)) {
			fatal("compiled binary doesn't exist '$binary'");
		}

		// move .dSYM to output directory
		$dsym = dirname($product_path).'/'.basename_no_ext($product_path).".dSYM";
		if (file_exists($dsym)) {
			$dest = $output."/".basename($dsym);
			rmovedir($dsym, $dest);
		}

		// make the package if a bundle path was specificed
		if ($bundle) $binary = make_bundle($platform, $binary, $bundle, $resource_paths);

		if ($shared_makefile) {
			$shared_makefile->push('clean', "rm -r $output");
			if ($bundle)
				$shared_makefile->push('clean', "rm -r $bundle");
			$shared_makefile->push('install', "cp $bundle ~/Applications/".basename($bundle));
			$shared_makefile->write_to_file("$project/Makefile");
		}

		// run xcodebuild for iphone platform
		if ($platform == PLATFORM_IPHONE && $build_variant != BUILD_MODE_NO_RUN)
			$xcode_product = run_xcode_project();

		// run the executable
		if ($run_after_build) {
			// TODO: add env common setting to $binary string
			switch ($build_variant) {
				case BUILD_MODE_DEBUG:
					run_in_terminal("/usr/bin/lldb $binary");
					break;

			  case BUILD_MODE_VSCODE:
			  	$command = "open -a \"Visual Studio Code\"";
			  	passthru2($command);
			  	build_finished(0);
			  	break;

			  case BUILD_MODE_DEFAULT:
			  	if ($platform == PLATFORM_DARWIN) {
			  		run_in_terminal($binary);
			  	} elseif ($platform == PLATFORM_IPHONE_SIMULATOR) {
			  		if ($bundle) {
			  			if (!file_exists($bundle)) fatal("iphone targets require an app bundle.");
			  			$bundle_id = get_macro('CFBundleIdentifier', true, true, 'iphonesim target requires a bundle id to launch.');
			  			run_in_simulator($bundle, $bundle_id);
			  		} else {
			  			$bundle_id = get_macro('CFBundleIdentifier', true, true, 'iphonesim target requires a bundle id to launch.');
			  			// $xcode_product = get_macro('xcode_product');
			  			$xcode_product = get_setting(SETTING_XCODEBUILD, SETTING_XCODEBUILD_PRODUCT, SETTING_IS_PATH, false);
			  			
			  			// if (!file_exists($xcode_product)) {
			  			// 	$xcode_product = run_xcode_project();
			  			// }
			  			
			  			// TODO: rebuild if the xcode bundle mode time changed
			  			// where can save this temp info? maybe just in the temp dir
			  			// print("***** xcode bundle mtime ".filemtime($xcode_product)."\n");

			  			// inject fpc binary into xcode bundle
			  			copy($binary, "$xcode_product/".basename($binary));

			  			launch_xcode_project(function() {
			  				run_in_simulator($xcode_product, $bundle_id);
			  			});

			  		}
			  	} elseif ($platform == PLATFORM_IPHONE) {
			  		if ($xcode_product == null) fatal("target require 'xcode_product' macro.");
		  			launch_xcode_project(function() {
		  				run_in_terminal('ios-deploy --debug --bundle "'.$xcode_product.'"');
		  			});
			  	} else {
			  		fatal('no default run mode for target platform');
			  	}
			  	break;

				default:
					break;
			}
		}
		
	} else {
		print("failed with exit code $exit_code\n");
		build_finished($exit_code);
	}
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

$file = $argv[1];						// path to current file
$project_path = $argv[2];		// path to directory containing sublime-project file
$project_file = $argv[3];		// path to *.sublime-project file
$build_variant = $argv[4];	// sublime-build "variants" key

switch ($build_variant) {
	case BUILD_MODE_DEFAULT:
	case BUILD_MODE_DEBUG:
	case BUILD_MODE_VSCODE:
	case BUILD_MODE_NO_RUN:
		// try to use .fpcbuild in the project directory
		if (file_exists($project_file)) {
			
			$fpcbuild = load_fpc_build($project_file);
			// if there is a build_script defined then override the project settings
			if ($fpcbuild['build_script']) {
				run_build_script($file, $project_file, $fpcbuild);
			} else {
				run_project($file, $project_file, $fpcbuild, $build_variant, false);
			}
		} else if ($file) {
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
		break;
	
	case BUILD_MODE_LAZARUS:
		run_lazarus($project_path, $project_file);
		build_finished();
		break;

	case BUILD_MODE_QUICK:
		run_quick_file($file);
		break;

	default:
		# code...
		break;
}

?>