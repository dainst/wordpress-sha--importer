<?php
/**
 * @package 	enhanced-storytelling
 * @subpackage	Search in Datasources | Abstract Datasource Class
 * @link 		https://github.com/dainst/wordpress-storytelling
 * @author 		Philipp Franck
 *
 *
 * Every datasource wich is connected to the Eagle Story Telling Application (such as europeana, iDai
 * Gazetteer etc.) is an implementation of this abstract class.
 *
 *
 */



namespace shap_datasource {
	abstract class abstract_datasource {

		public $debug = true;
		public $overwrite_item = false;
		protected $results = array();

		// saves current search params
		public $id;
		public $params = array();

		// pagination data
		public $page = 1; //current page
		public $pages = false; // number of pages. false means: unknown
		public $items_per_page = 4;

		// log collector
		public $log = array();

		// some settings
		public $force_curl = false;

		// a debug feature
        public $last_fetched_url;


        /**
         *
         * Search given Data Source for Query
         *
         * This is a generic function, it can be overwritten in some implementations
         *
         *
         * @param int $page
         * @param bool $test
         * @return true or false depending to success;
         */
		function fetch(int $page = 0, $ids = false, $test = false) : bool {
			try {

                $queryurl = $this->api_fetch_url($page, $ids);

                if ($this->debug) {
                    echo shap_debug($queryurl);
                }

                $result = $this->_generic_api_call($queryurl);
                $this->results = $this->parse_result_set($result, $test);

			} catch (\Exception $e) {
				$this->error($e->getMessage());
			}
			if (is_array($this->errors)){
				return (!count($this->errors));
			} else {
				return true;
			}
		}


        /**
         *
         * get data from source for a specific unique identifier or URL
         *
         * This is generic function, it can be overwritten in some implementations
         *
         * @param $id - unique identifier
         *
         * @return \esa_item
         * @throws \Exception
         */
		function get($id) {
			$this->id = (isset($_POST['esa_ds_id'])) ? $_POST['esa_ds_id'] : $id;
			return $this->parse_result($this->_generic_api_call($this->api_single_url($this->id)));
		}

        /**
         * used for the generic get and search function only;
         *
         * @param string $url
         * @return object|string
         * @throws \Exception
         */
		function _generic_api_call($url) {

			if (!$url) {
				throw new \Exception('No Query: ' . $url);
			}

			$response = $this->_fetch_external_data($url);

			if ($this->debug) {
                echo "url: ", shap_debug($url), "POST:", shap_debug($_POST), "Response:", shap_debug((array) json_decode($response));
			}

			return $response;
		}


        /**
         *
         * This functions parses a result from a api and brings it in the needed form
         * it HAS to be implemented in every data source class
         *
         * @param array|object $result
         *
         * @param bool $test
         * @return array
         */
		abstract function parse_result_set($result, bool $test = false) : array;

		abstract function parse_result($result);

		abstract function api_single_url($id, $params = array()) : string;

		abstract function api_record_url($id, $params = array()) : string;

		abstract function api_fetch_url(int $page);

		/**
		 *
		 * register error message
		 *
		 * @param string $error_text
		 */
		protected function error($error_text) {
			$this->log[] = array('msg' => $error_text, 'type' => 'error');
		}

        protected function log($log_text, string $type = 'info') {
            $this->log[] = array('msg' => $log_text, 'type' => $type);
        }

        protected function filter_log(string $type) : array {
		    return array_filter($this->log, function($entry) use ($type) {
		        return $entry['type'] == $type;
            });
        }


		/**
		 * shows the list of errors
		 *
		 */
		function show_errors() {
			echo "<div class='shap_error_list'>";
			foreach ($this->log as $log_entry) {
				echo "<div class='{$log_entry['type']}'>{$log_entry['msg']}</div>";
			}
			echo "</div>";
		}

		/**
		 * if the functionality of the datasource relies onto something special like specific php libraries or external software,
		 * you can implement a dependency check on wose result the availability in wordpress depends.
		 * @return string
		 * @throws \Exception if not
		 */
		function dependency_check() : string {
			return 'O. K.';
		}

