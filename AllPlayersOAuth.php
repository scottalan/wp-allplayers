<?php
/*
 * Chris Christensen http://imetchrischris.com
 *  Originally: Twitter: Abraham Williams (abraham@abrah.am) http://abrah.am
 *
 * Basic lib to work with AllPlayers' OAuth beta.
 *
 * Code based on:
 * Fire Eagle code - http://github.com/myelin/fireeagle-php-lib
 * twitterlibphp - http://github.com/poseurtech/twitterlibphp
 *
 * Modified for standard WordPress HTTP API support by Otto - otto@ottodestruct.com
 *
 */

/* Load OAuth lib. You can find it at http://oauth.net */
require_once('OAuth.php');

/**
 * AllPlayers OAuth class
 */
class AllPlayersOAuth {
  /* Contains the last HTTP status code returned */
  private $http_status;

  /* Contains the last API call */
  private $last_api_call;

  /* Set up the API root URL */
  public static $TO_API_ROOT = "https://www.allplayers.com";

  /**
   * Set API URLS
   */
  function requestTokenURL() { return self::$TO_API_ROOT.'/oauth/request_token'; }
  function authorizeURL() { return self::$TO_API_ROOT.'/oauth/authorize'; }
  function authenticateURL() { return self::$TO_API_ROOT.'/oauth/authorize'; }
  function accessTokenURL() { return self::$TO_API_ROOT.'/oauth/access_token'; }

  /**
   * Debug helpers
   */
  function lastStatusCode() { return $this->http_status; }
  function lastAPICall() { return $this->last_api_call; }

  /**
   * construct AllPlayersOAuth object
   */
  function __construct($consumer_key, $consumer_secret, $oauth_token = NULL, $oauth_token_secret = NULL) {
    $this->sha1_method = new OAuthSignatureMethod_HMAC_SHA1();
    $this->consumer = new OAuthConsumer($consumer_key, $consumer_secret);
    if (!empty($oauth_token) && !empty($oauth_token_secret)) {
      $this->token = new OAuthConsumer($oauth_token, $oauth_token_secret);
    } else {
      $this->token = NULL;
    }
  }


  /**
   * Get a request_token from AllPlayers.com
   *
   * @returns a key/value array containing oauth_token and oauth_token_secret
   */
  function getRequestToken() {
    $r = $this->oAuthRequest($this->requestTokenURL());
    $token = $this->oAuthParseResponse($r);
    $this->token = new OAuthConsumer($token['oauth_token'], $token['oauth_token_secret']);
    return $token;
  }

  /**
   * Parse a URL-encoded OAuth response
   *
   * @return a key/value array
   */
  function oAuthParseResponse($responseString) {
    $r = array();
    foreach (explode('&', $responseString) as $param) {
      $pair = explode('=', $param, 2);
      if (count($pair) != 2) continue;
      $r[urldecode($pair[0])] = urldecode($pair[1]);
    }
    return $r;
  }

  /**
   * Get the authorize URL
   *
   * @returns a string
   */
  function getAuthorizeURL($token) {
    if (is_array($token)) $token = $token['oauth_token'];
    return $this->authorizeURL() . '?oauth_token=' . $token;
  }


  /**
   * Get the authenticate URL
   *
   * @returns a string
   */
  function getAuthenticateURL($token) {
    if (is_array($token)) $token = $token['oauth_token'];
    return $this->authenticateURL() . '?oauth_token=' . $token;
  }

  /**
   * Exchange the request token and secret for an access token and
   * secret, to sign API calls.
   *
   * @returns array("oauth_token" => the access token,
   *                "oauth_token_secret" => the access secret)
   */
  function getAccessToken($token = NULL) {
    $r = $this->oAuthRequest($this->accessTokenURL());
    $token = $this->oAuthParseResponse($r);
    $this->token = new OAuthConsumer($token['oauth_token'], $token['oauth_token_secret']);
    return $token;
  }

  /**
   * Format and sign an OAuth / API request
   */
  function oAuthRequest($url, $args = array(), $method = NULL) {
    if (empty($method)) $method = empty($args) ? "GET" : "POST";
    $req = OAuthRequest::from_consumer_and_token($this->consumer, $this->token, $method, $url, $args);
    $req->sign_request($this->sha1_method, $this->consumer, $this->token);

    $response = false;
    $url=null;

    switch ($method) {
    case 'GET':
        $url = $req->to_url();
        $response = wp_remote_get( $url );
        break;
    case 'POST':
        $url = $req->get_normalized_http_url();
        $args = wp_parse_args($req->to_postdata());
        $response = wp_remote_post( $url, array('body'=>$args));
        break;
    }

    if ( is_wp_error( $response ) ) return false;

    $this->http_status = $response['response']['code'];
    $this->last_api_call = $url;

    return $response['body'];
  }
}

