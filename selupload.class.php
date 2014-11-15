<?php

class selupload_SelectelStorage
{

    /**
     * Throw exception on Error
     *
     * @var boolean
     */
    protected static $throwExcaptions = false;
    /**
     * Header string in array for authtorization.
     *
     * @var array()
     */
    protected $token = array();
    /**
     * Storage url
     *
     * @var string
     */
    protected $url = '';
    /**
     * The response format
     *
     * @var string
     */
    protected $format = '';
    /**
     * Allowed response formats
     *
     * @var array
     */
    protected $formats = array('', 'json', 'xml');

    /**
     * Creating Selectel Storage PHP class
     *
     * @param string $user Account id
     * @param string $key Storage key
     * @param string $server Authorization server
     * @param string $format Allowed response formats
     *
     * @return selupload_SelectelStorage
     */
    public function __construct($user, $key, $server = 'auth.selcdn.ru', $format = null)
    {
        $user = trim($user);
        $key = trim($key);
        $server = trim($server);
        $header = selupload_sCurl::init('https://' . $server . '/')
            ->setHeaders(array("X-Auth-User: {$user}", "X-Auth-Key: {$key}"))
            ->request("GET")
            ->getHeaders();

        if ($header["HTTP-Code"] != 204) {
            return $this->error($header["HTTP-Code"], 'Error connecting to storage.');
        }

        $this->format = (!in_array($format, $this->formats, true) ? $this->format : $format);
        $this->url = $header['x-storage-url'];
        $this->token = array("X-Auth-Token: {$header['x-storage-token']}");

        return true;
    }

    /**
     * Handle errors
     *
     * @param integer $code Error code
     * @param string $message Error message
     *
     * @return integer
     *
     * @throws SelectelStorageException
     *
     */
    protected function error($code, $message)
    {
        if (self::$throwExcaptions) {
            throw new SelectelStorageException ('<div id="message" class="error"><p><b>' . $message . '</b></p></div>', $code);
        }
        return $code;
    }

    /**
     * Getting storage info
     *
     * @return array
     */
    public function getInfo()
    {
        $head = selupload_sCurl::init($this->url)
            ->setHeaders($this->token)
            ->request("HEAD")
            ->getHeaders();
        return $this->getX($head);
    }

    /**
     * Select only 'x-' from headers
     *
     * @param array $headers Array of headers
     *
     * @return array
     */
    protected static function getX($headers)
    {
        $result = array();
        foreach ($headers as $key => $value) {
            if (stripos($key, "x-") === 0) {
                $result[$key] = $value;
            }
        }
        return $result;
    }

    /**
     * Getting containers list
     *
     * @param integer $limit Limit (Default 10000)
     * @param string $marker Marker (Default '')
     * @param string $format Format ('', 'json', 'xml') (Default self::$format)
     *
     * @return array
     */
    public function listContainers($limit = 10000, $marker = '', $format = null)
    {
        $params = array(
            'limit' => $limit,
            'marker' => $marker,
            'format' => (!in_array($format, $this->formats, true) ? $this->format : $format)
        );

        $cont = selupload_sCurl::init($this->url)
            ->setHeaders($this->token)
            ->setParams($params)
            ->request("GET")
            ->getContent();

        if ($params['format'] == '') {
            return explode("\n", trim($cont));
        }

        return array(trim($cont));
    }

    /**
     * Create container by name.
     * Headers for
     *
     * @param string $name
     * @param array $headers
     *
     * @return selupload_SelectelContainer
     */
    public function createContainer($name, $headers = array())
    {
        $headers = array_merge($this->token, $headers);
        $info = selupload_sCurl::init($this->url . $name)
            ->setHeaders($headers)
            ->request("PUT")
            ->getInfo();

        if (!in_array($info["http_code"], array(201, 202))) {
            return $this->error($info["http_code"], __METHOD__);
        }

        return $this->getContainer($name);
    }

