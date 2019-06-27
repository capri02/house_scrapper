<?php

//require_once ( 'mailer/class.email.php' );
class Scraper
{
    /**
     * DOMDocument object
     */
    protected $_dom = null;
    /**
     * DOMXPath object
     */
    protected $_xpath = null;

    /**
     * html string of the url
     */
    protected $_html = null;

    /**
     * Array of articles scraped from site
     */
    protected $_result = array();
    
    protected $_options = array();
    protected $_defaultCookie = "cookie.txt";
    protected $_defaultDays = array();
    protected $_runScript = false;
    
    private $_debug = false;
    private $_defaulOptions = array(
        'rentMax' => 5500,
        'rentMin' => 4000,
        'mailto' => '',
    );
    private $_sleepTime = array(
        'day' => 60, // 60 min
        'offHours' => 60, // 60 min
        'onHours' => 5, // 5 min
        'runDelay' => 10, // 10 sec
    );
    
    function __construct($options)
    {
        $this->_options = array_merge($options, $this->_defaulOptions);
        
        $this->_options['cookieFile'] = get_class($this);
        
        if (isset($this->_options['days']))
            $this->_options['days'] = explode(",", strtolower(str_replace (" ", "", $this->_options['days'])));
        else
            $this->_options['days'] = $this->_defaultDays;
        
        if (isset($this->_options['debug'])) {
            $this->_debug = $this->_options['debug'];
        }
    }
    
    /**
     * Load html from link in DOMDocument and DOMXPath
     * 
     * @param string $url
     * 
     */
    protected function loadHTML($url)
    {
        if ($this->_debug)
            echo "{$url} \n" . "\n";
            
        $this->_html = $this->getHTML($url);
        
        if (!$this->_html)
            return;
        $this->_dom = new DOMDocument();
        @$this->_dom->loadHtml($this->_html);
        $this->_xpath = new DOMXPath($this->_dom);
    }
    /**
     * Load htmt into DOMDocument
     * 
     * @param string $html
     * 
     */
    protected function loadDOM($html)
    {
        $this->_html = $html;
        $this->_dom = new DOMDocument();
        @$this->_dom->loadHtml($this->_html);
        $this->_xpath = new DOMXPath($this->_dom);
    }
    protected function changeXpath($xpath)
    {
        $this->_xpath = $xpath;
    }
    /**
     * Load JSON array from the link
     * 
     * @param string $url
     * @return Array
     */
    protected function loadJSON($url)
    {
        return $this->getJSONData($url);
    }
    /**
     * Gets html of the given link
     *
     * @param string url
     * 
     */
    private function getHTML($url)
    {
        if (!$url)
            return '';
        
        $cookie = (string) $this->getCookieFile();

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt ($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($curl, CURLOPT_USERAGENT, $this->getUserAgent() );
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->getHeader() );
        curl_setopt($curl, CURLOPT_TIMEOUT, 60);
        curl_setopt ($curl, CURLOPT_FOLLOWLOCATION, 1); 
        curl_setopt ($curl, CURLOPT_RETURNTRANSFER, 1); 
        curl_setopt ($curl, CURLOPT_COOKIEJAR, $cookie); 
        curl_setopt ($curl, CURLOPT_COOKIEFILE, $cookie); 
        
        if (isset($this->_options['postdata']) && $this->_options['postdata']) {
            curl_setopt ($curl, CURLOPT_REFERER, $url); 
            curl_setopt ($curl, CURLOPT_POSTFIELDS, $this->_options['postdata']); 
            curl_setopt ($curl, CURLOPT_POST, 1);
        }

        $html = curl_exec($curl);

        if (!$html) {
            printf("CURL Error:%s \n (#%s) \n %s", curl_error($curl), curl_errno($curl), $url, $url);
            return '';
        }
        unset($this->_options['postdata']);
        curl_close($curl);
        return $html;
    }
    /**
     * Load JSON array from the link
     * 
     * @param string $url
     * @return Array
     */
    private function getJSONData($url)
    {
        $jsonResult = $this->getHTML($url);
        if (!$jsonResult)
            return array();
        return json_decode($jsonResult, true);
    }
    private function getCookieFile() 
    {
        if (isset($this->_options['cookieFile']) && $this->_options['cookieFile'])
            $fileName = strtolower($this->_options['cookieFile'] . '.txt'); 
        else
            $fileName = strtolower($this->_defaultCookie);
        $filePath = str_replace("\\", "/", realpath(dirname(__FILE__)). '\\' . trim($fileName));
        
        if (!file_exists($filePath)) {
            $handle = fopen($filePath, 'w') or die('Cannot open file:  ' . $filePath);
            fclose($handle);
        }
        return $filePath;
    }
    /**
     * Replace invalid string chatacters with valid ones
     * 
     * @param array/string $seach
     * @param array/string $replace
     * @param string $str
     * 
     * @return string $str
     */
     protected function strReplace($str, $seach = '', $replace = '')
    {
        if (is_array($seach) && is_array($replace))
            $str = str_replace($seach, $replace, $str);
        elseif (!(is_array($seach) && is_array($replace)))
            $str = str_replace($seach, $replace, $str);

        // Remove unicode
        $str = preg_replace('/[^(\x20-\x7F)]*/', '', $str);

        // Remove HTML special chars
        $str = preg_replace('/&#?[a-z0-9]{2,8};/i', '', $str);

        return $str;
    }
    
