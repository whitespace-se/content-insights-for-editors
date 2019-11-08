<?php

namespace CONTENT_INSIGHTS_FOR_EDITORS\Util;

class Crawler {
    private $_pageSource = null;
    private $_pageBody =  null;
    private $_pageHead = null;

    function __construct($pageUrl){
        $this->_pageSource = $this->crawlUrl($pageUrl);

        if($this->_pageSource === false){
            throw new Exception("Page was unreachable: $pageUrl");
        }
    }

    public function pageHeader(){
        throw new Exception("Not implemented");
    }

    public function pageBody(){
        if($this->_pageBody == null){
            preg_match('/<body[^>]*>(.*?)<\/body>/is', $this->_pageSource, $matches);
            
            if(count($matches) < 2 || strlen($matches[1]) < 1){
                throw new Exception("Page has no content in body");
            }

            $this->_pageBody = $matches[1];
        }

        return $this->_pageBody;
    }

    public function getImgTags(){
        preg_match_all('/(<img.*?>)/', $this->pageBody(), $matches);

        return $matches[0];
    }

    private function crawlUrl($url){
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        $result = curl_exec($curl);
        curl_close($curl);

        return $result;
    }
}