    /**
     * Select container by name
     *
     * @param string $name
     *
     * @return selupload_SelectelContainer
     */
    public function getContainer($name)
    {
        $url = $this->url . $name;
        $headers = selupload_sCurl::init($url)
            ->setHeaders($this->token)
            ->request("HEAD")
            ->getHeaders();

        if (!in_array($headers["HTTP-Code"], array(204))) {
            return $this->error($headers["HTTP-Code"], 'Incorrectly selected container');
        }

        return new selupload_SelectelContainer ($url, $this->token, $this->format, $this->getX($headers));
    }

    /**
     * Deleteing container or object by name
     *
     * @param string $name
     *
     * @return integer
     */
    public function delete($name)
    {
        $info = selupload_sCurl::init($this->url . $name)
            ->setHeaders($this->token)
            ->request("DELETE")
            ->getInfo();

        if (!in_array($info["http_code"], array(204))) {
            return $this->error($info["http_code"], __METHOD__);
        }

        return $info["http_code"];
    }

    /**
     * Copy
     *
     * @param string $origin Origin object
     * @param string $destin Destination
     *
     * @return array
     */
    public function copy($origin, $destin)
    {
        $destin = $this->url . $destin;
        $headers = array_merge($this->token, array("Destination: {$destin}"));
        $info = selupload_sCurl::init($this->url . $origin)
            ->setHeaders($headers)
            ->request("COPY")
            ->getResult();

        return $info;
    }

    public function setContainerHeaders($name, $headers)
    {
        $headers = $this->getX($headers, "X-Container-Meta-");
        if (get_class($this) != 'SelectelStorage') {
            return false;
        }

        return $this->setMetaInfo($name, $headers);
    }

    /**
     * Setting meta info
     *
     * @param string $name Name of object
     * @param array $headers Headers
     *
     * @return integer
     */
    protected function setMetaInfo($name, $headers)
    {
        if ((get_class($this) == 'SelectelStorage') or (get_class($this) == 'SelectelContainer')) {
            $headers = $this->getX($headers, "X-Container-Meta-");
        } else {
            return false;
        }

        $info = selupload_sCurl::init($this->url . $name)
            ->setHeaders($headers)
            ->request("POST")
            ->getInfo();

        if (!in_array($info["http_code"], array(204))) {
            return $this->error($info["http_code"], __METHOD__);
        }

        return $info["http_code"];
    }

}

class selupload_SelectelContainer extends selupload_SelectelStorage
{

    /**
     * 'x-' Headers of container
     *
     * @var array
     */
    private $info;

    public function __construct($url, $token = array(), $format = null, $info = array())
    {
        $this->url = $url . "/";
        $this->token = $token;
        $this->format = (!in_array($format, $this->formats, true) ? $this->format : $format);
        count($info) == 0 ? $this->info = $this->getInfo(true) : $this->info = array();

        return true;
    }

    /**
     * Getting container info
     *
     * @param boolean $refresh Refresh Default false
     *
     * @return array|integer returns an error code (integer) or container info (array)
     */
    public function getInfo($refresh = false)
    {
        if (!$refresh) {
            return $this->info;
        }

        $headers = selupload_sCurl::init($this->url)
            ->setHeaders($this->token)
            ->request("HEAD")
            ->getHeaders();

        if (!in_array($headers["HTTP-Code"], array(204))) {
            return $this->error($headers["HTTP-Code"], __METHOD__);
        }

        return $this->info = $this->getX($headers);
    }

    /**
     * Getting file with info and headers
     *
     * Supported headers:
     * If-Match
     * If-None-Match
     * If-Modified-Since
     * If-Unmodified-Since
     *
     * @param string $name
     * @param array $headers
     *
     * @return array
     */
    public function getFile($name, $headers = array())
    {
        $headers = array_merge($headers, $this->token);
        $res = selupload_sCurl::init($this->url . $name)
            ->setHeaders($headers)
            ->request("GET")
            ->getResult();
        return $res;
    }

