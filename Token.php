<?php 
// Token
// 
// Token is meant to be a database less/stateless token management system.
// It was originally created to manage Salesforce tokens, but can be used for
// other services and can be easily extended by the getPayload function.
//
// To initialize this class we can do something like:
//
// require_once ("Token.php");
// $Token = new Token (
//   "salesforce",
//   "https://someurl.com/auth",
//   "https://someurl.com/action",
//   true
// );
//
// echo $Token->token;
//
// Note everytime you run a action you should run the getToken function in 
// order to ensure that token is up to date. You can also use Token to 
// manage more than one token! Just initialize the class with or without 
// the first token you are looking for, then create another class instance
// for other tokens.  
//
// Author: David Johnson
// Last Modified Date: 11/30/20

Class Token {

  /*We keep everything private other than the token as we want to force 
  construction of the variables through construct!*/
  
  private $testing = true, $tokenPath, $url, $tokenUrl;
  public $token;

  //Setting up variables and setting the initial token
  public function __construct ($tokenName, $url, $tokenUrl, $testing) {

    $this->tokenPath = "tokens\$tokenName.txt";
    $this->url = $url;
    $this->tokenUrl = $tokenUrl;
    $this->testing = $testing;
    
    $this->getToken ($tokenName);
  
  }

  //Gets token
  //
  //Takes the token name in and first looks for the token on the 
  //file system. If this file doesn't exist we create it, then we
  //test the current token, if its valid we do nothing but set the 
  //token variable, if its not, we re-authenticate and set the token.
  //
  //Note we do rewrite the file using the w+ mode as we need to be 
  //able to quickly overwrite the old token, but since we do this 
  //we have to write the token (refreshed or not) to the file.
  //
  //@param string $tokenName
  //@return void
  public function getToken ($tokenName) {

    $tokenFileObject = fopen ($this->tokenPath, "w+");

    //If we were able to get the token file handler
    if ($tokenFileObject) {

      //reading the whole file
      $token = fread (
        $tokenFileObject, 
        filesize ($this->tokenPath) + 1
      );

      //getting new token if other is expired/invalid
      if ($this->testToken ($token) === 1) 
        $this->token = $token;
      else
        $this->token = $this->authenticate ();
    
    }

    fwrite ($tokenFileObject, $this->token);
    fclose ($tokenFileObject);

  }

  // 
  //Utils
  //

  //Checks if token is valid 
  //
  //This function calls the action api endpoint and we should get a response
  //with proper data or an 'errorCode' which may be service specific. If we 
  //have an errorCode we return the internal error code and tell the parent
  //function to re-authenticate. 
  //
  //@param string $token
  //@return int 
  private function testToken ($token): int {

    if ($token != "") {

      $curl = curl_init ();
      curl_setopt ($curl, CURLOPT_URL, $this->tokenUrl);
      curl_setopt ($curl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt ($curl, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt ($curl, CURLOPT_HTTPHEADER, array (
        "Content-Type: application/json",
        "Authorization: Bearer $this->token"
      ));

      $result = curl_exec ($curl);
      curl_close ($curl);

      if ($result) {

        $decodedResults = json_decode ($result);

        if ($this->decodedResults->errorCode !== null)
          return -1;
        else 
          return 1;

      } else 
          return -2;

    } else
        return -3; 
      
  
  }

  //Authenticates with service
  //
  //This function simply just gets the authenticate payload and tries to 
  //retrieve a access_token from the service. If we don't we return null
  //which will bubble up and cause authenticate to fail. 
  //
  //@return string
  private function authenticate (): string {

    $payload = $this->getPayload ();
    $curl = curl_init ();

    curl_setopt ($curl, CURLOPT_URL, $this->url);
    curl_setopt ($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt ($curl, CURLOPT_POST, true);
    curl_setopt ($curl, CURLOPT_POSTFIELDS, $payload);
    curl_setopt ($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt ($curl, CURLOPT_HTTPHEADER, array (
      "Content-Type: application/x-www-form-urlencoded"
    ));

    //curl_setopt ($curl, CURLOPT_VERBOSE, true);
    //curl_setopt ($curl, CURLOPT_STDERR, fopen ("curl_log.txt", "w+"));

    $result = curl_exec ($curl);
    curl_close ($curl);

    if ($result) {

      $decodedResults = json_decode ($result);
      return $decodedResults->access_token;

    } else
        return null;
  
  }

  //Grabs payload info
  //
  //We open our config files in our directory depending on if we are testing
  //and concatinate the string together for usage in the authenticate function.
  //
  //@return string
  private function getPayload (): string {

    if ($this->testing)
      require_once ("Config_Dev.php");
    else 
      require_once ("Config_Prod.php");

    $Config = new Config ();

    $username = $Config->username ();
    $password = $Config->password ();
    $clientId = $Config->clientid ();
    $clientSecret = $Config->clientsecret ();

    return "grant_type=password&client_id=$clientId".
      "&client_secret=$clientSecret&username=$username&password=$password";

  }

}

?>
