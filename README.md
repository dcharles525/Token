# Token
A simple (Salesforce/Other?) token management system for PHP. 

Usage
```
<?php 

  require_once ("Token.php");
  $Token = new Token (
    "salesforce",
    "https://someurl.com/auth",
    "https://someurl.com/action",
    true
  );

  echo $Token->token;

?>
```
