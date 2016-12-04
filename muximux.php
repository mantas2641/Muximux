<?php
/*
* DO NOT CHANGE THIS FILE!
*/
defined("CONFIG") ? null : define('CONFIG', 'settings.ini.php');
defined("CONFIGEXAMPLE") ? null : define('CONFIGEXAMPLE', 'settings.ini.php-example');
defined("SECRET") ? null : define('SECRET', 'secret.txt');
require dirname(__FILE__) . '/vendor/autoload.php';

// Check if this is an old installation that needs upgrading.
if (file_exists('config.ini.php')) {
    copy('config.ini.php', 'backup.ini.php');
    unlink('config.ini.php');
    $upgrade = true;
} else {
    $upgrade = false;
}

function openFile($file, $mode) {
    if ((file_exists($file) && (!is_writable(dirname($file)) || !is_writable($file))) || !is_writable(dirname($file))) { // If file exists, check both file and directory writeable, else check that the directory is writeable.
        printf('Either the file %s and/or it\'s parent directory is not writable by the PHP process. Check the permissions & ownership and try again.', $file);
        if (PHP_SHLIB_SUFFIX === "so") { //Check for POSIX systems.
            printf("<br>Current permission mode of %s: %d", $file, decoct(fileperms($file) & 0777));
            printf("<br>Current owner of %s: %s", $file, posix_getpwuid(fileowner($file))['name']);
            printf("<br>Refer to the README on instructions how to change permissions on the aforementioned files.");
        } else if (PHP_SHLIB_SUFFIX === "dll") {
            printf("<br>Detected Windows system, refer to guides on how to set appropriate permissions."); //Can't get fileowner in a trivial manner.
        }

        exit;
    }

    return fopen($file, $mode);
}

function createSecret() {
    $text = uniqid("muximux-", true);
    $file = openFile(SECRET, "w");
    fwrite($file, $text);
    fclose($file);
    return $text;
}

if(!file_exists(CONFIG)){
    copy(CONFIGEXAMPLE, CONFIG);
    checksetSHA();
}

// First what we're gonna do - save or read
if (sizeof($_POST) > 0) {
	
    if("" == trim($_POST['username'])){
		
		write_ini();
		
	}
	
} else {
    parse_ini();
}

function write_ini()
{
	$oldHash = getPassHash();
    	$oldBranch = getBranch();
    unlink(CONFIG);

    $config = new Config_Lite(CONFIG);
    foreach ($_POST as $parameter => $value) {
        $splitParameter = explode('_-_', $parameter);
        if ($value == "on")
            $value = "true";
		if ($splitParameter[1] == "password") {
			if ($value != $oldHash) {
				$value = password_hash($value, PASSWORD_BCRYPT);
			}
		}
		if ($splitParameter[1] == "branch") {
			if ($value != $oldBranch) {
				$config->set('settings','branch_changed',true);
				$config->set('settings','sha','00');
			} else {
				$config->set('settings','branch_changed',false);
			}
		}
        $config->set($splitParameter[0], $splitParameter[1], $value);
    }
    // save object to file
    try {
        $config->save();
    } catch (Config_Lite_Exception $e) {
        echo "\n" . 'Exception Message: ' . $e->getMessage();
    }
	rewrite_config_header();
    
	
}

function rewrite_config_header() {
	$cache_new = "; <?php die(\"Access denied\"); ?>"; // Adds this to the top of the config so that PHP kills the execution if someone tries to request the config-file remotely.
    $file = CONFIG; // the file to which $cache_new gets prepended

    $handle = openFile($file, "r+");
    $len = strlen($cache_new);
    $final_len = filesize($file) + $len;
    $cache_old = fread($handle, $len);
    rewind($handle);
    $i = 1;
    while (ftell($handle) < $final_len) {
        fwrite($handle, $cache_new);
        $cache_new = $cache_old;
        $cache_old = fread($handle, $len);
        fseek($handle, $i * $len);
        $i++;
    }
}

