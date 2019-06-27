<?php
class Vasby extends Scraper
{
    private $_siteUrl = 'http://example.com';
    private $_params = array(
        'date' => 'nu',
        'imgAlt' => 'Bostad-direkt',
        'imgTitle' => 'Bostad-direkt',

        'username' => '',
        'password' => '',
        
        'timeMin' => '07',
        'timeMax' => '17',
        'days' => 'Tue, Thu',
    );
    
    function __construct()
    {
        $this->_params['imgAlt'] = $this->iconv($this->_params['imgAlt']);
        $this->_params['imgTitle'] = $this->iconv($this->_params['imgTitle']);
        parent::__construct($this->_params);
    }
    public function runScript()
    {
        $url = $this->_siteUrl . 'object_list.aspx?cmguid=4e6e781e-5257-403e-b09d-7efc8edb0ac8&objectgroup=1';
        $this->loadHTML($url);

        // Check for Login 
        $topMenu = $this->getElementLength('//ul[@class="topmenu"]/li');
        
        if ($topMenu != 9) {
            $this->siteLogin();
            $this->loadHTML($url);
        }
        
        $hasNextPage = true;
        $currentPage = 1;
        while ($hasNextPage) {
            $pageXapth = $this->_xpath;

            $hasNextPage = $this->_xpath->evaluate(
                'boolean(//input[@id="ctl00_ctl01_DefaultSiteContentPlaceHolder1_Col1_ucNavBar_btnNavNext"])'
            );
            
            
            if ($hasNextPage) {
                $hasNextPage = !($this->getElementAtrribute(
                    '//input[@id="ctl00_ctl01_DefaultSiteContentPlaceHolder1_Col1_ucNavBar_btnNavNext"]', 
                    'disabled'
                ));
                
                $pageViewState = $this->getElementChildAtrribute('//input[@id="__VIEWSTATE"]', 0, 'value');
                $pageEventValidation = $this->getElementChildAtrribute('//input[@id="__EVENTVALIDATION"]', 0, 'value');
            }

            $items = $this->getElementsList(
                '//table[@id="ctl00_ctl01_DefaultSiteContentPlaceHolder1_Col1_grdList"]/tr[@class="listitem-even"]|'
                . '//table[@id="ctl00_ctl01_DefaultSiteContentPlaceHolder1_Col1_grdList"]/tr[@class="listitem-odd"]'
            );
            
            foreach ($items as $item) {
                $images = $this->getChildElements($item, 'img');
                $date = strtolower(trim($this->strReplace($this->getChildElementText($item, 7))));
                $rent = (int) strtolower(trim($this->strReplace($this->getChildElementText($item, 6))));
                
                foreach ($images as $image) {
                    if (
                        $rent > $this->_options['rentMin']
                        && $rent < $this->_options['rentMax']
                        && ($this->matchString($this->getElementAtrribute($image, 'title'), 'imgTitle') 
                        || $this->matchString($this->getElementAtrribute($image, 'alt'), 'imgAlt') 
                        || $this->matchString($date, 'date'))
                    ) {
                        $itemUrl = $item->childNodes->item(2)->childNodes->item(0)->attributes->getNamedItem('href')
                            ->nodeValue;
                        $this->applyForAppartment($itemUrl);
                        $this->changeXpath($pageXapth);
                        break;
                    }
                }
            }
            if ($hasNextPage) {
                // Next page
                $currentPage++;
                $postData = '__EVENTTARGET=&__EVENTARGUMENT=&';
                $postData .= '__VIEWSTATE=' . urlencode($pageViewState) . '&';
                $postData .= '__EVENTVALIDATION=' . urlencode($pageEventValidation) . '&';
                $postData .= 'ctl00$ctl01$DefaultSiteContentPlaceHolder1$Col1$ucNavBar$btnNavNext=&';
                $postData .= 'ctl00$ctl01$SearchSimple$txtSearch=';

                $this->_options['postdata'] = $postData;

                // Load Next page
                $this->loadHTML($url);
            }
        }
    }   
    
    private function applyForAppartment($itemUrl) 
    {
        $this->loadHTML($this->_siteUrl . $itemUrl);
        // Check if not appied before
        $applyButtonExists = $this->_xpath->evaluate(
            'boolean(//input[@id="ctl00_ctl01_DefaultSiteContentPlaceHolder1_Col1_btnRegister"])'
        );
        $urlHash = $this->getHash($itemUrl);
        if ($applyButtonExists == true && !$this->getCookie("vasby_" . $urlHash)) {
            
            $viewState = $this->getElementChildAtrribute('//input[@id="__VIEWSTATE"]', 0, 'value');
            $eventValidation = $this->getElementChildAtrribute('//input[@id="__EVENTVALIDATION"]', 0, 'value');
            $applyUrl = $this->_siteUrl . $this->getElementChildAtrribute('//form[@id="aspnetForm"]', 0, 'action'); 

            $postData = '__EVENTTARGET=&__EVENTARGUMENT=&';
            $postData .= '__VIEWSTATE=' . urlencode($viewState) . '&';
            $postData .= '__EVENTVALIDATION=' . urlencode($eventValidation) . '&';
            $postData .= 'ctl00$ctl01$DefaultSiteContentPlaceHolder1$Col1$btnRegister='
                    . urlencode('Anm?l intresse') . '&';
            $postData .= 'ctl00$ctl01$SearchSimple$txtSearch=';

            $this->_options['postdata'] = $postData;
            // Apply
            $this->loadHTML($applyUrl);
            // Send Email
            $this->sendEmail($applyUrl);
        }
    }
    
    private function siteLogin()
    {
        echo 'siteLogin()';
        $loginUrl = 'https://www.vasbyhem.se/User/MyPagesLogin.aspx?cmguid=c80865e7-dea9-4e5d-ad2c-ca43073f16c4';
        $this->loadHTML($loginUrl);
        
        $viewState = $this->getElementChildAtrribute('//input[@id="__VIEWSTATE"]', 0, 'value');
        $eventValidation = $this->getElementChildAtrribute('//input[@id="__EVENTVALIDATION"]', 0, 'value');
        
        $postData = '__LASTFOCUS=&__EVENTTARGET=&__EVENTARGUMENT=&';
        $postData .= '__VIEWSTATE=' . urlencode($viewState) . '&';
        $postData .= '__EVENTVALIDATION=' . urlencode($eventValidation) . '&';
        $postData .= 'ctl00$ctl01$DefaultSiteContentPlaceHolder1$Col2$LoginControl1$txtUserID=' . $this->_options['username']
                . '&';
        $postData .= 'ctl00$ctl01$DefaultSiteContentPlaceHolder1$Col2$LoginControl1$txtPassword=' . $this->_options['password']
                . '&';
        $postData .= 'ctl00$ctl01$DefaultSiteContentPlaceHolder1$Col2$LoginControl1$btnLogin=OK&';
        $postData .= 'ctl00$ctl01$SearchSimple$txtSearch=';

        $this->_options['postdata'] = $postData;
        // Sign In
        $this->loadHTML($loginUrl);
    }
}
