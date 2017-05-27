<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Test extends CI_Controller {

	/**
	* Index Page for this controller.
	*
	* Maps to the following URL
	* 		http://example.com/index.php/welcome
	*	- or -
	* 		http://example.com/index.php/welcome/index
	*	- or -
	* Since this controller is set as the default controller in
	* config/routes.php, it's displayed at http://example.com/
	*
	* So any other public methods not prefixed with an underscore will
	* map to /index.php/welcome/<method_name>
	* @see https://codeigniter.com/user_guide/general/urls.html
	*/
	public function index()
	{
		$this->load->view('welcome_message');
	}

	public function __construct(){
		parent::__construct();

		$this->fbAppID = '296983647387556';
		$this->fbAppSecret = '2a70378e8990e891b74c1746a090b6c4';
	}

	public function phpinfo(){
		echo phpinfo();
	}

	// public function testMongo(){
	// 	// $this->load->library('mongo_db', array('activate'=>'test'),'mongo_db2');
	// 	// $this->load->library('mongo_db');
	// 	$test = array("name"=> "harkirat", "cp"=>"18500");

	// 	// var_dump($this->mongo_db->insert("pokemons", $test));
	// 	echo "<pre>";
	// 	// var_dump($this->mongo_db->select("name")->where_in("badges", array("blue"))->get("pokemons"));
	// 	var_dump($this->mongo_db->select("name")->like("dish_name", "Mojo Crepe", "i")->get("dishes"));
	// 	// var_dump($this->mongo_db->where_in("finished", array(11))->get("pokemons"));
	// 	// var_dump($this->mongo_db->where("_id", "57a45c8bc58ebb47048b4567")->get("pokemons"));
	// }

	public function getFbData(){
		require_once APPPATH."libraries/facebook-sdk-v5/autoload.php";

		$fb = new Facebook\Facebook([
			'app_id' => $this->fbAppID,
			'app_secret' => $this->fbAppSecret,
			'default_graph_version' => 'v2.8',
			]);

		$helper = $fb->getRedirectLoginHelper();
		$permissions = ['email', 'user_likes', 'manage_pages', 'public_profile', 'user_friends', 'pages_show_list', 'read_insights'];
		$loginUrl = $helper->getLoginUrl('http://fitstreak.in/prediction_baba/callbackFB', $permissions);

	// echo '<a href="' . $loginUrl . '">Log in with Facebook!</a>';

		$data = array(
			"loginUrl" => $loginUrl
			);

		$this->load->view("fb_auth", $data);
	}

	public function callbackFB(){
		require_once APPPATH."libraries/facebook-sdk-v5/autoload.php";

		$fb = new Facebook\Facebook([
			'app_id' => $this->fbAppID,
			'app_secret' => $this->fbAppSecret,
			'default_graph_version' => 'v2.8',
			]);


		$helper = $fb->getRedirectLoginHelper();
		try {
			$accessToken = $helper->getAccessToken();
		} catch(Facebook\Exceptions\FacebookResponseException $e) {
			// When Graph returns an error
			echo 'Graph returned an error: ' . $e->getMessage();
			exit;
		} catch(Facebook\Exceptions\FacebookSDKException $e) {
			// When validation fails or other local issues
			echo 'Facebook SDK returned an error: ' . $e->getMessage();
			exit;
		}

		if (isset($accessToken)) {
			// Logged in!
			$_SESSION['facebook_access_token'] = (string) $accessToken;

		// Now you can redirect to another page and use the
		// access token from $_SESSION['facebook_access_token']
		}

		try {
		// Returns a `Facebook\FacebookResponse` object
			$response = $fb->get('/me?fields=id,name', $accessToken);
		} catch(Facebook\Exceptions\FacebookResponseException $e) {
			echo 'Graph returned an error: ' . $e->getMessage();
			exit;
		} catch(Facebook\Exceptions\FacebookSDKException $e) {
			echo 'Facebook SDK returned an error: ' . $e->getMessage();
			exit;
		}

		$user = $response->getGraphUser();

		try {
		// Returns a `Facebook\FacebookResponse` object
			$pageResponse = $fb->get('/me/accounts', $accessToken);
		} catch(Facebook\Exceptions\FacebookResponseException $e) {
			echo 'Graph returned an error: ' . $e->getMessage();
			exit;
		} catch(Facebook\Exceptions\FacebookSDKException $e) {
			echo 'Facebook SDK returned an error: ' . $e->getMessage();
			exit;
		}

		$pages_data = $posts_likes_data = $posts_data = $pages_data_dump = array();

		foreach ($pageResponse->getDecodedBody()['data'] as $value) {
			$pages_data[] = array(
				"page_id"=> $value['id'],
				"name"=> $value['name'],
				"page_access_token"=> $value['access_token']
				);
		}

		// $this->mongo_db->delete_all("users");
		// $this->mongo_db->delete_all("posts_data");
		// $this->mongo_db->delete_all("page_posts");
		$user_exists = $this->mongo_db->where("user_id", (string)$user['id'])->get("users");
		if(!isset($user_exists[0]))
			$this->mongo_db->insert("users", array("user_id"=> $user['id'], "pages_data"=> $pages_data));
		else{
			// Updating pages list
			$this->mongo_db->where("user_id", (string)$user['id'])->set("pages_data", $pages_data)->update("users");

			$this->mongo_db->where("user_id", (string)$user['id'])->delete_all("page_posts");
			$this->mongo_db->where("user_id", (string)$user['id'])->delete_all("posts_data");
		}
		


		// $i = 0;
		foreach ($pages_data as $page_data) {
			// if($i==0){
			// 	$i++;
			// 	continue;
			// }
			$page_id = $page_data['page_id'];
			// if($page_id==138990652972359) //startoholics
			// if($page_id != 491582707520973) //dreamingknights
				// continue;
			$page_access_token = $page_data['page_access_token'];
			$p_data = array();

			try {
				// Returns a `Facebook\FacebookResponse` object
				$post_data = $fb->get('/'.$page_id.'/posts?limit=100', $page_access_token)->getDecodedBody();
				if(array_key_exists('paging', $post_data) && array_key_exists('next', $post_data['paging']))
					$next = $post_data['paging']['next'];
				else
					$next = False;

				$posts = $post_data['data'];

				foreach ($posts as $key => $value) {
					$posts[$key] = (object)$value;
				}

				// Fetches last 400 posts, if available
				$i=0;
				while($i<5 && $next){
					$result = $this->curlRequest($next);

					if(isset($result->paging->next))
						$next = $result->paging->next;
					else
						break;

					if(count($result->data))
						$posts = array_merge($posts, $result->data);
					else
						break;

					$i++;
				}

				$pages_data_dump[] = array("data"=> $posts, "name"=> $page_data['name'], "id"=> $page_id, 'page_access_token'=> $page_access_token);
				$this->mongo_db->insert("page_posts", array("page_id"=> $page_id, "name"=> $page_data['name'], "posts_array"=> $posts, "user_id"=> $user['id'], "page_access_token"=> $page_access_token));

			} catch(Facebook\Exceptions\FacebookResponseException $e) {
				echo 'Graph returned an error: ' . $e->getMessage();
				// exit;
			} catch(Facebook\Exceptions\FacebookSDKException $e) {
				echo 'Facebook SDK returned an error: ' . $e->getMessage();
				exit;
			}
		}


		// $this->mongo_db->where("user_id", $user['id'])->delete_all("posts_data");
		foreach ($pages_data_dump as $p) {
			$page_access_token = $p['page_access_token'];
			$page_id = $p['id'];

			foreach ($p['data'] as $data) {
				try {
					// Returns a `Facebook\FacebookResponse` object
					$message = isset($data->message) ? $data->message : (isset($data->story) ? $data->story: '');

					$created_time = explode("T", $data->created_time)[1];
					$created_time = explode("+", $created_time)[0];

					$post_data = $fb->get('/'.$data->id.'/insights?metric=post_engaged_users,post_impressions_unique,post_impressions,post_impressions_organic,post_impressions_fan', $page_access_token)->getDecodedBody();
					// echo "<pre>";
					// var_dump($post_data);
					// die();

					if(isset($post_data['data'][0]['values'][0]['value'])){
						$this->mongo_db->insert("posts_data", array("post_id"=> $data->id, "message"=> $message, "engagement_count"=> $post_data['data'][0]['values'][0]['value'], "unique_impressions_count"=> $post_data['data'][1]['values'][0]['value'], "all_impressions_count" => $post_data['data'][2]['values'][0]['value'], "organic_impressions_count"=> $post_data['data'][3]['values'][0]['value'], "fan_only_impressions"=> $post_data['data'][4]['values'][0]['value'], "user_id"=> $user['id'], 'page_id' => $page_id, "created_datetime"=>$data->created_time, "created_time"=> $created_time));
					}
				} catch(Facebook\Exceptions\FacebookResponseException $e) {
					echo 'Graph returned an error: ' . $e->getMessage();
					// exit;
				} catch(Facebook\Exceptions\FacebookSDKException $e) {
					echo 'Facebook SDK returned an error: ' . $e->getMessage();
					exit;
				}
			}
		}


		redirect("http://fitstreak.in/prediction_baba/test/gettime/".$user['id']);
	}

	public function getTime($user_id){
		$all_pages = $this->mongo_db->where("user_id", (string)$user_id)->get("users");
		if(!isset($all_pages[0])){
			echo "Invalid user. May be need to go here first? http://fitstreak.in/prediction_baba/login";
			exit;
		}

		foreach ($all_pages[0]['pages_data'] as $page) {
			$page_id = $page['page_id'];
			echo "<h3>Analysis for ".$page['name']."</h3><br>";

			$all_posts = $this->mongo_db->where("page_id", (string)$page_id)->order_by(array("engagement_count"=> "ASC"))->get("posts_data");

			// creating timing and frequency pairs
			// 0 => 0-0:59:59am
			// 1 => 1-1:59:59am
			// 2 => 2-2:59:59am
			// 3 => 3-3:59:59am
			// ...
			// 23 => 23pm-23:59:59pm
			$timing_frequency = array();
			echo "Analysis considering <b>TOTAL</b> impressions<br><br>";

			foreach ($all_posts as $value) {
				$time_in_ist = strtotime($value['created_time']." +5 hours +30 minutes");
				$time = date('G:i:s', $time_in_ist);
				$hour = (int)explode(":", $time)[0];

				// engagement_count
				// unique_impressions_count
				// all_impressions_count
				// organic_impressions_count
				// fan_only_impressions

				$score = $value['all_impressions_count']+(3*$value['engagement_count']);
				// $score = $value['organic_impressions_count']+(3*$value['engagement_count']);

				if(array_key_exists($hour, $timing_frequency))
					$timing_frequency[$hour] += $score;
				else
					$timing_frequency[$hour] = $score;
			}
			arsort($timing_frequency);

			if(count($timing_frequency))
				echo "Best times (Max 5):<br><br>";
			else
				echo "No data";

			$i = 0;
			foreach ($timing_frequency as $key => $value) {
				if($i>4){
					break;
				}
				$time = "Between ".$key. " to ".$key.":59 hrs<br>";

				echo $time;
				$i++;
			}



			$timing_frequency = array();
			echo "<br><br>Analysis considering <b>ORGANIC</b> impressions<br><br>";

			foreach ($all_posts as $value) {
				$time_in_ist = strtotime($value['created_time']." +5 hours +30 minutes");
				$time = date('G:i:s', $time_in_ist);
				$hour = (int)explode(":", $time)[0];

				// engagement_count
				// unique_impressions_count
				// all_impressions_count
				// organic_impressions_count
				// fan_only_impressions

				// $score = $value['unique_impressions_count']+(3*$value['engagement_count']);
				$score = $value['organic_impressions_count']+(3*$value['engagement_count']);

				if(array_key_exists($hour, $timing_frequency))
					$timing_frequency[$hour] += $score;
				else
					$timing_frequency[$hour] = $score;
			}
			arsort($timing_frequency);

			if(count($timing_frequency))
				echo "Best times (Max 5):<br><br>";
			else
				echo "No data";

			$i = 0;
			foreach ($timing_frequency as $key => $value) {
				if($i>4){
					break;
				}
				$time = "Between ".$key. " to ".$key.":59 hrs<br>";

				echo $time;
				$i++;
			}



			$timing_frequency = array();
			echo "<br><br>Analysis considering <b>UNIQUE</b> impressions<br><br>";

			foreach ($all_posts as $value) {
				$time_in_ist = strtotime($value['created_time']." +5 hours +30 minutes");
				$time = date('G:i:s', $time_in_ist);
				$hour = (int)explode(":", $time)[0];

				// engagement_count
				// unique_impressions_count
				// all_impressions_count
				// organic_impressions_count
				// fan_only_impressions

				$score = $value['unique_impressions_count']+(3*$value['engagement_count']);
				// $score = $value['organic_impressions_count']+(3*$value['engagement_count']);

				if(array_key_exists($hour, $timing_frequency))
					$timing_frequency[$hour] += $score;
				else
					$timing_frequency[$hour] = $score;
			}
			arsort($timing_frequency);

			if(count($timing_frequency))
				echo "Best times (Max 5):<br><br>";
			else
				echo "No data";

			$i = 0;
			foreach ($timing_frequency as $key => $value) {
				if($i>4){
					break;
				}
				$time = "Between ".$key. " to ".$key.":59 hrs<br>";

				echo $time;
				$i++;
			}



			$timing_frequency = array();
			echo "<br><br>Analysis considering <b>ONLY FAN</b> impressions<br><br>";

			foreach ($all_posts as $value) {
				$time_in_ist = strtotime($value['created_time']." +5 hours +30 minutes");
				$time = date('G:i:s', $time_in_ist);
				$hour = (int)explode(":", $time)[0];

				// engagement_count
				// unique_impressions_count
				// all_impressions_count
				// organic_impressions_count
				// fan_only_impressions

				$score = $value['fan_only_impressions']+(3*$value['engagement_count']);

				if(array_key_exists($hour, $timing_frequency))
					$timing_frequency[$hour] += $score;
				else
					$timing_frequency[$hour] = $score;
			}
			arsort($timing_frequency);

			if(count($timing_frequency))
				echo "Best times (Max 5):<br><br>";
			else
				echo "No data";

			$i = 0;
			foreach ($timing_frequency as $key => $value) {
				if($i>4){
					break;
				}
				$time = "Between ".$key. " to ".$key.":59 hrs<br>";

				echo $time;
				$i++;
			}


			echo "<br><br><br>";
		}
	}

	// public function setEngagement(){
	// 	$all_posts = $this->mongo_db->where("user_id", (string)10212824316111152)->order_by(array("engagement_count"=> "ASC"))->get("posts_data");

	// 	foreach ($all_posts as $value) {
	// 		$this->mongo_db->where("_id", $value['_id'])->set("engagement_count", rand(0,20))->update("posts_data");
	// 	}
	// }

	private function curlRequest($url=False){
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_POST, 0);
	// curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($response));
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, True);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
		$result = json_decode(curl_exec($ch));
		curl_close($ch);

		if($result) return $result;
		else return False;
	}

}