function parse_ini()
{
    
    $config = new Config_Lite(CONFIG);
	fetchBranches();
    checksetSHA();
	$myBranch = getBranch();
	$branchArray = getBranches();
	$branchList = "";
	$pageOutput = "<div>". implode($branchArray)."</div>";
	foreach ($branchArray as $branchName => $shaSum ) {
		$branchList .= "<option value='".$branchName."' ".(($myBranch == $branchName) ? 'selected' : '' ).">". $branchName ."</option>\n";
	}
	
	$title = $config->get('general', 'title', 'Muximux - Application Management Console');
    $pageOutput .= "
					<form>
						<div class='applicationContainer' style='cursor:default;'>
						<h2>General</h2>
						<div>
							<label for='titleInput'>Title: </label>
							<input id='titleInput' type='text' class='general_-_value' name='general_-_title' value='" . $title . "'>
						</div>
						<div>
							<label for='theme'>Theme: </label>
							<select id='theme' class='general_-_value' name='general_-_theme'>". 
								listThemes() ."
							</select>
						</div>
						<div>
							<label for='branch'>Branch tracking: </label>
							<select id='branch' name='general_-_branch'>".
								$branchList ."
							</select>
						</div>
						<br>
						<div>
							<label for='dropdownCheckbox'>Enable Dropdown:</label>
							<input id='dropdownCheckbox' class='checkbox general-value' name='general_-_enabledropdown' type='checkbox' ". ($config->getBool('general', 'enabledropdown', false) ? 'checked' : '') ."
						</div>
						<div>
							<label for='updatepopupCheckbox'>Enable update poups:</label> 
							<input id='updatepopupCheckbox' class='general_-_value' name='general_-_updatepopup' type='checkbox' ".($config->getBool('general', 'updatepopup', false) ? 'checked' : '') .">
						</div>
						
						<div>
							<label for='mobileoverrideCheckbox'>Enable mobile override:</label> 
								<input id='mobileoverrideCheckbox' class='general_-_value' name='general_-_mobileoverride' type='checkbox' ".($config->getBool('general', 'mobileoverride', false) ? 'checked' : '').">
						</div><br>
						<div>
							<label for='autohideCheckbox'>Enable auto-hide:</label>
							<input id='autohideCheckbox' class='general_-_value' name='general_-_autohide' type='checkbox' ".($config->getBool('general', 'autohide', false) ? 'checked' : '').">
						</div>
						<div>
							<label for='authenticationCheckbox'>Enable authentication:</label>
							<input id='authenticationCheckbox' class='general_-_value' name='general_-_authentication' type='checkbox' ".($config->getBool('general', 'authentication', false) ? 'checked' : '').">
						</div><br>
						<div class='userinput ".($config->getBool('general', 'authentication', false) ? '' : 'hidden')."'>
							<label for='userName'>Username: </label><input id='userNameInput' type='text' class='general_-_value userinput' name='general_-_userNameInput' value='" . $config->get('general', 'userNameInput', 'admin') . "'>
						</div><br>
						<div class='userinput ".($config->getBool('general', 'authentication', false) ? '' : 'hidden')."'>
							<label for='password'>Password: </label><input id='passwordInput' type='password' class='general_-_value userinput' name='general_-_password' value='" . $config->get('general', 'password', 'muximux') . "'>
						</div>
					</div>
				</div>
					";
    
	if ($config->get('settings', 'sha', '0') == "0") {
		$pageOutput .="<input type='hidden' class='settings_-_value' name='settings_-_sha' value='".fetchSHA()."'>";
	} else {
		$mySha = $config->get('settings', 'sha', '0');
		$pageOutput .="<input type='hidden' class='settings_-_value' name='settings_-_sha' value='".$mySha."'>";
	}
	
    $pageOutput .= "<input type='hidden' class='settings_-_value' name='settings_-_enabled' value='true'>" .
        "<input type='hidden' class='settings_-_value' name='settings_-_default' value='false'>" .
        "<input type='hidden' class='settings_-_value' name='settings_-_name' value='Settings'>" .
        "<input type='hidden' class='settings_-_value' name='settings_-_url' value='muximux.php'>" .
        "<input type='hidden' class='settings_-_value' name='settings_-_landingpage' value='false'>" .
        "<input type='hidden' class='settings_-_value' name='settings_-_icon' value='fa-cog'>" .
        "<input type='hidden' class='settings_-_value' name='settings_-_dd' value='true'>";

    $pageOutput .= "
<div id='sortable'>";
    foreach ($config as $section => $name) {
        if (is_array($name) && $section != "settings" && $section != "general") {
			$name = $config->get($section, 'name', '');
			$url = $config->get($section, 'url', 'http://www.plex.com');
			$color = $config->get($section, 'color', '#000');
			$icon = $config->get($section, 'icon', '');
			$scale = $config->get($section, 'scale', '');
			$default = $config->getBool($section, 'default', false);
			$enabled = $config->getBool($section, 'enabled', true);
			$landingpage = $config->getBool($section, 'landingpage', true);
			$dd = $config->getBool($section, 'dd', true);
			$scaleRange = "0";
			$scaleRange = buildScale($scale);
       
			$pageOutput .= "
	<div class='applicationContainer' id='" . $section . "'>
		<span class='bars fa fa-bars'></span>
		<div>
			<label for='" . $section . "_-_name' >Name: </label>
			<input class='appName " . $section . "_-_value' was='" . $section . "' name='" . $section . "_-_name' type='text' value='" . $name . "'>
		</div>
		<div>
			<label for='" . $section . "_-_url' >URL: </label>
			<input class='" . $section . "_-_value' name='" . $section . "_-_url' type='text' value='" . $url . "'>
		</div>
		<div>
			<label for='" . $section . "_-_enabled' >Enabled: </label>
			<input type='checkbox' class='checkbox " . $section . "_-_value' id='" . $section . "_-_enabled' name='" . $section . "_-_enabled'".($enabled ? 'checked' : '') .">
		</div>
		<div>
			<label for='" . $section . "_-_landingpage' >Landing page: </label>
			<input type='checkbox' class='checkbox " . $section . "_-_value' id='" . $section . "_-_landingpage' name='" . $section . "_-_landingpage'".($landingpage ? 'checked' : '') .">
		</div>
		<div>
			<label for='" . $section . "_-_dd' >Put in dropdown: </label>
			<input type='checkbox' class='checkbox " . $section . "_-_value' id='" . $section . "_-_dd' name='" . $section . "_-_dd'".($dd ? 'checked' : '') .">
		</div>
		<div>
			<label for='" . $section . "_-_default' >Default: </label>
			<input type='radio' class='radio " . $section . "_-_value' id='" . $section . "_-_default' name='" . $section . "_-_default'".($default ? 'checked' : '') .">
		</div>
		<br><div>
			<label for='" . $section . "_-_icon' >Icon: </label>
			<button role=\"iconpicker\" class=\"iconpicker btn btn-default\" name='" . $section . "_-_icon' data-rows=\"4\" data-cols=\"6\" data-search=\"true\" data-search-text=\"Search...\" data-iconset=\"fontawesome\" data-placement=\"left\" data-icon=\"" . $icon . "\">
			</button>
		</div>
		<div>
			<label for='" . $section . "_-_color'>Color: </label>
			<input type='color' id='custom' class='appsColor " . $section . "_-_color' value='" . $color . "' name='" . $section . "_-_color'>
		</div>
		<div style=\"margin-left:5px;\">
			<label for='" . $section . "_-_scale'>Zoom: </label>
			<select id='" . $section . "_-_scale' name='" . $section . "_-_scale'>". $scaleRange ."</select>
		</div>
		<button type='button' class='removeButton btn btn-danger btn-xs' value='Remove' id='remove-" . $section . "'>Remove</button>
	</div>";
            
        
		}
	}
    $pageOutput .= "</div>
    <div class='text-center' style='margin-top: 15px;'>
		<div class='btn-group' role='group' aria-label='Buttons'>
			<a class='btn btn-primary btn-md' id='addApplication'><span class='fa fa-plus'></span> Add new</a>
            <a class='btn btn-danger btn-md' id='removeAll'><span class='fa fa-trash'></span> Remove all</a>
		</div>
	</div>
</form>";
    return $pageOutput;
}
// Check if the user changes tracking branch, which will change the SHA and trigger an update notification
function checkBranchChanged() {
	$config = new Config_Lite(CONFIG);
	if ($config->getBool('settings', 'branch_changed', false)) {
		console_log("Branch change detected.");
		$config->set("settings","sha","00");
		$config->set("settings","branch_changed",false);
		try {
			$config->save();
		} catch (Config_Lite_Exception $e) {
			echo "\n" . 'Exception Message: ' . $e->getMessage();
		}
		rewrite_config_header();
		return true;
	} else {
		return false;
	}
}