    /**
     * Getting file info
     *
     * @param string $name File name
     *
     * @return string a JSON encoded string on success or array.
     */
    public function getFileInfo($name)
    {
        $res = $this->listFiles(1, '', $name, null, null, 'json');
        $info = current(json_decode($res, true));
        return $this->format == 'json' ? json_encode($info) : $info;
    }

    /**
     * Getting file list
     *
     * @param integer $limit Limit
     * @param string $marker Marker
     * @param string $prefix Prefix
     * @param string $path Path
     * @param string $delimiter Delemiter
     * @param string $format Format
     *
     * @return array
     */
    public function listFiles(
        $limit = 10000,
        $marker = null,
        $prefix = null,
        $path = null,
        $delimiter = null,
        $format = null
    ) {
        $params = array(
            'limit' => $limit,
            'marker' => $marker,
            'prefix' => $prefix,
            'path' => $path,
            'delimiter ' => $delimiter,
            'format' => (!in_array($format, $this->formats, true) ? $this->format : $format)
        );

        $res = selupload_sCurl::init($this->url)
            ->setHeaders($this->token)
            ->setParams($params)
            ->request("GET")
            ->getContent();

        if ($params['format'] == '') {
            return explode("\n", trim($res));
        }

        return array(trim($res));
    }

    /**
     * Upload local file
     *
     * @param string $localFileName The name of a local file
     * @param string $remoteFileName The name of storage file
     * @param string $formatArchive Archive format, if you need a decompression
     *
     * @return array
     */
    public function putFile($localFileName, $remoteFileName, $formatArchive = null)
    {
        $info = selupload_sCurl::init(
            $this->url . $remoteFileName . ($formatArchive != null ? '?extract-archive=' . $formatArchive : '')
        )
            ->setHeaders($this->token)
            ->putFile($localFileName)
            ->getInfo();

        if (in_array($info["http_code"], array(201))) {
            return true;
        }

        return $this->error($info["http_code"], __METHOD__);
    }

    /**
     * Set meta info for file
     *
     * @param string $name File name
     * @param array $headers Headers
     *
     * @return integer
     */
    public function setFileHeaders($name, $headers)
    {
        $headers = $this->getX($headers, "X-Container-Meta-");
        if (get_class($this) != 'SelectelContainer') {
            return false;
        }

        return $this->setMetaInfo($name, $headers);
    }

    /**
     * Creating directory
     *
     * @param string $name Directory name
     *
     * @return array
     */
    public function createDirectory($name)
    {
        $headers = array_merge(array("Content-Type: application/directory"), $this->token);
        $info = selupload_sCurl::init($this->url . $name)
            ->setHeaders($headers)
            ->request("PUT")
            ->getInfo();

        return $info;
    }

}

class SelectelStorageException extends Exception
{

}

class selupload_sCurl
{

    static private $instance = null;
    private $ch = null;
    private $url = null;
    private $params = array();
    private $result = array();
    private $openFile = null;

    /**
     * Curl wrapper
     *
     * @param string $url
     */
    private function __construct($url)
    {
        $this->ch = curl_init($url);
        curl_setopt($this->ch, CURLOPT_ENCODING, 'gzip,defalate');
        //curl_setopt ($this->ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($this->ch, CURLOPT_MAXREDIRS, 5);
        curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($this->ch, CURLOPT_HEADER, true);
        curl_setopt($this->ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, 2);
        curl_setopt($this->ch, CURLOPT_BINARYTRANSFER, true);
// TODO: big files
// curl_setopt($this->ch, CURLOPT_RANGE, "0-100");
        $this->setUrl($url);
    }

    /**
     * Set url for request
     *
     * @param string $url URL
     *
     * @return selupload_sCurl
     */
    public function setUrl($url)
    {
        $this->url = $url;
        return self::$instance;
    }

    /**
     *
     * @param string $url
     *
     * @return selupload_sCurl
     */
    static function init($url)
    {
        if (self::$instance == null) {
            self::$instance = new selupload_sCurl ($url);
        }
        return self::$instance->setUrl($url);
    }

