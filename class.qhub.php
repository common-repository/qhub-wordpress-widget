<?
class qhub {
	protected $apiurl;
	protected $hub;
	protected $user_id;
	protected $password;
	protected $apikey;
	protected $error;


	public function __construct ($hub,$user_id,$password,$apikey)	{
		$this->apiurl = 'http://api.qhub.com';

		$this->hub = $hub; 
		$this->user_id = $user_id;
		$this->password = $password; 
		$this->apikey = $apikey; 

		$this->error = ''; 
	}

	private function authenticate()	{
		$ret = "";
		$user_id = $this->user_id;
		$password = $this->password;
		$username=($user_id && $password) ? md5($user_id.":".$password) : 'X';
		$ret = "$username:".$this->apikey;
		return $ret;
	}

	public function request ($url, $postfields=NULL) {
		$url = $this->apiurl.'/'.$this->hub.$url;
		$ch = curl_init();
		curl_setopt ($ch, CURLOPT_URL, $url);
		curl_setopt ($ch, CURLOPT_REFERER, $url);
		curl_setopt ($ch, CURLOPT_USERAGENT, "Qhub PHP API v1.0");

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt ($ch, CURLOPT_CONNECTTIMEOUT, 2);
		curl_setopt ($ch, CURLOPT_TIMEOUT, 3);

		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC); 
		curl_setopt($ch, CURLOPT_USERPWD, $this->authenticate()); 

		if ($postfields) {
			curl_setopt ($ch, CURLOPT_POST, 1);
			curl_setopt ($ch, CURLOPT_POSTFIELDS, $postfields);
		}
		
		$response = curl_exec ($ch);
		
		if (curl_errno($ch)) {
			$this->error = curl_error($ch);
		} 
		else {
			curl_close($ch);
		}

		return $response;		
	}

}
?>