// Build a custom scale using our set value, show it as selected
function buildScale($selectValue) 
{
	$f=10;
    $scaleRange = "";
    while($f<251) {
        $pra = $f / 100;
		if ($pra == $selectValue) {
			
			$scaleRange .= "<option value='" . $pra ."' selected>". $f ."%</option>\n";
			$f++;
		} else {
			
			$scaleRange .= "<option value='" . $pra ."'>". $f ."%</option>\n";
			$f++;
		}
    }
	return $scaleRange;

}

function getTheme()
{
    $config = new Config_Lite(CONFIG);
    $item = $config->get('general', 'theme', 'classic');
    return $item;
}

function listThemes() {
	$dir    = './css/theme';
	$themelist ="";
	$themes = scandir($dir);
	$themeCurrent = getTheme();
	foreach($themes as $value){ 
        $splitName = explode('.', $value);
		if  (!empty($splitName[0])) {
			if ($splitName[0] == getTheme()) {
				$themelist .="
								<option value='".$splitName[0]."' selected>".$splitName[0]."</option>";
			} else {
				$themelist .="
								<option value='".$splitName[0]."'>".$splitName[0]."</option>";
			}
		}
	}
	return $themelist;
}

function menuItems() {
    $config = new Config_Lite(CONFIG);
    $standardmenu = "";
    $dropdownmenu = "";

    foreach ($config as $keyname => $section) {
        if (($keyname == "general")) {
			$autohide = $config->getBool('general', 'autohide', true);
			$enabledropdown = $config->getBool('general', 'enabledropdown', false);
			$mobileoverride = $config->getBool('general', 'mobileoverride', false);
			$authentication = $config->getBool('general', 'authentication', false);

            } else {
			$dropdown = $config->getBool($keyname, 'dd', false);
			$enabled = $config->getBool($keyname, 'enabled', true);
			$default = $config->getBool($keyname, 'default', true);
        if ($enabled && !$dropdown) {
                $standardmenu .= "
					<li class='cd-tab'>
						<a data-content='" . $keyname . "' data-title='" . $section["name"] . "' data-color='" . $section["color"] . "' class='".($default ? 'selected' : '')."'>
							<span class='fa " . $section["icon"] . " fa-lg'></span> " . $section["name"] . "
						</a>
					</li>";
            
        }
        if ($dropdown && $enabled && $section['name'] == "Settings") {
            $dropdownmenu .= "
				<li>
					<a data-toggle=\"modal\" data-target=\"#settingsModal\" data-title=\"Settings\">
						<span class=\"fa " . $section["icon"] . "\"></span> " . $section["name"] . "
					</a>
				</li>";
        } else if ($enabledropdown && $dropdown && $enabled) {
            $dropdownmenu .= "
				<li>
					<a data-content=\"" . $keyname . "\" data-title=\"" . $section["name"] . "\">
						<span class=\"fa " . $section["icon"] . "\"></span> " . $section["name"] . "
					</a>
				</li>";
        } else {
            $dropdownmenu .= "";
        }
        }
    }
	
	if ($mobileoverride == "true") {
		$moButton = "
		<li class='navbtn'>
			<a id=\"override\" title=\"Click this button to disable mobile scaling on tablets or other large-resolution devices.\">
				<span class=\"fa fa-mobile fa-lg\"></span>
			</a>
		</li>
		";
	} else {
		$moButton = "";
	}
	if ($authentication == "true") {
		$outButton = "
				<li class='navbtn'>
					<a id='logout' title='Click this button to log out of Muximux.'>
						<span class=\"fa fa-sign-out fa-lg\"></span>
					</a>
				</li>
		";
	} else {
		$outButton = "";
	}

	if ($autohide == "true") {
			$drawerdiv .= "
		<div class='cd-tabs-bar drawer'>
";
	} else {
			$drawerdiv .= "
				<div class='cd-tabs-bar'>
			";
	}
    if ($enabledropdown == "true") {
		$item = $drawerdiv . "
			<ul class=\"main-nav\">" .
			$moButton ."
				<li class='navbtn'>
					<a id=\"reload\" title=\"Double click your app in the menu, or press this button to refresh the current app.\">
						<span class=\"fa fa-refresh fa-lg\"></span>
					</a>
				</li>". 
				$outButton ."
				<li class='dd navbtn'>
					<a id=\"hamburger\">
						<span class=\"fa fa-bars fa-lg\"></span>
					</a>
					<ul class=\"drop-nav\">" . 
							$dropdownmenu ."
					</ul>
				</li>
			</ul>
			
			<ul class=\"cd-tabs-navigation\">
				<nav>" .
					$standardmenu ."
				</nav>
			</ul>
		</div>
	
			";
    } else {
		$item =  $drawerdiv . "
			<ul class=\"main-nav\">" .
			$moButton ."
				<li class='cd-tab navbtn'>
					<a id=\"reload\" title=\"Double click your app in the menu, or press this button to refresh the current app.\">
						<span class=\"fa fa-refresh fa-lg\"></span>
					</a>
				</li>
				<li class='cd-tab navbtn'>
					<a id=\"settings\" data-toggle=\"modal\" data-target=\"#settingsModal\" data-title=\"Settings\">
						<span class=\"fa fa-gear fa-lg\"></span>
					</a>
				</li>
			</ul>
			<ul class=\"cd-tabs-navigation\">
			<nav>" .
				$standardmenu . "
				</nav>
				</ul>
		";
    }
    return $item;
}

