<?php
/*
 * Copyright 2007-2011 Charles du Jeu <contact (at) cdujeu.me>
 * This file is part of AjaXplorer.
 *
 * AjaXplorer is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * AjaXplorer is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with AjaXplorer.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://www.ajaxplorer.info/>.
 *
 * This is the main configuration file for configuring the core of the application.
 * In a standard usage, you should not have to change any variables.
 */
define("AJXP_PACKAGING", "zip");
define("AJXP_INSTALL_PATH", realpath(dirname(__FILE__)));
/**
 * in an effort to secure the conf (and get out of the repository)
 * look for a ajaxplorer_conf directory in any level parent from the current
 * file/dir... this allows you to easily create customized configs outside of
 * your webroot
 *
 * mv conf ../../ajaxplorer_conf
 */
$confPath = realpath(dirname(__FILE__)) . '/ajaxplorer_conf';
$i = 0;
while ($i < 5 && trim($confPath, '/\:') != 'ajaxplorer_conf' && (!file_exists($confPath) || !is_dir($confPath))) {
	$i++;
	$confPath = dirname(dirname($confPath)) . '/ajaxplorer_conf';
}
// couldn't find a valid ajaxplorer_conf directory?  load from this folder
if (empty($confPath) || !file_exists($confPath) || !is_dir($confPath)) {
	$confPath = AJXP_INSTALL_PATH."/conf";
}
define("AJXP_CONF_PATH", $confPath);
// load the rest of the configurations
require_once(AJXP_CONF_PATH."/bootstrap_context.php");
?>