    protected function getElementLength($elementPath)
    {
        return $this->_xpath->query($elementPath)->length;
    }        
    
    protected function getElement($elementPath)
    {
        return $this->_xpath->query($elementPath)->item(0);
    }        
    
    protected function getElementsList($elementPath)
    {
        return $this->_xpath->query($elementPath);
    }        
   
    protected function getChildElementText($element, $index)
    {
        $domElement = $element;
        if (is_string($element))
            $domElement = $this->getElement($element);
        
        if ($domElement->hasChildNodes()) 
            return $domElement->childNodes->item($index)->nodeValue;
        else
            return '';
    }        
   
    protected function getElementAtrribute($element, $attribute)
    {
        $domElement = $element;
        if (is_string($element))
            $domElement = $this->getElement($element);
        
        if ($domElement->hasAttribute($attribute))
            return $domElement->attributes->getNamedItem($attribute)->nodeValue;
        else
            return NULL;
    }
    
    protected function getElementChildAtrribute($element, $child, $attribute)
    {
        $domElements = $element;
        if (is_string($element))
            $domElements = $this->getElementsList($element);
        
        return $this->getElementAtrribute($domElements->item($child), $attribute);
    }
    
    protected function getChildElements($element, $children)
    {
        if ($element->hasChildNodes())
            return $this->_xpath->query('.//' . $children, $element);
        else
            return array();
    }
    protected function iconv($str)
    {
        return strtolower(iconv("UTF-8", "ISO-8859-1//TRANSLIT", $str));
    }
    protected function matchString($str, $attribute)
    {
        if (trim($str) && isset($this->_options[$attribute]) && $this->iconv($str) == $this->_options[$attribute])
            return true;
        else
            return false;
    }
    protected function searchString($str)
    {
        return strtolower(iconv("UTF-8", "ISO-8859-1//TRANSLIT", $str));
    }
    
    public function checkDateAndTime()
    {
        // Check day
        $return = false;
        list($today, $hour, $minute) = explode(",", strtolower(date('D,H,i')));
        $minuteToNextHour = -(($minute - 60)) - 5;
        $sleep = ($minuteToNextHour > 0) ? ($minuteToNextHour * 60) : $this->_sleepTime['runDelay'];
        
        echo date('Y-m-d H:i:s') . "\n";
        if (
            in_array($today, $this->_options['days']) 
            && $hour >= $this->_options['timeMin'] 
            && $hour < $this->_options['timeMax']
        ) {
            $sleep = $this->_sleepTime['runDelay'];
            $return = true;
        }
        echo "Sleep for " . ($sleep/60) . " min / ({$sleep}) sec \n";
        sleep ($sleep);
        
        return $return;
    }        
    