function getTitle() {
    $config = new Config_Lite(CONFIG);
    $item = $config->get('general', 'title', 'Muximux - Application Management Console');
    return $item;
}

// Quickie fetch of the current selected branch
function getBranch() {
	$config = new Config_Lite(CONFIG);
	$branch = $config->get('general', 'branch', 'master');
	return $branch;
}

// Reads for "branches" from settings.  If not found, fetches list from github, saves, parses, and returns
function getBranches() {
	$config = new Config_Lite(CONFIG);
	$branches = [];
	$branches = $config->get('settings', 'branches');
	if ($branches == []) {
		fetchBranches();
	} else {
		$branches = $config->get('settings', 'branches');
	}
	return $branches;
}

// Fetch a list of branches from github, along with their current SHA
function fetchBranches() {
	$config = new Config_Lite(CONFIG);
	$last = $config->get('settings', 'last_check', "0");
	if (time() >= $last + 3600) { // Check to make sure we haven't checked in an hour or so, to avoid making GitHub mad
		$curl_handle=curl_init();
		curl_setopt($curl_handle, CURLOPT_URL,'https://api.github.com/repos/mescon/Muximux/branches');
		curl_setopt($curl_handle, CURLOPT_CONNECTTIMEOUT, 2);
		curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl_handle, CURLOPT_USERAGENT, 'Muximux');
		$json = curl_exec($curl_handle);
		curl_close($curl_handle);
		$array = json_decode($json,true);
		$i = 0;
		$names = array();
		$shas = array();
		foreach ($array as $value) {
			foreach ($value as $key => $value2) {
				if ($key == "name") {
						array_push($names,$value2);
					
				} else {
					foreach ($value2 as $key2 => $value3) {
						if ($key2 == "sha" ) {
							$shaVal = $value3;
							array_push($shas,$value3);
						}
					}
				}
			}
		}

		$outP = array_combine($names,$shas);
		$config ->set('settings','branches',$outP);
		$config ->set('settings','last_check',time());
		try {
			$config->save();
		} catch (Config_Lite_Exception $e) {
			echo "\n" . 'Exception Message: ' . $e->getMessage();
		}
		rewrite_config_header();
	} 
	
}

