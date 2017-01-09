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

use Pydio\Access\Core\MetaStreamWrapper;
use Pydio\Access\Core\Model\UserSelection;
use Pydio\Core\Model\ContextInterface;
use Pydio\Core\PluginFramework\Plugin;
use Pydio\Core\Utils\Vars\InputFilter;
use Pydio\Core\Utils\Vars\StatHelper;

class RenderchanPreviewer extends Plugin
{
	private function searchPath($currentpath, $root, $fullpath, $dirs, $suffixes)
	{
		// search in subdirectories by $dirs array
		if ($currentpath !== $fullpath)
		{
			$separator = strlen($currentpath) > 0 ? substr($currentpath, strlen($currentpath) - 1) : '';
			if ($separator != '\\') $separator = '/';
			foreach($dirs as $dir)
			{
				  
				$subpath = $currentpath . $dir . $separator . substr($fullpath, strlen($currentpath));
				foreach($suffixes as $suffix)
				{
					if (file_exists($subpath.$suffix))
						return $subpath.$suffix;
				}
			}
		}
		
		// search parent directory
		if (strlen($currentpath) > strlen($root))
		{
			$pos_a = strrpos($currentpath, '/', -2);
			$pos_b = strrpos($currentpath, '\\', -2);
			$pos = $pos_a === false ? $pos_b : ($pos_b === false ? $pos_a : max($pos_a, $pos_b));
			if ($pos !== false && !empty($dirs))
			{
				$result = $this->searchPath(substr($currentpath, 0, $pos + 1), $root, $fullpath, $dirs, $suffixes);
				if ($result !== "") return $result;
			}
		}
		
		// search in current directory
		if ($currentpath === $fullpath)
		{
			foreach($suffixes as $suffix)
				if (file_exists($currentpath.$suffix))
					return $currentpath.$suffix;
		}

		return "";
	}
	
	private function splitNames($list)
	{
		$names = array();
		foreach(explode(',', $list) as $sub)
		{
			$s = trim($sub);
			if ($s !== "")
				array_push($names, $s);
		}
		return $names;
	}
	
    public function switchAction($action, $httpVars, $filesVars, ContextInterface $contextInterface)
    {
    	$thumbDirs       = $this->splitNames( $this->getContextualOption($contextInterface, "THUMB_DIRS") );
        $thumbSuffixes   = $this->splitNames( $this->getContextualOption($contextInterface, "THUMB_SUFFIXES") );
        $previewDirs     = $this->splitNames( $this->getContextualOption($contextInterface, "PREVIEW_DIRS") );
        $previewSuffixes = $this->splitNames( $this->getContextualOption($contextInterface, "PREVIEW_SUFFIXES") );
        
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
        $root = MetaStreamWrapper::getRealFSReference("pydio://".$userId."@".$repoId."/");
        $file_full = $root.$file;
        
        $dirs     = array();
        $suffixes = array();
        if ($action == "renderchan_get_thumbnail") {
            $dirs     = $thumbDirs;
            $suffixes = $thumbSuffixes;
        } else
        if ($action == "renderchan_get_preview") {
            $dirs   = $previewDirs;
            $suffixes = $previewSuffixes;
        } else {
            return false;
        }
        
        if (isSet($httpVars["json"]) && $httpVars["json"]) { 
        	$files = array();
            $list = scandir(dirname($file_full));
            foreach($list as $f) {
                if ($f != "." && $f != "..") {
                    $f_full = dirname($file_full) . DIRECTORY_SEPARATOR . $f;
                    $f_small = $this->searchPath($f_full, "/", $f_full, $dirs, $suffixes);
                    if (file_exists($f_small)) {
                        array_push($files, array(
                            'file' => DIRECTORY_SEPARATOR . dirname($file) . DIRECTORY_SEPARATOR . $f,
                            'width' => 0,
                            'height' => 0 ));
                    }
                }
            }
            
            header("Content-Type: application/json");
            print(json_encode($files));
            return false;
        } else {
        	$file_small = $this->searchPath($file_full, "/", $file_full, $dirs, $suffixes);
        	header("Content-Type: ".StatHelper::getImageMimeType(basename($file_small))."; name=\"".basename($file_small)."\"");
        	header('Cache-Control: public');
            header("Pragma:");
            header("Last-Modified: " . gmdate("D, d M Y H:i:s", time()-10000) . " GMT");
            header("Expires: " . gmdate("D, d M Y H:i:s", time()+5*24*3600) . " GMT");
            readfile($file_small);
            return false;
        }
    }
}
