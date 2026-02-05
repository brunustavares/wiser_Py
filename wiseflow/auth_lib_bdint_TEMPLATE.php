<?php
/**
 * auth parameters for WISEflow, Moodle and BDInt
 * (developed for UAb - Universidade Aberta)
 *
 * @package    auth_lib_bdint
 * @category   php_config
 * @author     Bruno Tavares <brunustavares@gmail.com>
 * @link       https://www.linkedin.com/in/brunomastavares/
 * @copyright  Copyright (C) 2022-present Bruno Tavares
 * @license    GNU General Public License v3 or later
 *             https://www.gnu.org/licenses/gpl-3.0.html
 * @version    2025020702
 * @date       2022-10-21
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

// e-mail settings
    /**
     * configuração do serviço de e-mail
     *
     */
    require 'C://php//php7//extras//PHPMailer//src//PHPMailer.php';
    require 'C://php//php7//extras//PHPMailer//src//SMTP.php';
    require 'C://php//php7//extras//PHPMailer//src//Exception.php';

    use PHPMailer\PHPMailer\PHPMailer;
    use PHPMailer\PHPMailer\SMTP;
    use PHPMailer\PHPMailer\Exception;

    $email = new PHPMailer(true);

    $email->isSMTP();
    $email->IsHTML(true);
    $email->CharSet = 'UTF-8';

    $email->Host = "<your_hidden_host>";
    $email->Port = "<your_hidden_port>";
    
    $email->SMTPAuth = true;
    $email->SMTPSecure = 'tls';
    $email->SMTPOptions = array(
                                'ssl' => array(
                                                'verify_peer' => false,
                                                'verify_peer_name' => false,
                                                'allow_self_signed' => true
                                                )
    );
    // $email->SMTPDebug = 2;
    $email->Username = '<your_hidden_username>';
    $email->Password = '<your_hidden_password>';

// WISEflow
    // parte do URL comum a todas as APIs
        GLOBAL $base_url;
        $base_url = 'https://<hidden-url>/';

    // cadeias p/ reforço da encriptação
        GLOBAL $privateKey, $secretKey, $encryptMethod;

        $privateKey    = '<hidden-private-key>';
        $secretKey     = '<hidden-secret-key>';
        $encryptMethod = "<hidden-encryption-method>";

    /**
     * Encriptação do token de acesso às APIs, para guardar em ficheiro
     *
     * @return string encrypted_string
     */
    function encrypt_token($token_string) {
        GLOBAL $privateKey, $secretKey, $encryptMethod;

        $encrypted_string = "<hidden-encryption-algorithm>";

        return $encrypted_string;

    }

    /**
     * Desencriptação do token de acesso às APIs, após leitura em ficheiro
     *
     * @return string token_string
     */
    function decrypt_token($encrypted_string) {
        GLOBAL $privateKey, $secretKey, $encryptMethod;

        $token_string = "<hidden-decryption-algorithm>";

        return $token_string;

    }

    /**
     * Obtenção do token de acesso às APIs
     *
     * @return array token
     */
    function getwftoken()
    {
        GLOBAL $base_url;

        $client_id     = '<hidden-client-id>';
        $client_secret = '<hidden-client-secret>';
        $grant_type    = 'client_credentials';
    
        $token[] = '';

        $url = $base_url . "oauth2/token";

        $auth = ['client_id'=>$client_id,
                 'client_secret'=>$client_secret,
                 'grant_type'=>$grant_type];
    
        $headers = array(
                         "accept:application/json",
                         "content-type:application/x-www-form-urlencoded",
                        );
    
        $data = array(
                      CURLOPT_POST => true,
                      CURLOPT_RETURNTRANSFER => true,
                      CURLOPT_ENCODING => '',
                      CURLOPT_MAXREDIRS => 10,
                      CURLOPT_TIMEOUT => 0,
                      CURLOPT_FOLLOWLOCATION => true,
                      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                      CURLOPT_SSL_VERIFYHOST => false,
                      CURLOPT_SSL_VERIFYPEER => false,
                      CURLOPT_URL => $url,
                      CURLOPT_HTTPHEADER => $headers,
                      CURLOPT_POSTFIELDS => http_build_query($auth)
                     );

        $curl = curl_init();

        curl_setopt_array($curl, $data);
    
        $response = curl_exec($curl);
        // $errNo    = curl_errno($curl);
        // $err      = curl_error($curl);
    
        curl_close($curl);
    
        $flow = json_decode($response, true);
    
        $token = ['chain'=>$flow['access_token'],
                  'expire'=>$flow['expires_in'],
                  'type'=>$flow['token_type']];

        return $token;

    }

// base de dados intermédia - BDInt
    // variáveis comuns
        GLOBAL $host, $port, $usr, $pwd, $db;

        $host = '<hidden-host>';
        $port = '<hidden-port>';
        $usr  = '<hidden-usr>';
        $pwd  = '<hidden-pwd>';
        $db   = '<hidden-db>';

    /**
     * Conexão à BDInt
     *
     * @return mysqli connection
     */
    function connect2bdint()
    {
        GLOBAL $host, $port, $usr, $pwd, $db;

        $connection = mysqli_connect($host, $usr, $pwd, $db, $port)
                          or die('Ñ foi possível aceder à BDInt: ' . mysqli_connect_error());

        return $connection;

    }

// PlataformAbERTA
    // variáveis globais
        GLOBAL $mdl_wsURL, $mdl_token;
        $mdl_wsURL = 'https://<hidden-url>/';
        $mdl_token = '<hidden-token>';

    /**
     * Conexão ao web service da PlataformAbERTA
     *
     * @return string
     */
    function connect2mdl($endpoint) {
        GLOBAL $mdl_wsURL, $mdl_token;

        $connection = $mdl_wsURL
                    . '?wstoken=' . $mdl_token
                    . '&wsfunction=' . $endpoint
                    . '&moodlewsrestformat=json';

        return $connection;

    }