// We run this when parsing settings to make sure that we have a SHA saved just as 
// soon as we know we'll need it (on install or new settings).  This is how we track whether or not 
// we need to update.  

function checksetSHA() {
	$config = new Config_Lite(CONFIG);
	if (getSHA() == '00') {
		$config ->set('settings','sha',fetchSHA());
		try {
			$config->save();
		} catch (Config_Lite_Exception $e) {
			echo "\n" . 'Exception Message: ' . $e->getMessage();
		}
		rewrite_config_header();
	}
}

// Read SHA from settings and return it's value.

function getSHA() {
    $config = new Config_Lite(CONFIG);
    $item = $config->get('settings', 'sha', '00');
    return $item;
}

// This reads our array of branches, finds our selected branch in settings,
// and returns the corresponding SHA value.  We need this to set the initial
// SHA value on setup/load, as well as to compare for update checking.
function fetchSHA() {
	$branchArray = getBranches();
	$myBranch = getBranch();
	foreach ($branchArray as $branchName => $shaVal) {
		if ($branchName==$myBranch) {
				$shaOut = $shaVal;
		}
		
	}
	return $shaOut;
	
}

// Retrieve password hash from settings and return it for "stuff".
function getPassHash() {
    $config = new Config_Lite(CONFIG);
    $item = $config->get('general', 'password', 'foo');
    return $item;
}

