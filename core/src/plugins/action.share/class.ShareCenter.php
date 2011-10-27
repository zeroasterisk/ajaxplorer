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
 */

defined('AJXP_EXEC') or die( 'Access not allowed');

require_once("class.PublicletCounter.php");

class ShareCenter extends AJXP_Plugin{

    private static $currentMetaName;
    private static $metaCache;
    private static $fullMetaCache;
    /**
     * @var AbstractAccessDriver
     */
    private $accessDriver;
    /**
     * @var Repository
     */
    private $repository;
    private $urlBase;

	protected function parseSpecificContributions(&$contribNode){
		parent::parseSpecificContributions($contribNode);
		if(isSet($this->actions["share"])){
			$disableSharing = false;
			$downloadFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
			if($downloadFolder == ""){
				$disableSharing = true;
			}else if((!is_dir($downloadFolder) || !is_writable($downloadFolder))){
				AJXP_Logger::debug("Disabling Public links, $downloadFolder is not writeable!", array("folder" => $downloadFolder, "is_dir" => is_dir($downloadFolder),"is_writeable" => is_writable($downloadFolder)));
				$disableSharing = true;
			}else{
				if(AuthService::usersEnabled()){
					$loggedUser = AuthService::getLoggedUser();
					if($loggedUser != null && $loggedUser->getId() == "guest" || $loggedUser == "shared"){
						$disableSharing = true;
					}
				}else{
					$disableSharing = true;
				}
			}
			if($disableSharing){
				unset($this->actions["share"]);
				$actionXpath=new DOMXPath($contribNode->ownerDocument);
				$publicUrlNodeList = $actionXpath->query('action[@name="share"]', $contribNode);
				$publicUrlNode = $publicUrlNodeList->item(0);
				$contribNode->removeChild($publicUrlNode);
			}
		}
	}

    function init($options){
        parent::init($options);
        $pServ = AJXP_PluginsService::getInstance();
        $aPlugs = $pServ->getActivePlugins();
        $accessPlugs = $pServ->getPluginsByType("access");
        $this->repository = ConfService::getRepository();
        foreach($accessPlugs as $pId => $plug){
            if(array_key_exists("access.".$pId, $aPlugs) && $aPlugs["access.".$pId] === true){
                $this->accessDriver = $plug;
                if(!isSet($this->accessDriver->repository)){
                    $this->accessDriver->init($this->repository);
                    $this->accessDriver->initRepository();
                    $wrapperData = $this->accessDriver->detectStreamWrapper(true);
                }else{
                    $wrapperData = $this->accessDriver->detectStreamWrapper(false);
                }
                $this->urlBase = $wrapperData["protocol"]."://".$this->repository->getId();
            }
        }
    }

