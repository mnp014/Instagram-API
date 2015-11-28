<?php

require_once 'Instagram.php';
require_once 'InstagramException.php';


class InstagramRegistration
{

  protected $debug;
  protected $IGDataPath;
  protected $username;

  public function InstagramRegistration($debug = false, $IGDataPath = null)
  {
    $this->debug = $debug;
    if (!is_null($IGDataPath))
      $this->IGDataPath = $IGDataPath;
    else
      $this->IGDataPath = __DIR__ . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR;
  }

  /**
  * Checks if the username is already taken (exists)
  *
  * @param string $username
  *
  * @return array
  *   Username availability data
  */
  public function checkUsername($username)
  {
      $data = json_encode(array(
          '_uuid'  => $this->uuid,
          'username'   => $username,
          '_csrftoken' => 'missing'
      ));

      return $this->request("users/check_username/", $this->generateSignature($data))[1];
  }


  /**
  * Register account
  *
  * @param String $username
  *
  * @param String $password
  *
  * @param String $email
  *
  * @return array
  *   Registration data
  */
  public function createAccount($username, $password, $email)
  {
      $data = json_encode(array(
          '_uuid'  => $this->generateUUID(true),
          'username'   => $username,
          'password'   => $password,
          'device_id'  => $this->generateUUID(true),
          'email'      => $email,
          '_csrftoken' => 'missing'
      ));

      $result = $this->request("accounts/create/", $this->generateSignature($data), $username);
      if (isset($result[1]['account_created']) && ($result[1]['account_created'] == true))
      {
        $this->username_id = $result[1]['created_user']['pk'];
        file_put_contents($this->IGDataPath . "$username-userId.dat", $this->username_id);
        preg_match('#Set-Cookie: csrftoken=([^;]+)#', $result[0], $match);
        $token = $match[1];
        $this->username = $username;
        file_put_contents($this->IGDataPath . "$username-token.dat", $token);
        rename($this->IGDataPath . "cookies.dat", $this->IGDataPath . "$username-cookies.dat");
      }
      return $result;
  }


  public function generateSignature($data)
  {
    $hash = hash_hmac('sha256', $data, 'c1c7d84501d2f0df05c378f5efb9120909ecfb39dff5494aa361ec0deadb509a');

    return 'ig_sig_key_version=4&signed_body=' . $hash . '.' . urlencode($data);
  }

  public function generateUUID($type)
  {
    $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
      mt_rand(0, 0xffff), mt_rand(0, 0xffff),
      mt_rand(0, 0xffff),
      mt_rand(0, 0x0fff) | 0x4000,
      mt_rand(0, 0x3fff) | 0x8000,
      mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );

    return $type ? $uuid : str_replace('-', '', $uuid);
  }

  public function request($endpoint, $post = null) {

   $ch = curl_init();

   curl_setopt($ch, CURLOPT_URL, 'https://i.instagram.com/api/v1/' . $endpoint);
   curl_setopt($ch, CURLOPT_USERAGENT, 'Instagram 7.10.0 Android (23/6.0; 515dpi; 1440x2416; huawei/google; Nexus 6P; angler; angler; en_US)');
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
   curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
   curl_setopt($ch, CURLOPT_HEADER, true);
   curl_setopt($ch, CURLOPT_VERBOSE, false);
   if (file_exists($this->IGDataPath . "$this->username-cookies.dat"))
   {
     curl_setopt($ch, CURLOPT_COOKIEFILE, $this->IGDataPath . "$this->username-cookies.dat");
     curl_setopt($ch, CURLOPT_COOKIEJAR, $this->IGDataPath . "$this->username-cookies.dat");
   }
   else {
     curl_setopt($ch, CURLOPT_COOKIEFILE, $this->IGDataPath . "cookies.dat");
     curl_setopt($ch, CURLOPT_COOKIEJAR, $this->IGDataPath . "cookies.dat");
   }

   if ($post) {

   curl_setopt($ch, CURLOPT_POST, true);
   curl_setopt($ch, CURLOPT_POSTFIELDS, $post);

   }

   $resp       = curl_exec($ch);
   $header_len = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
   $header     = substr($resp, 0, $header_len);
   $body       = substr($resp, $header_len);

   curl_close($ch);

   if ($this->debug)
   {
     echo "REQUEST: $endpoint\n";
     if (!is_null($post))
     {
       if (!is_array($post))
        echo "DATA: $post\n";
     }
     echo "RESPONSE: $body\n\n";
   }

   return array($header, json_decode($body, true));

  }
}