    public function setCookie($name, $value) 
    {
        setcookie($name, $value);
    }
    
    public function getCookie($name) 
    {
        if (isset($_COOKIE[$name]) && $_COOKIE[$name])
            return $_COOKIE[$name];
        else
            return NULL;
    }
    public function getHash($value) 
    {
        return sha1($value);
    }
    
    protected function sendEmail($url) 
    {
        if (isset($this->_options['mailto']))
            Email::send($url, $this->_options['mailto']);
    }

    /**
     * Gets random Referer
     *
     * @return string
     * 
     */
    private function getReferer()
    {
        $referer = array(
            'http://www.google.com',
            'http://www.yahoo.com',
        );

        return $referer[rand(0, (count($referer) - 1))];
    }
    /**
     * Gets Header for request
     *
     * @return array
     * 
     */
    private function getHeader()
    {
        $header[] = 'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
        $header[] = 'Accept-Encoding: gzip, deflate';
        $header[] = 'Accept-Language: en-US,en;q=0.5';
        $header[] = 'Connection: keep-alive';
        $header[] = 'User-Agent: ' . $this->getUserAgent();
        //$header[] = 'Pragma: ';

        return $header;
    }
    /**
     * Gets random Browser User Agent
     *
     * @return string
     * 
     */
    private function getUserAgent()
    {
        // Set an array with different browser user agents
        $agents = array(
            // Chrome
            'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/535.21 (KHTML, like Gecko) Chrome/19.0.1042.0 Safari/535.21',
            'Mozilla/5.0 (X11; Linux i686) AppleWebKit/535.21 (KHTML, like Gecko) Chrome/19.0.1041.0 Safari/535.21',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_7_3) AppleWebKit/535.20 '
            . '(KHTML, like Gecko) Chrome/19.0.1036.7 Safari/535.20',
            'Mozilla/5.0 (Windows NT 6.1) AppleWebKit/535.2 (KHTML, like Gecko) '
            . 'Chrome/18.6.872.0 Safari/535.2 UNTRUSTED/1.0 3gpp-gba UNTRUSTED/1.0',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_6_8) AppleWebKit/535.19 '
            . '(KHTML, like Gecko) Chrome/18.0.1025.11 Safari/535.19',
            // Firefox
            'Mozilla/6.0 (Macintosh; I; Intel Mac OS X 11_7_9; de-LI; rv:1.9b4) Gecko/2012010317 Firefox/10.0a4',
            'Mozilla/5.0 (Macintosh; I; Intel Mac OS X 11_7_9; de-LI; rv:1.9b4) Gecko/2012010317 Firefox/10.0a4',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.6; rv:9.0a2) Gecko/20111101 Firefox/9.0a2',
            'Mozilla/5.0 (Windows NT 6.2; rv:9.0.1) Gecko/20100101 Firefox/9.0.1',
            // IE 
            'Mozilla/5.0 (Windows; U; MSIE 9.0; WIndows NT 9.0; en-US))',
            'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 7.1; Trident/5.0)',
            'Mozilla/5.0 (compatible; MSIE 9.0; Windows NT 6.1; WOW64; Trident/5.0; SLCC2; '
            . 'Media Center PC 6.0; InfoPath.3; MS-RTC LM 8; Zune 4.7)',
            'Mozilla/5.0 (compatible; MSIE 8.0; Windows NT 6.0; Trident/4.0; WOW64; Trident/4.0; SLCC2; .NET '
            . 'CLR 2.0.50727; .NET CLR 3.5.30729; .NET CLR 3.0.30729; .NET CLR 1.0.3705; .NET CLR 1.1.4322)',
            'Mozilla/5.0 (compatible; MSIE 7.0; Windows NT 6.0; WOW64; SLCC1; .NET CLR 2.0.50727; '
            . 'Media Center PC 5.0; c .NET CLR 3.0.04506; .NET CLR 3.5.30707; InfoPath.1; el-GR)',
        );
        return $agents[rand(0, (count($agents) - 1))];
    }
}