    function switchAction($action, $httpVars, $fileVars){

        if(!isSet($this->accessDriver)){
            throw new Exception("Cannot find access driver!");
        }


        if($this->accessDriver->getId() == "access.demo"){
            $errorMessage = "This is a demo, all 'write' actions are disabled!";
            if($httpVars["sub_action"] == "delegate_repo"){
                return AJXP_XMLWriter::sendMessage(null, $errorMessage, false);
            }else{
                print($errorMessage);
            }
            return;
        }


        switch($action){

            //------------------------------------
            // SHARING FILE OR FOLDER
            //------------------------------------
            case "share":
            	$subAction = (isSet($httpVars["sub_action"])?$httpVars["sub_action"]:"");
            	if($subAction == "delegate_repo"){
					header("Content-type:text/plain");
					$result = $this->createSharedRepository($httpVars, $this->repository, $this->accessDriver);
					print($result);
            	}else if($subAction == "list_shared_users"){
            		header("Content-type:text/html");
            		if(!ConfService::getAuthDriverImpl()->usersEditable()){
            			break;
            		}
            		$loggedUser = AuthService::getLoggedUser();
            		$allUsers = AuthService::listUsers();
            		$crtValue = $httpVars["value"];
            		$users = "";
            		foreach ($allUsers as $userId => $userObject){
            			if($crtValue != "" && (strstr($userId, $crtValue) === false || strstr($userId, $crtValue) != 0)) continue;
            			if( ( $userObject->hasParent() && $userObject->getParent() == $loggedUser->getId() ) || ConfService::getCoreConf("ALLOW_CROSSUSERS_SHARING") === true  ){
            				$users .= "<li>".$userId."</li>";
            			}
            		}
            		if(strlen($users)) {
            			print("<ul>".$users."</ul>");
            		}
            	}else{
					$file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
	                $data = $this->accessDriver->makePublicletOptions($file, $httpVars["password"], $httpVars["expiration"], $this->repository);
                    $customData = array();
                    foreach($httpVars as $key => $value){
                        if(substr($key, 0, strlen("PLUGINS_DATA_")) == "PLUGINS_DATA_"){
                            $customData[substr($key, strlen("PLUGINS_DATA_"))] = $value;
                        }
                    }
                    if(count($customData)){
                        $data["PLUGINS_DATA"] = $customData;
                    }
                    $url = $this->writePubliclet($data, $this->accessDriver, $this->repository);
                    $this->loadMetaFileData($this->urlBase.$file);
                    self::$metaCache[$file] = array_shift(explode(".", basename($url)));
                    $this->saveMetaFileData($this->urlBase.$file);
	                header("Content-type:text/plain");
	                echo $url;
            	}
            break;

            case "load_shared_element_data":

                $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                $elementType = $httpVars["element_type"];
                $messages = ConfService::getMessages();

                $this->loadMetaFileData($this->urlBase.$file);
                if(isSet(self::$metaCache[$file])){
                    header("Content-type:application/json");
                    if($elementType == "file"){
                        $pData = self::loadPublicletData(self::$metaCache[$file]);
                        if($pData["OWNER_ID"] != AuthService::getLoggedUser()->getId()){
                            throw new Exception("You are not allowed to access this data");
                        }
                        $jsonData = array(
                                         "publiclet_link"   => $this->buildPublicletLink(self::$metaCache[$file]),
                                         "download_counter" => PublicletCounter::getCount(self::$metaCache[$file]),
                                         "expire_time"      => ($pData["EXPIRE_TIME"]!=0?date($messages["date_format"], $pData["EXPIRE_TIME"]):0),
                                         "has_password"     => (!empty($pData["PASSWORD"]))
                                         );
                    }else if( $elementType == "repository"){
                        $repoId = self::$metaCache[$file];
                        $repo = ConfService::getRepositoryById($repoId);
                        if($repo->getOwner() != AuthService::getLoggedUser()->getId()){
                            throw new Exception("You are not allowed to access this data");
                        }
                        $sharedUsers = array();
                        $sharedRights = "";
                        $loggedUser = AuthService::getLoggedUser();
                        $users = AuthService::listUsers();
                        foreach ($users as $userId => $userObject) {
                            if($userObject->getId() == $loggedUser->getId()) continue;
                            if($userObject->canWrite($repoId) && $userObject->canRead($repoId)){
                                $sharedUsers[] = $userId;
                                $sharedRights = "rw";
                            }else if($userObject->canRead($repoId)){
                                $sharedUsers[] = $userId;
                                $sharedRights = "r";
                            }else if($userObject->canWrite($repoId)){
                                $sharedUsers[] = $userId;
                                $sharedRights = "w";
                            }
                        }

                        $jsonData = array(
                                         "repositoryId" => $repoId,
                                         "label"    => $repo->getDisplay(),
                                         "rights"   => $sharedRights,
                                         "users"    => $sharedUsers
                                    );
                    }
                    echo json_encode($jsonData);
                }


            break;

            case "unshare":
                $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                $this->loadMetaFileData($this->urlBase.$file);
                if(isSet(self::$metaCache[$file])){
                    $element = self::$metaCache[$file];
                    self::deleteSharedElement($httpVars["element_type"], $element, AuthService::getLoggedUser());
                    unset(self::$metaCache[$file]);
                    $this->saveMetaFileData($this->urlBase.$file);
                }
            break;

            case "reset_counter":
                $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
                $this->loadMetaFileData($this->urlBase.$file);
                if(isSet(self::$metaCache[$file])){
                    $element = self::$metaCache[$file];
                    PublicletCounter::reset($element);
                }
            break;

            default:
            break;
        }


    }


