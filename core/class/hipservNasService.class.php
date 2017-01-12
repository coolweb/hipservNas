<?php

/* This file is part of Jeedom.
 *
 * Jeedom is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Jeedom is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Jeedom. If not, see <http://www.gnu.org/licenses/>.
 */

class hipservNasService
{
    public $cloudServerAddress = 'https://www.lifecloudmedion.com';
    public $nasUrl = '';
    public $authToken = '';
    public $userSession = '';
    public $authCookie = null;
    public $userURI = '';
    public $rootMediaSources = array();

    public function logDebug($message)
    {
        log::add('hipservNas', 'debug', $message);
    }

    /**
     * Function to get xml response for a request.
     * @returns The result xml.
     */
    public function getXml($url, $data, $method = 'POST')
    {
        $logger = log::getLogger('hipservNas');
        log::add('hipservNas', 'debug', "getXml called");

        $header = '';

        $options = array(
                'http' => array(
                    'header'  => $header,
                    'method'  => $method,
                    'content' => $data,
                    'ignore_errors' => false
                )
            );

        if ($this->authCookie != null) {
            $header = $header . 'Cookie: ' .
                        $this->authCookie->name .
                        '=' . $this->authCookie->value .
                        ';\r\n';
        }        

        if ($data != null) {
            log::add('hipservNas', 'debug', "Build context");
            $header = $header . 'Content-Type: application/xml; charset=\"UTF-8\"\r\n' .
                                 'Accept: */*\r\n' .
                                 'Accept-Encoding: gzip,deflate,sdch\r\n' .
                                 'Content-Length:' . strlen($data) . '\r\n';
        }

        $options['http']['header'] = $header;
        $context  = stream_context_create($options);
        $result = file_get_contents($url, false, $context);
        
        log::add('hipservNas', 'debug', "getXml call options:" . print_r($options, true));
        log::add('hipservNas', 'debug', "getXml call:" . $url . " with data: " . $data);
        log::add('hipservNas', 'debug', "getXml result:" . $result);
        log::add('hipservNas', 'debug', "getXml response header:" . print_r($http_response_header, true));

        if ($this->authCookie == null) {
            log::add('hipservNas', 'debug', "Search auth cookie");
            foreach ($http_response_header as $s) {
                if (preg_match('|^Set-Cookie:\s*([^=]+)=([^;]+);(.+)$|', $s, $parts)) {
                    log::add('hipservNas', 'debug', "getXml cookie received:" .
                    $parts[1] . ' ' . $parts[2]);

                    if ($parts[1] == 'HOMEBASEID') {
                        log::add('hipservNas', 'debug', "Auth cookie found");
                        $this->authCookie = new cookie();
                        $this->authCookie->name = $parts[1];
                        $this->authCookie->value = $parts[2];
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Retrieve an authentification token, store it in
     * authToken, and store the nas url to nasUrl.
     */
    public function getAuthentificationToken($nasName, $userName, $password)
    {
        $this->logDebug("hipservNasService - getAuthentificationToken");
        
        $request = '<?xml version="1.0"?><session hipserv="' . $nasName . '" username="' . $userName . '" password="' . $password . '"/>';
        $url = $this->cloudServerAddress . '/rest/1.0/sessions/hipserv';
        $responseXml = $this->getXml($url, $request);

        $responseObject = simplexml_load_string($responseXml);
        $this->logDebug("doAuthentification response:" . print_r($responseObject, true));

        $returnCode = $responseObject['code'];

        if ($returnCode != '0') {
            return 'Return code ' . $returnCode . ' error ' . $responseObject['description'];
        }

        $this->authToken = $responseObject->session['auth'];
        $this->nasUrl = $responseObject->session['url'];

        $this->logDebug("doAuthentification token:" . $this->authToken .
            'nas url:' . $this->nasUrl);
        return '';
    }
    
    /**
     * do logon, you need to retrieve an authToken before by calling getAuthentificationToken.
     */
    public function logon()
    {
        $this->logDebug("hipservNasService - logon");
        
        $request = '<?xml version="1.0"?><session code="' . $this->authToken . '"/>';
        $url = $this->nasUrl . '/api/2.0/rest/sessions';
        $responseXml = $this->getXml($url, $request);

        $responseObject = simplexml_load_string($responseXml);
        $this->userURI = $responseObject['userURI'];

        $this->logDebug("logon response:" . print_r($responseObject, true));
    }
    
    public function shutdown()
    {
        $this->logDebug("hipservNasService - shutdown");

        $request = '<?xml version="1.0"?><command name="shutdown"/>';
        $url = $this->nasUrl . '/api/2.0/rest/server/config/admin';
        $responseXml = $this->getXml($url, $request, 'PUT');
    }

    public function restart()
    {
        $this->logDebug("hipservNasService - restart");

        $request = '<?xml version="1.0"?><command name="restart"/>';
        $url = $this->nasUrl . '/api/2.0/rest/server/config/admin';
        $responseXml = $this->getXml($url, $request, 'PUT');
    }
    
    public function getUserInformation()
    {
        $this->logDebug("hipservNasService - getUserInformation");

        $url = $this->nasUrl . $this->userURI;
        $responseXml = $this->getXml($url, null, 'GET');

        $responseObject = simplexml_load_string($responseXml);

        foreach ($responseObject->mediaSources->mediaSource as $element) {
            $mediaSource = new mediaSource();
            $mediaSource->id = $element['id'];
            $mediaSource->name = $element['name'];
            $mediaSource->href = $element['href'];
            $mediaSource->type = 'folder';

            array_push($this->rootMediaSources, $mediaSource);
            
            $this->logDebug("hipservNasService - getUserInformation, found media source:" .
            $mediaSource->id . ' - ' . $mediaSource->name);
        }
    }

    /**
     * Get the content of a directory.
     * @param string $directoryUri The uri or href of the directory to get the content.
     * @return array of media source.
     */
    public function getDirectoryContent($directoryUri)
    {
        $this->logDebug("hipservNasService - getDirectoryContents: " . $directoryUri);

        $url = $this->nasUrl . $directoryUri . '?mediafilter=document';
        $responseXml = $this->getXml($url, null, 'GET');

        $responseObject = simplexml_load_string($responseXml);
        $mediaSources = array();

        foreach ($responseObject->file as $file) {
            $mediaSource = new mediaSource();
            $mediaSource->name = (string)$file['name'];
            $mediaSource->href= (string)$file['href'];
            $mediaSource->type = (string)$file['type'];

            array_push($mediaSources, $mediaSource);
        }

        return $mediaSources;
    }

    /**
     * Returns the last child directory of the path
     * @param string path The path for which to get the directory
     * @return mediaSource The found directory or null if not found
     */
    public function getDirectoryOfPath($path)
    {
        $this->logDebug("hipservNasService - getDirectoryOfPath: " . $path);
        
        $folders = explode('/', $path);
        $isFirst = true;
        $currentDirectory = null;
        
        foreach ($folders as $folder) {
            $this->logDebug('Search part: ' . $folder);
            if ($folder !== '') {
                if ($isFirst) {
                    $isFirst = false;
                    
                    $this->logDebug('Search root folder ' . $folder);
                    foreach ($this->rootMediaSources as $root) {
                        if ($root->name == $folder) {
                            $this->logDebug('root folder found');
                            $currentDirectory = $root;
                        }
                    }

                    if ($currentDirectory == null) {
                        $this->logDebug('root folder not found');
                        return null;
                    }
                } else {
                    $this->logDebug('Search folder ' . $folder);

                    $mediaSources = $this->getDirectoryContent($currentDirectory->href);
                    $folderFound = false;

                    foreach ($mediaSources as $mediaSource) {
                        if ($mediaSource->type == 'folder') {
                            if ($mediaSource->name == $folder) {
                                $this->logDebug('Folder found');
                                $currentDirectory = $mediaSource;
                                $folderFound = true;
                            }
                        } else {
                            $this->logDebug('item is not a folder, it is a ' . $mediaSource->type);
                        }
                    }

                    if ($folderFound == false) {
                        $this->logDebug('Folder not found');
                        return null;
                    }
                }
            }
        }

        return $currentDirectory;
    }

    /**
     * Upload a local file to the nas
     * @param string $fileToUpload The path of the file to upload
     * @param mediaSource The folder where to upload the file
     * @return string empty if success or error message
     */
    public function uploadFile($fileToUpload, $mediaSourceFolder)
    {
        $this->logDebug('hipservNasService - uploadFile: ' . $fileToUpload);
        $splitPath = explode('/', $fileToUpload);
        $fileName = $splitPath[count($splitPath) - 1];

        $url = $this->nasUrl . '/filemanager/done_upload';

        $cFile = new CurlFile($fileToUpload, 'application/octet-stream', $fileName);

        $headers = array("Content-Type:multipart/form-data",
         'Cookie:' . $this->authCookie->name . '=' . $this->authCookie->value . ';');

        $this->logDebug(print_r($postFields, true));

        $ch = curl_init();
        $options = array(
            CURLOPT_URL => $url,
            CURLOPT_HEADER => 1,
            CURLOPT_POST => 1,
            CURLOPT_SSL_VERIFYPEER => 0,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $postfields,
            CURLOPT_RETURNTRANSFER => 1
        ); // cURL options
        
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // stop verifying certificate
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($ch, CURLOPT_POST, true); // enable posting
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_POSTFIELDS, array(
            'np' => $mediaSourceFolder->href,
            'ACTION' => 'UPLOAD_FILE',
            'file[0]' => $cFile
        )); // post file 

        curl_exec($ch);

        $errmsg = 'error';
        $info = curl_getinfo($ch);
        $this->logDebug(print_r($info, true));
        
        if (!curl_errno($ch)) {            
            if ($info['http_code'] == 200) {
                $errmsg = '';
            } else {
                $errmsg = print_r($info, true);
            }
        } else {
            $errmsg = curl_error($ch) . ' - ' . print_r($info, true);
        }

        curl_close($ch);

        return $errmsg;
    }
}