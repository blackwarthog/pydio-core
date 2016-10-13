<?php
/*
 * Copyright 2007-2013 Charles du Jeu - Abstrium SAS <team (at) pyd.io>
 * This file is part of Pydio.
 *
 * Pydio is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Pydio is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with Pydio.  If not, see <http://www.gnu.org/licenses/>.
 *
 * The latest code can be found at <http://pyd.io/>.
 */

defined('AJXP_EXEC') or die( 'Access not allowed');

use \Psr\Http\Message\ServerRequestInterface;
use \Psr\Http\Message\ResponseInterface;

use Pydio\Access\Core\MetaStreamWrapper;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Controller\Controller;
use Pydio\Core\Exception\PydioException;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\Utils\Vars\InputFilter;

class PluginDownloadDeps extends Plugin
{
    public function receiveAction(ServerRequestInterface &$requestInterface, ResponseInterface &$responseInterface)
    {
        $action           = $requestInterface->getAttribute("action");
        $httpVars         = $requestInterface->getParsedBody();
        $contextInterface = $requestInterface->getAttribute("ctx");

        if ($action == "download-deps") {
        	// config
        	$url_template = $this->getContextualOption($contextInterface, "RENDERCHAN_URL_TEMPLATE");
        	$local_root   = $this->getContextualOption($contextInterface, "RENDERCHAN_LOCAL_ROOT");
        	$remote_root  = $this->getContextualOption($contextInterface, "RENDERCHAN_REMOTE_ROOT");
        	
        	if (substr($server, -1) == '/')
        		$server .= substr($server, 0, strlen($server) - 1);
        	if (substr($local_root, -1) != '/' && substr($local_root, -1) != '\\')
        		$local_root .= '/';
        	if (substr($remote_root, -1) != '/' && substr($remote_root, -1) != '\\')
        		$remote_root .= '/';
        	if (substr($remote_root, 0, 1) == '/')
       			$remote_root = substr($remote_root, 1);
        	
            $selection = UserSelection::fromContext($contextInterface, $httpVars);
            $file = $selection->getUniqueFile();
        	if (strpos($file, "base64encoded:") === 0)
        		$file = base64_decode(array_pop(explode(':', $file, 2)));
        	$file = InputFilter::securePath($file);
        	$file = InputFilter::decodeSecureMagic($file);
        	if (substr($file, 0, 1) == '/' || substr($file, 0, 1) == '\\')
        		$file = substr($file, 1);
        	
            $userId=$contextInterface->getUser()->getId();
            $repoId=$contextInterface->getRepositoryId();
            $dir = MetaStreamWrapper::getRealFSReference("pydio://".$userId."@".$repoId."/");
        	
        	// path
			if ($local_root != substr($dir.$file, 0, strlen($local_root)))
				throw new PydioException("Cannot request dependencies for file from outside of renderchan local root");
			$remote_file = $remote_root.substr($dir.$file, strlen($local_root));
			
			// ask renderchan
			$remote_file_url = str_replace('%2F', '/', rawurlencode($remote_file));
			$url = str_replace('%s', $remote_file_url, $url_template);
			$reply = json_decode(file_get_contents($url));
			
			// parse reply
			if (!is_object($reply) || !isset($reply->files) || !is_array($reply->files))
				throw new PydioException("Wrong reply from renderchan");
			$index = 0;
			$newParams = array();
			$newParams["archive_name"] = basename($file).".zip";
			foreach($reply->files as $f) {
				if (!is_object($f) || !isset($f->source) || !is_string($f->source)) continue;
				$f = $f->source;

				if (strlen($remote_root)) {
					if ($remote_root != substr($f, 0, strlen($remote_root))) continue;
					$f = substr($f, strlen($remote_root));
				} else {
					if (substr($f, 0, 1) == '/' || substr($f, 0, 1) == '\\')
						$f = substr($f, 1);
				}

				$f = $local_root.$f;
        		if ($dir != substr($f, 0, strlen($dir))) continue;
				$f = substr($f, strlen($dir));
				
				$newParams['file_'.$index] = '/'.$f;
				++$index;
			}
			
			// call download
            $downloadRequest = Controller::executableRequest($contextInterface, "download", $newParams);
            $responseInterface = Controller::run($downloadRequest);
        }
    }
}