    /**
     * @param AJXP_Node $ajxpNode
     * @return void
     */
    function nodeSharedMetadata(&$ajxpNode){
        if($this->accessDriver->getId() == "access.imap") return;
        $this->loadMetaFileData($ajxpNode->getUrl());
        if(count(self::$metaCache) && isset(self::$metaCache[$ajxpNode->getPath()])){
            if(!self::sharedElementExists($ajxpNode->isLeaf()?"file":"repository", self::$metaCache[$ajxpNode->getPath()], AuthService::getLoggedUser())){
                unset(self::$metaCache[$ajxpNode->getPath()]);
                $this->saveMetaFileData($ajxpNode->getUrl());
                return;
            }
            $ajxpNode->mergeMetadata(array(
                     "ajxp_shared"      => "true",
                     "overlay_icon"     => "shared.png"
                ), true);
        }
    }

	/**
	 *
	 * Hooked to node.change, this will update the index
	 * if $oldNode = null => create node $newNode
	 * if $newNode = null => delete node $oldNode
	 * Else copy or move oldNode to newNode.
	 *
	 * @param AJXP_Node $oldNode
	 * @param AJXP_Node $newNode
	 * @param Boolean $copy
	 */
	public function updateNodeSharedData($oldNode, $newNode = null, $copy = false){
        if($this->accessDriver->getId() == "access.imap") return;
        if($oldNode == null) return;
        $this->loadMetaFileData($oldNode->getUrl());
        if(count(self::$metaCache) && isset(self::$metaCache[$oldNode->getPath()])){
            try{
                self::deleteSharedElement(
                    ($oldNode->isLeaf()?"file":"repository"),
                    self::$metaCache[$oldNode->getPath()],
                    AuthService::getLoggedUser()
                );
                unset(self::$metaCache[$oldNode->getPath()]);
                $this->saveMetaFileData($oldNode->getUrl());
            }catch(Exception $e){

            }
        }
    }