// This little gem helps us replace a whome bunch of AJAX calls by sorting out the info and 
// writing it to meta-tags at the bottom of the page.  Might want to look at calling this via one AJAX call.

function metaTags() {
	$config = new Config_Lite(CONFIG);
    $standardmenu = "";
    $dropdownmenu = "";
    foreach ($config as $keyname => $section) {
        if (($keyname == "general")) {
			if (isset($section["autohide"]) && ($section["autohide"] == "true")) {
				$autohide = "true";
			} else {
				$autohide = "false";
			}
			$greeting = $config->get('general', 'greeting', 'false');
			if (isset($section["branch"])) {
				$branch = $section["branch"];
				$branchUrl = "https://api.github.com/repos/mescon/Muximux/commits?sha=" . $branch;
				
			} else {
				$branch = "";
				$branchData = "";
				$result = "";
			}
			if (isset($section["updatepopup"])) {
				$popupdate = $section["updatepopup"];
			} else {
				$popupdate = "";
			}
			if (isset($section["title"])) {
				$maintitle = $section["title"];
			} else {
				$maintitle = "";
			}
		}
	}
	
	$gitdir = getcwd() . "/.git/";
	    if (is_readable($gitdir)) {
            $gitdir = "readable";
        } else {
            $gitdir = "unreadable";
        }
        		
	$inipath = php_ini_loaded_file();

        if ($inipath) {
            $inipath;
        } else {
            $inipath = "php.ini";
        }
		
	$created = filectime(CONFIG);
    	$branchChanged = (checkBranchChanged() ? 'true' : 'false');
	
$tags .= "
<meta id='branch-data' data='". $branch . "'>
<meta id='branch-changed' data='". $branchChanged . "'>
<meta id='popupdate' data='". $popupdate . "'>
<meta id='drawer' data='". $autohide . "'>
<meta id='maintitle' data='". $maintitle . "'>
<meta id='gitdirectory-data' data='". $gitdir . "'>
<meta id='cwd-data' data='". getcwd() . "'>
<meta id='phpini-data' data='". $inipath . "'>
<meta id='title-data' data='". $maintitle . "'>
<meta id='created-data' data='". $created . "'>
<meta id='sha-data' data='". getSHA() . "'>

";
	return $tags;
}

