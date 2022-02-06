<?php

### PHP MICROSOFT OAUTH2 IMPLEMENTATION FOR REFERENCE

# To be set in the app admin side/config/db
$client_id = "XYZ";
$client_secret = "secret";
$app_url = "https://samplesite.loc";

# https://docs.microsoft.com/en-us/azure/active-directory/develop/v2-oauth2-auth-code-flow
## {tenant} is set to organistions to allow any MS Work/School account - See above for valid values. Must be used in conjunction with the correct setting on the App Registration
$auth_code_url = "https://login.microsoftonline.com/organizations/oauth2/v2.0/authorize";
$token_grant_url = "https://login.microsoftonline.com/organizations/oauth2/v2.0/token";

session_start();

echo "<br>";

echo session_id();

echo "<h1>MS OAuth2.0 Demo </h1><br>";

var_dump($_SESSION);

if (isset ($_SESSION['is_ms_authenticated'])){

    echo "<h2>Authenticated ".$_SESSION["user"]." </h2><br> ";

    echo "<h3>$_SESSION[upn]</h3>";

    if($_SESSION['id'] == 'c831081b-f08e-4ef2-a741-897bdd91aa72'){
        echo "ID Match";
    }

    echo '<p><a href="?action=logout">Log Out</a></p>';

}
else{
    echo '<h2><p>You can <a href="?action=login">Log In</a> with Microsoft</p></h2>';
}

// Initial Login Request, via Microsoft
// Returns a authorization code if login was successful
if ($_GET['action'] == 'login'){

    $params = array (
        'client_id' =>$client_id,
        'redirect_uri' => $app_url,

        #'response_type' =>'token',
        'response_type' => 'code',

        'response_mode' =>'form_post',
        'scope' => 'https://graph.microsoft.com/User.Read',
        'state' => session_id());

    header ('Location: '.$auth_code_url.'?'.http_build_query ($params));

}

// Login was successful, Microsoft has returned us a authorization code via POST
// Request an access token using authorization code (& client secret) (server side)
if (isset($_POST['code']) && $_POST['state'] == session_id()){

    $params = array (
        'client_id' =>$client_id,
        'code' => $_POST['code'],
        'redirect_uri' => $app_url,
        'grant_type' => 'authorization_code',
        'client_secret' => $client_secret
    );

    // Send request via CURL (server side) so user cannot see the client secret
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL,$token_grant_url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS,
        http_build_query($params));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER,1);

    $access_token_response = json_decode(curl_exec($ch),1);
    //curl_close ($ch);
    var_dump($ch);
    var_dump($access_token_response);

    // Check if we have an access token
    // If we do, send a request to Microsoft Graph API to get user info
    if (isset($access_token_response['access_token'])){

        $ch = curl_init();
        curl_setopt ($ch, CURLOPT_HTTPHEADER, array ('Authorization: Bearer '.$access_token_response['access_token'],
            'Content-type: application/json'));
        curl_setopt ($ch, CURLOPT_URL, "https://graph.microsoft.com/v1.0/me/");
        curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);

        $msgraph_response = json_decode (curl_exec ($ch), 1);

        if (isset($msgraph_response['error'])){
            // Something went wrong verifying the token/using the Graph API - quit
            echo "Error with MS Graph API. Details:";
            var_dump ($msgraph_response['error']);
            exit();
        }

        elseif(isset($msgraph_response['id'])){

            $_SESSION['is_ms_authenticated'] = 1;  //auth and verified

            $_SESSION['user'] = $msgraph_response["displayName"];

            $_SESSION['id'] = $msgraph_response["id"];

            $_SESSION['upn'] = $msgraph_response["userPrincipalName"];
        }
        header ('Location: https://samplesite.loc');
    }
    else{
        echo "Error getting access_token / state did not match";
    }

}

if ($_GET['action'] == 'logout'){

    setcookie("PHPSESSID", '', time() - 3600, "/");
    unset($_COOKIE['PHPSESSID']);
    session_unset();
    session_destroy();

    header ('Location: https://samplesite.loc');

}
