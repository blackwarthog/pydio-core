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

/**
 * Encapsulates calls to Image Magick to extract JPG previews of PDF, PSD, TIFF, etc.
 * @package AjaXplorer_Plugins
 * @subpackage Editor
 */
class RenderchanPreviewer extends AJXP_Plugin
{
    public function switchAction($action, $httpVars, $filesVars)
    {
        $sourcePath    = $this->getFilteredOption("SOURCE_PATH");
        $thumbPath     = $this->getFilteredOption("THUMB_PATH");
        $thumbSuffix   = $this->getFilteredOption("THUMB_SUFFIX");
        $previewPath   = $this->getFilteredOption("PREVIEW_PATH");
        $previewSuffix = $this->getFilteredOption("PREVIEW_SUFFIX");
        
        if (!empty($sourcePath) && substr($sourcePath,  -1) != '/' && substr($sourcePath,  -1) != '\\') $sourcePath  .= '/';
        if (!empty($thumbPath)  && substr($thumbPath,   -1) != '/' && substr($thumbPath,   -1) != '\\') $thumbPath   .= '/';
        if (!empty(previewPath) && substr($previewPath, -1) != '/' && substr($previewPath, -1) != '\\') $previewPath .= '/';
        
        $file = $httpVars['file'];
        if (strpos($file, "base64encoded:") === 0)
            $file = base64_decode(array_pop(explode(':', $file, 2)));
        $file = AJXP_Utils::securePath($file);
        $file = AJXP_Utils::decodeSecureMagic($file);
        if (substr($file, 0, 1) == '/' || substr($file, 0, 1) == '\\')
            $file = substr($file, 1);
        
        $root = AJXP_MetaStreamWrapper::getRealFSReference("pydio://".ConfService::getCurrentRepositoryId()."/");
        $file_full = $root.$file;
        
        if (!empty($sourcePath) && substr($file_full, 0, strlen($sourcePath)) != $sourcePath)
            return false;
        
        $path   = "";
        $suffix = "";
        if ($action == "renderchan_get_thumbnail") {
            $path   = $thumbPath;
            $suffix = $thumbSuffix;
        } else
        if ($action == "renderchan_get_preview") {
            $path   = $previewPath;
            $suffix = $previewSuffix;
        } else {
            return false;
        }
        
        if (isSet($httpVars["json"]) && $httpVars["json"]) { 
            $files = array();
            $list = scandir(dirname($file_full));
            foreach($list as $f) {
                if ($f != "." && $f != "..") {
                    $f_full = dirname($file_full) . DIRECTORY_SEPARATOR . $f;
                    $f_small = !empty($sourcePath) && !empty($path)
                             ? $path . substr($f_full, strlen($sourcePath)) . $suffix
                             : $f_full . $suffix;
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
            $file_small = !empty($sourcePath) && !empty($path)
                        ? $path . substr($file_full, strlen($sourcePath)) . $suffix
                        : $file_full . $suffix;
        	header("Content-Type: image/jpeg; name=\"".basename($file_small)."\"");
            header('Cache-Control: public');
            header("Pragma:");
            header("Last-Modified: " . gmdate("D, d M Y H:i:s", time()-10000) . " GMT");
            header("Expires: " . gmdate("D, d M Y H:i:s", time()+5*24*3600) . " GMT");
            readfile($file_small);
            return false;
        }
    }
}