    public function putFile($file)
    {
        $this->openFile = fopen($file, "r");
        if ($this->openFile != false) {
            curl_setopt($this->ch, CURLOPT_INFILE, $this->openFile);
            curl_setopt($this->ch, CURLOPT_INFILESIZE, filesize($file));
            # TODO: check correct closing file
            #return $this->request('PUT');
            $this->request('PUT');
            fclose($this->openFile);
            return self::$instance;
        }else{
            return false;
        }
    }

    /**
     * Set method and request
     *
     * @param string $method
     *
     * @return selupload_sCurl
     */
    public function request($method)
    {
        $this->method($method);
        $this->params = array();
        curl_setopt($this->ch, CURLOPT_URL, $this->url);

        $response = explode("\r\n\r\n", curl_exec($this->ch));

        $this->result['info'] = curl_getinfo($this->ch);
        $this->result['header'] = $this->parseHead($response[0]);
        unset ($response[0]);
        $this->result['content'] = join("\r\n\r\n", $response);
//        if ($this->openFile !== null){
//            @fclose($this->openFile);
//            $this->openFile = null;
//        }
        return self::$instance;
    }

    /**
     * Set request method
     *
     * @param string $method
     *
     * @return selupload_sCurl
     */
    private function method($method)
    {
        switch ($method) {
            case "GET" :
            {
                $this->url .= "?" . http_build_query($this->params);
                curl_setopt($this->ch, CURLOPT_HTTPGET, true);
                break;
            }
            case "HEAD" :
            {
                $this->url .= "?" . http_build_query($this->params);
                curl_setopt($this->ch, CURLOPT_NOBODY, true);
                break;
            }
            case "POST" :
            {
                curl_setopt($this->ch, CURLOPT_POST, true);
                curl_setopt($this->ch, CURLOPT_POSTFIELDS, http_build_query($this->params));
                break;
            }
            case "PUT" :
            {
                curl_setopt($this->ch, CURLOPT_PUT, true);
                break;
            }
            default :
                {
                curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $method);
                break;
                }
        }
        return self::$instance;
    }

    /**
     * Header Parser
     *
     * @param string $head
     *
     * @return array
     */
    private function parseHead($head)
    {
        $result = array();
        $code = explode("\r\n", $head);
        $result['HTTP-Code'] = intval(str_replace("HTTP/1.1", "", $code[0]));
        $matches = array(array());
        preg_match_all("/([A-z\-]+)\: (.*)\r\n/", $head, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if ((!empty($match[1])) and (isset($match[2]))) {
            }
            $result[strtolower($match[1])] = $match[2];
        }
        return $result;
    }

    /**
     * Set headers
     *
     * @param array $headers
     *
     * @return selupload_sCurl
     */
    public
    function setHeaders(
        $headers
    ) {
        $headers = array_merge(array("Expect:"), $headers);
        curl_setopt($this->ch, CURLOPT_HTTPHEADER, $headers);
        return self::$instance;
    }

    /**
     * Set request parameters
     *
     * @param array $params
     *
     * @return selupload_sCurl
     */
    public
    function setParams(
        $params
    ) {
        $this->params = $params;
        return self::$instance;
    }

    /**
     * Getting info, headers and content of last response
     *
     * @return array
     */
    public
    function getResult()
    {
        return $this->result;
    }

    /**
     * Getting headers of last response
     *
     * @param string $header Header
     *
     * @return array
     */
    public
    function getHeaders(
        $header = null
    ) {
        if (!is_null($header)) {
            $this->result['header'][$header];
        }
        return $this->result['header'];
    }

    /**
     * Getting content of last response
     *
     * @return array
     */
    public
    function getContent()
    {
        return $this->result['content'];
    }

    /**
     * Getting info of last response
     *
     * @param string $info Info's field
     *
     * @return array
     */
    public
    function getInfo(
        $info = null
    ) {
        if (!is_null($info)) {
            $this->result['info'][$info];
        }
        return $this->result['info'];
    }

}