// Set up the actual iFrame contents, as the name implies.
function frameContent() {
    $config = new Config_Lite(CONFIG);
    if (empty($item)) $item = '';
    foreach ($config as $keyname => $section) {
        if (!empty($section["landingpage"]) && !($section["landingpage"] == "false") && ($section["landingpage"] == "true")) {
            $section["url"] = "?landing=" . $keyname;
        }

        if (empty($section["scale"]) or ($section["scale"] == "false")) {
            $section["scale"] = 1;
        }

        if (!empty($section["enabled"]) && !($section["enabled"] == "false") && ($section["enabled"] == "true")) {
            if (!empty($section["default"]) && !($section["default"] == "false") && ($section["default"] == "true")) {
                $item .= "
			<li data-content=\"" . $keyname . "\" data-scale=\"" . $section["scale"] ."\" class=\"selected\">
				<iframe sandbox=\"allow-forms allow-same-origin allow-pointer-lock allow-scripts allow-popups allow-modals allow-top-navigation\" 
				allowfullscreen=\"true\" webkitallowfullscreen=\"true\" mozallowfullscreen=\"true\" scrolling=\"auto\" data-title=\"" . $section["name"] . "\" src=\"" . $section["url"] . "\"></iframe>
			</li>";
            } else {
                $item .= "
			<li data-content=\"" . $keyname . "\" data-scale=\"" . $section["scale"] ."\">
				<iframe sandbox=\"allow-forms allow-same-origin allow-pointer-lock allow-scripts allow-popups allow-modals allow-top-navigation\" 
				allowfullscreen=\"true\" webkitallowfullscreen=\"true\" mozallowfullscreen=\"true\" scrolling=\"auto\" data-title=\"" . $section["name"] . "\" data-src=\"" . $section["url"] . "\"></iframe>
			</li>
";
            }

        }
    }
    return $item;
}

// Build a landing page.
function landingPage($keyname) {
    $config = new Config_Lite(CONFIG);
    $item = "
    <html lang=\"en\">
    <head>
    <title>" . $config->get($keyname, 'name') . "</title>
    <link rel=\"stylesheet\" href=\"css/landing.css\">
    </head>
    <body>
    <div class=\"login\">
        <div class=\"heading\">
            <h2><span class=\"fa " . $config->get($keyname, 'icon') . " fa-3x\"></span></h2>
            <section>
                <a href=\"" . $config->get($keyname, 'url') . "\" target=\"_self\" title=\"Launch " . $config->get($keyname, 'name') . "!\"><button class=\"float\">Launch " . $config->get($keyname, 'name') . "</button></a>
            </section>
        </div>
     </div>
     </body></html>";
    if (empty($item)) $item = '';
    return $item;
}

function command_exist($cmd) {
    $returnVal = exec("which $cmd");
    return (empty($returnVal) ? false : true);
}

function exec_enabled() {
    $disabled = explode(', ', ini_get('disable_functions'));
    return !in_array('exec', $disabled);
}

// URL parameters
if (isset($_GET['landing'])) {
    $keyname = $_GET['landing'];
    echo landingPage($keyname);
    die();
}


// This is where the JavaScript reads the contents of the secret file. This gets re-generated on each page load.
if (isset($_GET['get']) && $_GET['get'] == 'secret') {
        $secret = file_get_contents(SECRET) or die("Unable to open " . SECRET);
        echo $secret;
        die();
}

// Things wrapped inside this are protected by a secret hash.
if(isset($_GET['secret']) && $_GET['secret'] == file_get_contents(SECRET)) {


    // This lets us create a new secret when we leave the page.
    if (isset($_GET['set']) && $_GET['set'] == 'secret') {
            createSecret();
            die();
    }

    if (isset($_GET['get']) && $_GET['get'] == 'hash') {
        if (exec_enabled() == true) {
            if (!command_exist('git')) {
                $hash = 'unknown';
            } else {
                $hash = exec('git log --pretty="%H" -n1 HEAD');
            }
        } else {
            $hash = 'noexec';
        }
        echo $hash;
        die();
    }

    if(isset($_GET['remove']) && $_GET['remove'] == "backup") {
        unlink('backup.ini.php');
        echo "deleted";
        die();
    }
}
// End protected get-calls


if(empty($_GET)) {
    createSecret();
}

// This will download the latest zip from the current selected branch and extract it wherever specified
function downloadUpdate() {
	$f = file_put_contents("my-zip.zip", fopen("https://github.com/mescon/Muximux/archive/". getBranch() .".zip", 'r'), LOCK_EX);
	if(FALSE === $f)
		die("Couldn't write to file.");
	$zip = new ZipArchive;
	$res = $zip->open('my-zip.zip');
	if ($res === TRUE) {
	  $zip->extractTo('./test');
	  $zip->close();
	  //
	} else {
	  //
	}
}

function console_log( $data ){
	echo '<script>';
	echo 'console.log('. json_encode( $data ) .')';
	echo '</script>';
}