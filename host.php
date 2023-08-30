<?php
/* @author: chiasecode
 * @version: 2.2 */

class SynoFileHostingFshareVN {
    private $Url;
    private $Username;
    private $Password;
    private $HostInfo;
    private $COOKIE_JAR = '/tmp/fsharevn.cookie';
    private $LOG_FILE = '/tmp/fsharevn.log';
    private $TOKEN_FILE = '/tmp/fsharevn.token';
    
    public function __construct($Url, $Username, $Password, $HostInfo) {
        if(strpos($Url,'http://') !== FALSE){
            $Url = str_replace("http://", "https://", $Url);
        }else{
            if(strpos($Url,'https://') === FALSE){
                $Url = "https://" . $Url;
            }
        }
		// Truncate from ?
		$pos = strpos($Url,"?");
		$Url = ($pos !== false)?substr($Url,0,$pos):$Url;

        $this->Url = $Url;
        $this->Username = $Username;
        $this->Password = $Password;

        $this->AppId = "dMnqMMZMUnN5YpvKENaEhdQQ5jxDqddt";

        $this->HostInfo = $HostInfo;

        ini_set('max_execution_time', 300);
    }
    
    public function Verify($ClearCookie) {
        if($ClearCookie) {
            unlink($this->COOKIE_JAR);
            unlink($this->TOKEN_FILE);
        }
        
        if(file_exists($this->TOKEN_FILE)) {
            return USER_IS_PREMIUM;
        }
            
        return $this->performLogin();
    }
    
    public function GetDownloadInfo() {
        $DownloadInfo = array();

        $this->logInfo("Start getting download info");

        $this->logInfo("Checking if need to re-login");
        
        $this->Token = $this->getToken();
        if(empty($this->Token)) {
            $this->logInfo("Token is empty => need to login to get token");            
            if($this->Verify(false) === LOGIN_FAIL) {
                $DownloadInfo[DOWNLOAD_ERROR] = "Login fail!";
                return $DownloadInfo;
            }    
        }

        $downloadUrl = $this->getLink();        		
        if(empty($downloadUrl) || $downloadUrl === "error") {
            
            // get link may fail due to use expired token / cookie
            // => login and retry once            
            $this->logInfo("Token/Cookie is expired. Login and try once");
            if($this->Verify(false) === LOGIN_FAIL) {
                $DownloadInfo[DOWNLOAD_ERROR] = "Login fail!";
                return $DownloadInfo;
            }

            $downloadUrl = $this->getLink();
            $DownloadInfo[DOWNLOAD_URL] = $downloadUrl;
        }
        else
        {
            $DownloadInfo[DOWNLOAD_URL] = $downloadUrl;
        }

        $this->logInfo("End getting download info");

        return $DownloadInfo;

    }
    
    private function performLogin() {
        $ret = LOGIN_FAIL;

        $this->logInfo("Start login");

        $service_url = 'https://118.69.164.19//api/user/login';
        $curl = curl_init($service_url);
        $data = array(
            "app_key" => $this->AppId,
            "password" => $this->Password,
            "user_email" => $this->Username
        );

        $data_string = json_encode($data);                
        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_COOKIEJAR, $this->COOKIE_JAR);    
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36",
            "User-Agent: kodivietmediaf-K58W6U",
            "Authorization: Bearer efdf39c90189ddfbff339ae344c28db5f6c11885",
            "cache-control: no-cache"
        ));
                			
        $curl_response = curl_exec($curl);        
        if(!$this->isOK($curl, $curl_response))
        {
            $this->logError("Login error");            
        }
        else
        {
            $this->Token = json_decode($curl_response)->{'token'};
            // save token to disk
            $this->saveToken($this->Token);             
            $this->logInfo("Login ok");
    
            $ret = USER_IS_PREMIUM;
        }

        $this->logInfo("End login");

        curl_close($curl);

        return $ret;
        
    }

    private function getLink($retry = true) {

        $ret = "error";

        $this->logInfo("Start Get Link: " . $this->Url);

        $service_url = 'https://118.69.164.19//api/session/download';

        $curl = curl_init($service_url);
        $data = array(
            "password" => "",
            "token" => $this->Token,
            "url" => $this->Url,
            "zipflag" => false
        );

        $data_string = json_encode($data);

        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_COOKIEFILE, $this->COOKIE_JAR);
		curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
		curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($curl, CURLOPT_HTTPHEADER, array(
            "Content-Type: application/json",
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/79.0.3945.130 Safari/537.36",
            "User-Agent: kodivietmediaf-K58W6U",
            "Authorization: Bearer efdf39c90189ddfbff339ae344c28db5f6c11885",
            "cache-control: no-cache"
        ));
        
        $curl_response = curl_exec($curl);
        
        if(!$this->isOK($curl, $curl_response))
        {
            $this->logError("Get link error ");
            curl_close($curl);
            if ($retry && $this->performLogin()){
                return $this->getLink(false);
            }
        }
        else
        {
            $downloadUrl = json_decode($curl_response)->{'location'};
            $this->logInfo("Get link ok");
            $ret = $downloadUrl;            
            curl_close($curl);
        }

        $this->logInfo("End Get Link");        

        return $ret;
    }

    private function logError($msg) {
        $this->log("[ERROR]", $msg);
    }

    private function logInfo($msg) {
        $this->log("[INFO]", $msg);
    }

    private function log($prefix, $msg) {
        error_log($prefix . " - " . date('Y-m-d H:i:s') . " - " . $msg . "\n", 3, $this->LOG_FILE);
    }

    private function saveToken($token) {
        $myfile = fopen($this->TOKEN_FILE, "w");
        fwrite($myfile, $token);
        fclose($myfile);
    }


    private function getToken() {
        if(file_exists($this->TOKEN_FILE)) {
            $myfile = fopen($this->TOKEN_FILE, "r");
            $token = fgets($myfile);
            fclose($myfile);
            return $token;
        }
        else
        {
            return "";
        }
    }

    private function isOK($curl, $curl_response) {            
        if($curl_response === false) {
            return false;
        }

        $info = curl_getinfo($curl);
        $this->logInfo("HTTP CODE: " . $info['http_code']);
        if($info['http_code'] !== 200) {
            return false;
        }

        return true;
    }
}

?>