        /**
         * checks if curl is available.. can be used in dependency_check
         */
		function check_for_curl() : bool {
		    return (
                function_exists("curl_init") and
                function_exists("curl_setopt") and
                function_exists("curl_exec") and
                function_exists("curl_close")
            );
        }

        /**
         * @param bool $only_success
         * @return array
         */
        function get_results(bool $only_success = false) : array {
            if ($only_success) {
                return array_filter($this->results, function($entry) {
                    return $entry !== null;
                });
            }
		    return $this->results;
        }


		/**
		 * fetches $data from url, using curl if possible, if not it uses file_get_contents
		 * @param string or object $url
         *  object variant: {
         *    url : ...,
         *    post_params: ...,
         *    post_json: ...,
         *    method: post | get
         *  }
         * @return string object containing error
         * @throws \Exception
		 */
		protected function _fetch_external_data($url) {

			if (!$url) {
				throw new \Exception('no $url!');
			}

			$curl = $this->force_curl or is_object($url);

			if($curl){

			    if (!$this->check_for_curl()) {
                    throw new \Exception('No Curl Extension!');
                }

                if (!is_object($url)) {
			        $url = (object) array("url" => $url);
                }

                if (!isset($url->url)) {
                    throw new \Exception('URL missing!');
                }

                $ch = curl_init();

				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_URL, $url->url);

				if (isset($url->post_params)) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $url->post_params);
                }

                if (isset($url->post_json)) {
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
                            'Content-Type: application/json',
                            'Content-Length: ' . strlen(json_encode($url->post_json)))
                    );
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($url->post_json));
                }

                if (isset($url->method) and $url->method == 'post') {
                    curl_setopt($ch, CURLOPT_POST, 1);
                }

                if ($this->debug) {
                    echo "<pre>mode: curl</pre>";
                    curl_setopt($ch, CURLINFO_HEADER_OUT, true);
                }

				$response = curl_exec($ch);

				if(curl_errno($ch)) {
                    throw new \Exception('Curl Error: ' . curl_error($ch));
                }

                $info = curl_getinfo($ch);

                if ($this->debug) {
                    echo '<pre>Took ' . $info['total_time'] . ' seconds to send a request to ' . $info['url'] . '</pre>';
                }

                if (($info['http_code'] < 200) or ($info['http_code'] >= 400)) {
                    throw new \Exception($this->parse_error_response($response));
                }

				curl_close($ch);

				return $response;
			}


			if (!$data = file_get_contents($url)) {
				throw new \Exception("no response to $url!");
			}

			return $data;
		}

        /**
         * @param $url
         * @param $filename
         * @return string
         * @throws \Exception
         */
        function download_image($url, $filename) : string {
            $wp_upload_dir = wp_upload_dir();
            $ch = curl_init($url);
            $filepath = $wp_upload_dir['path'] . '/' . $filename;
            $fp = fopen($filepath, 'w+');
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, 0);
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            $result = curl_exec($ch);
            $error = curl_errno($ch);
            fclose($fp);
            curl_close($ch);
            if($error) {
                throw new \Exception('Curl Error: ' . $error);
            }
            if (!file_exists($filepath)) {
                throw new \Exception("Something went wrong downloading $filepath");
            }
            return $result ? $filepath : "";
        }

		/**
		 * json decode with error handling
		 */
		protected function _json_decode($json) {
			$dec = json_decode($json);

			switch (json_last_error()) {
				case JSON_ERROR_NONE:
					break;
				case JSON_ERROR_DEPTH:
					$this->error('json error: - Maximum stack depth exceeded');
					break;
				case JSON_ERROR_STATE_MISMATCH:
					$this->error('json error: - Underflow or the modes mismatch');
					break;
				case JSON_ERROR_CTRL_CHAR:
					$this->error('json error: - Unexpected control character found');
					break;
				case JSON_ERROR_SYNTAX:
					$this->error('json error: - Syntax error, malformed JSON');
					break;
				case JSON_ERROR_UTF8:
					$this->error('json error: - Malformed UTF-8 characters, possibly incorrectly encoded');
					break;
				default:
					$this->error('json error: - Unknown error');
					break;
			}

			return $dec;
		}


	}
}




?>