    /** Cypher the publiclet object data and write to disk.
     * @param Array $data The publiclet data array to write
                     The data array must have the following keys:
                     - DRIVER      The driver used to get the file's content
                     - OPTIONS     The driver options to be successfully constructed (usually, the user and password)
                     - FILE_PATH   The path to the file's content
                     - PASSWORD    If set, the written publiclet will ask for this password before sending the content
                     - ACTION      If set, action to perform
                     - USER        If set, the AJXP user
                     - EXPIRE_TIME If set, the publiclet will deny downloading after this time, and probably self destruct.
     * @param AbstractAccessDriver $accessDriver
     * @param Repository $repository
     * @return the URL to the downloaded file
    */
    function writePubliclet($data, $accessDriver, $repository)
    {
    	$downloadFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
    	if(!is_dir($downloadFolder)){
    		return "ERROR : Public URL folder does not exist!";
    	}
    	if(!function_exists("mcrypt_create_iv")){
    		return "ERROR : MCrypt must be installed to use publiclets!";
    	}
        $this->initPublicFolder($downloadFolder);
        $data["PLUGIN_ID"] = $accessDriver->id;
        $data["BASE_DIR"] = $accessDriver->baseDir;
        $data["REPOSITORY"] = $repository;
        if(AuthService::usersEnabled()){
        	$data["OWNER_ID"] = AuthService::getLoggedUser()->getId();
        }
        if($accessDriver->hasMixin("credentials_consumer")){
        	$cred = AJXP_Safe::tryLoadingCredentialsFromSources(array(), $repository);
        	if(isSet($cred["user"]) && isset($cred["password"])){
        		$data["SAFE_USER"] = $cred["user"];
        		$data["SAFE_PASS"] = $cred["password"];
        	}
        }
        // Force expanded path in publiclet
        $data["REPOSITORY"]->addOption("PATH", $repository->getOption("PATH"));
        if ($data["ACTION"] == "") $data["ACTION"] = "download";
        // Create a random key
        $data["FINAL_KEY"] = md5(mt_rand().time());
        // Cypher the data with a random key
        $outputData = serialize($data);
        // Hash the data to make sure it wasn't modified
        $hash = md5($outputData);
        // The initialisation vector is only required to avoid a warning, as ECB ignore IV
        $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND);
        // We have encoded as base64 so if we need to store the result in a database, it can be stored in text column
        $outputData = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, $hash, $outputData, MCRYPT_MODE_ECB, $iv));
        // Okay, write the file:
        $fileData = "<"."?"."php \n".
        '   require_once("'.str_replace("\\", "/", AJXP_INSTALL_PATH).'/publicLet.inc.php"); '."\n".
        '   $id = str_replace(".php", "", basename(__FILE__)); '."\n". // Not using "" as php would replace $ inside
        '   $cypheredData = base64_decode("'.$outputData.'"); '."\n".
        '   $iv = mcrypt_create_iv(mcrypt_get_iv_size(MCRYPT_RIJNDAEL_256, MCRYPT_MODE_ECB), MCRYPT_RAND); '."\n".
        '   $inputData = trim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, $id, $cypheredData, MCRYPT_MODE_ECB, $iv));  '."\n".
        '   if (md5($inputData) != $id) { header("HTTP/1.0 401 Not allowed, script was modified"); exit(); } '."\n".
        '   // Ok extract the data '."\n".
        '   $data = unserialize($inputData); ShareCenter::loadPubliclet($data); ?'.'>';
        if (@file_put_contents($downloadFolder."/".$hash.".php", $fileData) === FALSE){
            return "Can't write to PUBLIC URL";
        }
        PublicletCounter::reset($hash);
        return $this->buildPublicletLink($hash);
    }

    function buildPublicDlURL(){
        $downloadFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
        $dlURL = ConfService::getCoreConf("PUBLIC_DOWNLOAD_URL");
        $langSuffix = "?lang=".ConfService::getLanguage();
        if($dlURL != ""){
        	return rtrim($dlURL, "/");
        }else{
	        $fullUrl = AJXP_Utils::detectServerURL() . dirname($_SERVER['REQUEST_URI']);
	        return str_replace("\\", "/", $fullUrl.rtrim(str_replace(AJXP_INSTALL_PATH, "", $downloadFolder), "/"));
        }
    }

    function buildPublicletLink($hash){
        return $this->buildPublicDlURL()."/".$hash.".php?lang=".ConfService::getLanguage();
    }

    function initPublicFolder($downloadFolder){
        if(is_file($downloadFolder."/down.png")){
            return;
        }
        $language = ConfService::getLanguage();
        $pDir = dirname(__FILE__);
        $messages = array();
        if(is_file($pDir."/res/i18n/".$language.".php")){
            include($pDir."/res/i18n/".$language.".php");
            $messages = $mess;
        }else{
            include($pDir."/res/i18n/en.php");
        }
        $sTitle = sprintf($messages[1], ConfService::getCoreConf("APPLICATION_TITLE"));
        $sLegend = $messages[20];

        @copy($pDir."/res/down.png", $downloadFolder."/down.png");
        @copy($pDir."/res/button_cancel.png", $downloadFolder."/button_cancel.png");
        @copy(AJXP_INSTALL_PATH."/server/index.html", $downloadFolder."/index.html");
        file_put_contents($downloadFolder."/.htaccess", "ErrorDocument 404 ".$this->buildPublicDlURL()."/404.html");
        $content404 = file_get_contents($pDir."/res/404.html");
        $content404 = str_replace(array("AJXP_MESSAGE_TITLE", "AJXP_MESSAGE_LEGEND"), array($sTitle, $sLegend), $content404);
        file_put_contents($downloadFolder."/404.html", $content404);

    }

    /**
     * @static
     * @param Array $data
     * @return void
     */
    static function loadPubliclet($data)
    {
        // create driver from $data
        $className = $data["DRIVER"]."AccessDriver";
        if ($data["EXPIRE_TIME"] && time() > $data["EXPIRE_TIME"])
        {
            // Remove the publiclet, it's done
            if (strstr(realpath($_SERVER["SCRIPT_FILENAME"]),realpath(ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER"))) !== FALSE){
		        $hash = md5(serialize($data));
		        PublicletCounter::delete($hash);
                unlink($_SERVER["SCRIPT_FILENAME"]);
            }

            echo "Link is expired, sorry.";
            exit();
        }
        // Load language messages
        $language = "en";
        if(isSet($_GET["lang"])){
            $language = $_GET["lang"];
        }
        $messages = array();
        if(is_file(dirname(__FILE__)."/res/i18n/".$language.".php")){
            include(dirname(__FILE__)."/res/i18n/".$language.".php");
            $messages = $mess;
        }else{
            include(dirname(__FILE__)."/res/i18n/en.php");
        }

        $AJXP_LINK_HAS_PASSWORD = false;
        $AJXP_LINK_BASENAME = SystemTextEncoding::toUTF8(basename($data["FILE_PATH"]));

        // Check password
        if (strlen($data["PASSWORD"]))
        {
            if (!isSet($_POST['password']) || ($_POST['password'] != $data["PASSWORD"]))
            {
                $AJXP_LINK_HAS_PASSWORD = true;
                $AJXP_LINK_WRONG_PASSWORD = (isSet($_POST['password']) && ($_POST['password'] != $data["PASSWORD"]));
                include (AJXP_INSTALL_PATH."/plugins/action.share/res/public_links.php");
                return;
            }
        }else{
            if (!isSet($_GET["dl"])){
                include (AJXP_INSTALL_PATH."/plugins/action.share/res/public_links.php");
                return;
            }
        }
        $filePath = AJXP_INSTALL_PATH."/plugins/access.".$data["DRIVER"]."/class.".$className.".php";
        if(!is_file($filePath)){
                die("Warning, cannot find driver for conf storage! ($className, $filePath)");
        }
        require_once($filePath);
        $driver = new $className($data["PLUGIN_ID"], $data["BASE_DIR"]);
        $driver->loadManifest();
        if($driver->hasMixin("credentials_consumer") && isSet($data["SAFE_USER"]) && isSet($data["SAFE_PASS"])){
        	// FORCE SESSION MODE
        	AJXP_Safe::getInstance()->forceSessionCredentialsUsage();
        	AJXP_Safe::storeCredentials($data["SAFE_USER"], $data["SAFE_PASS"]);
        }
        $driver->init($data["REPOSITORY"], $data["OPTIONS"]);
        $driver->initRepository();
        ConfService::tmpReplaceRepository($data["REPOSITORY"]);
        // Increment counter
        $hash = md5(serialize($data));
        PublicletCounter::increment($hash);

        AuthService::logUser($data["OWNER_ID"], "", true);
        ConfService::loadRepositoryDriver();
        ConfService::initActivePlugins();
        try{
            $params = array("file" => SystemTextEncoding::toUTF8($data["FILE_PATH"]));
            if(isSet($data["PLUGINS_DATA"])){
                $params["PLUGINS_DATA"] = $data["PLUGINS_DATA"];
            }
            AJXP_Controller::findActionAndApply($data["ACTION"], $params, null);
        }catch (Exception $e){
        	die($e->getMessage());
        }
    }

    function createSharedRepository($httpVars, $repository, $accessDriver){
		// ERRORS
		// 100 : missing args
		// 101 : repository label already exists
		// 102 : user already exists
		// 103 : current user is not allowed to share
		// SUCCESS
		// 200

		if(!isSet($httpVars["repo_label"]) || $httpVars["repo_label"] == ""
			||  !isSet($httpVars["repo_rights"]) || $httpVars["repo_rights"] == ""){
			return 100;
		}
		$loggedUser = AuthService::getLoggedUser();
		$actRights = $loggedUser->getSpecificActionsRights($repository->id);
		if(isSet($actRights["share"]) && $actRights["share"] === false){
			return 103;
		}
        $users = array();
        if(isSet($httpVars["shared_user"]) && !empty($httpVars["shared_user"])){
            $users = array_filter(array_map("trim", explode(",", str_replace("\n", ",",$httpVars["shared_user"]))), array("AuthService","userExists"));
        }
        if(isSet($httpVars["new_shared_user"]) && ! empty($httpVars["new_shared_user"])){
            array_push($users, AJXP_Utils::decodeSecureMagic($httpVars["new_shared_user"], AJXP_SANITIZE_ALPHANUM));
        }
		//$userName = AJXP_Utils::decodeSecureMagic($httpVars["shared_user"], AJXP_SANITIZE_ALPHANUM);
		$label = AJXP_Utils::decodeSecureMagic($httpVars["repo_label"]);
		$rights = $httpVars["repo_rights"];
		if($rights != "r" && $rights != "w" && $rights != "rw") return 100;

        if(isSet($httpVars["repository_id"])){
            $editingRepo = ConfService::getRepositoryById($httpVars["repository_id"]);
        }

		// CHECK USER & REPO DOES NOT ALREADY EXISTS
		$repos = ConfService::getRepositoriesList();
		foreach ($repos as $obj){
			if($obj->getDisplay() == $label && (!isSet($editingRepo) || $editingRepo != $obj)){
				return 101;
			}
		}
		$confDriver = ConfService::getConfStorageImpl();

		// CREATE SHARED OPTIONS
        $options = $accessDriver->makeSharedRepositoryOptions($httpVars, $repository);
        $customData = array();
        foreach($httpVars as $key => $value){
            if(substr($key, 0, strlen("PLUGINS_DATA_")) == "PLUGINS_DATA_"){
                $customData[substr($key, strlen("PLUGINS_DATA_"))] = $value;
            }
        }
        if(count($customData)){
            $options["PLUGINS_DATA"] = $customData;
        }
        if(isSet($editingRepo)){
            $newRepo = $editingRepo;
            $newRepo->setDisplay($label);
            $newRepo->options = array_merge($newRepo->options, $options);
            ConfService::replaceRepository($httpVars["repository_id"], $newRepo);
        }else{
            $newRepo = $repository->createSharedChild(
                $label,
                $options,
                $repository->id,
                $loggedUser->id,
                null
            );
            ConfService::addRepository($newRepo);
        }


        if(isSet($httpVars["original_users"])){
            $originalUsers = explode(",", $httpVars["original_users"]);
            $removeUsers = array_diff($originalUsers, $users);
            if(count($removeUsers)){
                foreach($removeUsers as $user){
                    if(AuthService::userExists($user)){
                        $userObject = $confDriver->createUserObject($user);
                        $userObject->removeRights($newRepo->getId());
                        $userObject->save();
                    }
                }
            }
        }

        foreach($users as $userName){
            if(AuthService::userExists($userName)){
                // check that it's a child user
                $userObject = $confDriver->createUserObject($userName);
                if( ConfService::getCoreConf("ALLOW_CROSSUSERS_SHARING") !== true && ( !$userObject->hasParent() || $userObject->getParent() != $loggedUser->id ) ){
                    return 102;
                }
            }else{
                if(!isSet($httpVars["shared_pass"]) || $httpVars["shared_pass"] == "") return 100;
                AuthService::createUser($userName, md5($httpVars["shared_pass"]));
                $userObject = $confDriver->createUserObject($userName);
                $userObject->clearRights();
                $userObject->setParent($loggedUser->id);
            }
            // CREATE USER WITH NEW REPO RIGHTS
            $userObject->setRight($newRepo->getId(), $rights);
            $userObject->setSpecificActionRight($newRepo->getId(), "share", false);
            $userObject->save();
        }

        // METADATA
        if(!isSet($editingRepo)){
            $file = AJXP_Utils::decodeSecureMagic($httpVars["file"]);
            $this->loadMetaFileData($this->urlBase.$file);
            self::$metaCache[$file] =  $newRepo->getId();
            $this->saveMetaFileData($this->urlBase.$file);
        }

    	return 200;
    }


    /**
     * @static
     * @param String $type
     * @param String $element
     * @param AbstractAjxpUser $loggedUser
     * @return void
     */
    public static function deleteSharedElement($type, $element, $loggedUser){
        $mess = ConfService::getMessages();
        if($type == "repository"){
            $repo = ConfService::getRepositoryById($element);
            if(!$repo->hasOwner() || $repo->getOwner() != $loggedUser->getId()){
                throw new Exception($mess["ajxp_shared.12"]);
            }else{
                $res = ConfService::deleteRepository($element);
                if($res == -1){
                    throw new Exception($mess["ajxp_conf.51"]);
                }
            }
        }else if( $type == "user" ){
            $confDriver = ConfService::getConfStorageImpl();
            $object = $confDriver->createUserObject($element);
            if(!$object->hasParent() || $object->getParent() != $loggedUser->getId()){
                throw new Exception($mess["ajxp_shared.12"]);
            }else{
                AuthService::deleteUser($element);
            }
        }else if( $type == "file" ){
            $publicletData = self::loadPublicletData($element);
            if(isSet($publicletData["OWNER_ID"]) && $publicletData["OWNER_ID"] == $loggedUser->getId()){
                PublicletCounter::delete($element);
                unlink($publicletData["PUBLICLET_PATH"]);
            }else{
                throw new Exception($mess["ajxp_shared.12"]);
            }
        }
    }

    public static function sharedElementExists($type, $element, $loggedUser){
        if($type == "repository"){
            return (ConfService::getRepositoryById($element) != null);
        }else if($type == "file"){
            $dlFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
            return is_file($dlFolder."/".$element.".php");
        }
    }


    public static function loadPublicletData($id){
        $dlFolder = ConfService::getCoreConf("PUBLIC_DOWNLOAD_FOLDER");
        $file = $dlFolder."/".$id.".php";
        $lines = file($file);
        $inputData = '';
        $code = $lines[3] . $lines[4] . $lines[5];
        eval($code);
        $dataModified = (md5($inputData) != $id);
        $publicletData = unserialize($inputData);
        $publicletData["SECURITY_MODIFIED"] = $dataModified;
        $publicletData["DOWNLOAD_COUNT"] = PublicletCounter::getCount($id);
        $publicletData["PUBLICLET_PATH"] = $file;
        return $publicletData;
    }


	protected function loadMetaFileData($currentFile){
		if(preg_match("/\.zip\//",$currentFile)){
            self::$fullMetaCache = array();
			self::$metaCache = array();
			return ;
		}
        $local = ($this->pluginConf["METADATA_FILE_LOCATION"] == "infolders");
        if($local){
            $metaFile = dirname($currentFile)."/".$this->pluginConf["METADATA_FILE"];
            if(self::$currentMetaName == $metaFile && is_array(self::$metaCache))return;
        }else{
            $metaFile = AJXP_DATA_PATH."/plugins/action.share/".$this->pluginConf["METADATA_FILE"];
            if(is_array(self::$metaCache)) return;
        }
		if(is_file($metaFile) && is_readable($metaFile)){
            self::$currentMetaName = $metaFile;
			$rawData = file_get_contents($metaFile);
            self::$fullMetaCache = unserialize($rawData);
			self::$metaCache = self::$fullMetaCache[AuthService::getLoggedUser()->getId()][$this->repository->getId()];

		}else{
            self::$fullMetaCache = array();
			self::$metaCache = array();
		}
	}

	protected function saveMetaFileData($currentFile){
        $local = ($this->pluginConf["METADATA_FILE_LOCATION"] == "infolders");
        if($local){
            $metaFile = dirname($currentFile)."/".$this->pluginConf["METADATA_FILE"];
        }else{
            if(!is_dir(AJXP_DATA_PATH."/plugins/action.share/")){
                mkdir(AJXP_DATA_PATH."/plugins/action.share/", 0666, true);
            }
            $metaFile = AJXP_DATA_PATH."/plugins/action.share/".$this->pluginConf["METADATA_FILE"];
        }
		if((is_file($metaFile) && call_user_func(array($this->accessDriver, "isWriteable"), $metaFile)) || call_user_func(array($this->accessDriver, "isWriteable"), dirname($metaFile)) || (!$local) ){
            if(!isset(self::$fullMetaCache[AuthService::getLoggedUser()->getId()])){
                self::$fullMetaCache[AuthService::getLoggedUser()->getId()] = array();
            }
            self::$fullMetaCache[AuthService::getLoggedUser()->getId()][$this->repository->getId()] = self::$metaCache;
			$fp = fopen($metaFile, "w");
            if($fp !== false){
                @fwrite($fp, serialize(self::$fullMetaCache), strlen(serialize(self::$fullMetaCache)));
                @fclose($fp);
            }
			AJXP_Controller::applyHook("version.commit_file", $metaFile);
		}
	}

}
