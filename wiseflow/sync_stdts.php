<?php
/**
 * manages students in WISEflow
 * (developed for UAb - Universidade Aberta)
 *
 * @package    sync_stdts
 * @category   php_script
 * @author     Bruno Tavares <brunustavares@gmail.com>
 * @link       https://www.linkedin.com/in/brunomastavares/
 * @copyright  Copyright (C) 2022-present Bruno Tavares
 * @license    GNU General Public License v3 or later
 *             https://www.gnu.org/licenses/gpl-3.0.html
 * @version    2023122002
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

require ("auth_lib_bdint.php");

date_default_timezone_set('Europe/Lisbon');

define('FULLDATE', "Y-m-d H:i:s");

GLOBAL $base_url;

/**
 * Verifica a origem da chamada: CLI ou browser
 *
 * @return boolean
 */
function Is_cli()
{
    if (defined('STDIN')
        || (empty($_SERVER['REMOTE_ADDR'])
        && !isset($_SERVER['HTTP_USER_AGENT'])
        && count($_SERVER['argv']) > 0)
    ) {
        return true;

    }

    return false;

}

/**
 * Configura valores padrão p/ os requests curl
 *
 * @return array curlopt_base
 */
function set_curl_params($start_time)
{
    $auth_chain = checkwftoken($start_time);

    $headers = array(
                     "accept:application/json",
                     "content-type:application/json",
                     "authorization:" . $auth_chain,
                    );

    $curlopt_base = array(
                          CURLOPT_RETURNTRANSFER => true,
                          CURLOPT_ENCODING => '',
                          CURLOPT_MAXREDIRS => 10,
                          CURLOPT_TIMEOUT => 0,
                          CURLOPT_FOLLOWLOCATION => true,
                          CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                          CURLOPT_HTTPHEADER => $headers,
                          CURLOPT_SSL_VERIFYHOST => false,
                          CURLOPT_SSL_VERIFYPEER => false
                         );

    return $curlopt_base;

}

/**
 * Validação do token de acesso às APIs
 *
 * @return string auth_chain
 */
function checkwftoken($start_time) {
    $token_file = "./auth.tkn";
    $newtoken = false;

    if (file_exists($token_file)) { // obtenção de token em ficheiro
        $keys = array('chain', 'expire', 'type');
        $values = explode(";", decrypt_token(file_get_contents($token_file, false)));

        $token = array_combine($keys, $values);

        $tkn_expire = filemtime($token_file) + ($token['expire']);

        if ($start_time >= ($tkn_expire - 180)) { $newtoken = true; } // token a expirar em 3min
    
    } else { $newtoken = true; }

    if ($newtoken) { // obtenção de token válido e gravação em ficheiro
        $token = getwftoken();

        file_put_contents($token_file, encrypt_token($token['chain'] . ";" . $token['expire'] . ";" . $token['type']));

    }
    
    $auth_chain = $token['type'] . " " . $token['chain'];
        
    return $auth_chain;

}

// quebras de linha nas mensagens, em função do ambiente gráfico de chamada
    $nl = "";
    if (Is_cli()) {
        $nl = "\n\n";

    } else {
        $nl = "<br><br>";

        echo '<link rel="shortcut icon" href="https://europe.wiseflow.net/favicon.ico" type="image/x-icon"/>';

    }

// acesso à BDInt
    $conBDInt = connect2bdint();

// obtenção dos parâmetros transversais do curl
    $curlopt_base = set_curl_params(time());

// levantamento de estudantes a desactivar
    $slctqry = "SELECT *
                FROM wiseflow.vw_takestdts_fromwiseflow
                ORDER BY RAND()
                LIMIT 100;";

    $stdts2updt = mysqli_query($conBDInt, $slctqry)
                      or die("Ñ foi possível consultar a view 'wiseflow.vw_takestdts_fromwiseflow': " . mysqli_error($conBDInt) . $nl);

    if (mysqli_num_rows($stdts2updt) > 0) {
        // actualizar estudantes
        while ($row = mysqli_fetch_array($stdts2updt)) {
            $httpcode = 0;
            while ($httpcode <> 200) {
                $stdid     = $row['stdid'];
                $firstname = $row['firstname'];
                $lastname  = $row['lastname'];

                $url = $base_url . "users/" . $stdid;

                $data = <<<DATA
                               {
                                "firstName": "$firstname",
                                "lastName": "$lastname",
                                "phone": "",
                                "language": "pt_PT",
                                "loginDeactivated": true
                               }
                        DATA;

                $curlopt = array_replace($curlopt_base,
                                         array(
                                               CURLOPT_URL => $url,
                                               CURLOPT_CUSTOMREQUEST => 'PUT',
                                               CURLOPT_POSTFIELDS => $data,
                                              )
                                        );

                $curl = curl_init();

                curl_setopt_array($curl, $curlopt);

                $response = curl_exec($curl);
                // $errNo = curl_errno($curl);
                // $err = curl_error($curl);

                $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            }

            curl_close($curl);
            unset($response);

            // actualizar tabela de controlo com data de desactivação
            $updtqry = "UPDATE wiseflow.students
                        SET dtunreg = \"" . date(FULLDATE) . "\"
                        WHERE stdid = " . $stdid . ";";

            mysqli_query($conBDInt, $updtqry)
                or die("Ñ foi possível actualizar a tabela 'wiseflow.students': " . mysqli_error($conBDInt) . $nl);

        }

        printf("Foram desactivados " . mysqli_num_rows($stdts2updt) . " estudantes no WISEflow" . $nl);

    } else {
        printf("Sem estudantes p/ desactivar" . $nl);

    }

// levantamento de estudantes a reactivar/actualizar
    $slctqry = "SELECT *
                FROM wiseflow.vw_renewstdts_atwiseflow
                ORDER BY RAND()
                LIMIT 100;";

    $stdts2updt = mysqli_query($conBDInt, $slctqry)
                      or die("Ñ foi possível consultar a view 'wiseflow.vw_renewstdts_atwiseflow': " . mysqli_error($conBDInt) . $nl);

    if (mysqli_num_rows($stdts2updt) > 0) {
        // actualizar estudantes
        while ($row = mysqli_fetch_array($stdts2updt)) {
            $httpcode = 0;
            while ($httpcode <> 200) {
                $stdid     = $row['stdid'];
                $firstname = $row['firstname'];
                $lastname  = $row['lastname'];

                $url = $base_url . "users/" . $stdid;

                $data = <<<DATA
                               {
                                "firstName": "$firstname",
                                "lastName": "$lastname",
                                "phone": "",
                                "language": "pt_PT",
                                "loginDeactivated": false
                               }
                        DATA;

                $curlopt = array_replace($curlopt_base,
                                         array(
                                               CURLOPT_URL => $url,
                                               CURLOPT_CUSTOMREQUEST => 'PUT',
                                               CURLOPT_POSTFIELDS => $data,
                                              )
                                        );

                $curl = curl_init();

                curl_setopt_array($curl, $curlopt);

                $response = curl_exec($curl);
                // $errNo = curl_errno($curl);
                // $err = curl_error($curl);

                $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            }

            curl_close($curl);
            unset($response);

            // actualizar tabela de controlo limpando data de desactivação e nome
            $updtqry = "UPDATE wiseflow.students
                        SET dtunreg = NULL,
                            firstname = \"$firstname\",
                            lastname = \"$lastname\"
                        WHERE stdid = " . $stdid . ";";

            mysqli_query($conBDInt, $updtqry)
                or die("Ñ foi possível actualizar a tabela 'wiseflow.students': " . mysqli_error($conBDInt) . $nl);

        }

        printf("Foram reactivados/actualizados " . mysqli_num_rows($stdts2updt) . " estudantes no WISEflow" . $nl);

    } else {
        printf("Sem estudantes p/ reactivar/actualizar" . $nl);

    }

// levantamento de novos estudantes
    $slctqry = "SELECT *
                FROM wiseflow.vw_newstdts_2wiseflow
                ORDER BY std_num
                LIMIT 100;";

    $newstdts = mysqli_query($conBDInt, $slctqry)
                    or die("Ñ foi possível consultar a view 'wiseflow.vw_newstdts_2wiseflow': " . mysqli_error($conBDInt) . $nl);

    if (mysqli_num_rows($newstdts) > 0) {
        // inserir novos estudantes na tabela de controlo
        $isrtqry = "INSERT INTO wiseflow.students(firstname, lastname, std_num, email)
                    SELECT *
                    FROM wiseflow.vw_newstdts_2wiseflow
                    ORDER BY std_num
                    LIMIT 100;";

        mysqli_query($conBDInt, $isrtqry)
            or die("Ñ foi possível registar os estudantes na tabela 'wiseflow.students': " . mysqli_error($conBDInt) . $nl);

        while ($row = mysqli_fetch_array($newstdts)) {
            // registar novos estudantes
            $httpcode = 0;
            while ($httpcode <> 200) {
                $firstname = $row['firstname'];
                $lastname  = $row['lastname'];
                $uab_id    = $row['std_num'] . "@uab.pt";
                $std_num   = $row['std_num'];
                $email     = $row['email'];
                        
                $url = $base_url . "license/user";

                $data = <<<DATA
                               {
                                "firstName": "$firstname",
                                "lastName": "$lastname",
                                "externalIds": [
                                                {
                                                 "userDataTypeId": 696,
                                                 "value": "$uab_id"
                                                },
                                                {
                                                 "userDataTypeId": 698,
                                                 "value": "$std_num"
                                                }
                                               ],
                                "language": "pt",
                                "roles": [
                                          5726
                                         ],
                                "emails": [
                                           "$email"
                                       ]
                               }
                        DATA;

                $curlopt = array_replace($curlopt_base,
                                         array(
                                               CURLOPT_URL => $url,
                                               CURLOPT_CUSTOMREQUEST => 'POST',
                                               CURLOPT_POSTFIELDS => $data,
                                              )
                                        );

                $curl = curl_init();

                curl_setopt_array($curl, $curlopt);

                $response = curl_exec($curl);
                // $errNo = curl_errno($curl);
                // $err = curl_error($curl);

                $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

                if ($httpcode == 201) { $httpcode = 200; }

            }

            $user = json_decode($response, true);

            curl_close($curl);
            unset($response);

            // actualizar tabela de controlo com userid e data de registo
            $updtqry = "UPDATE wiseflow.students
                        SET stdid = " . $user['userId'] . ",
                            dtreg = \"" . date(FULLDATE) . "\"
                        WHERE std_num = " . $std_num . ";";

            mysqli_query($conBDInt, $updtqry)
                or die("Ñ foi possível actualizar a tabela 'wiseflow.students': " . mysqli_error($conBDInt) . $nl);

        }

        printf("Foram registados " . mysqli_num_rows($newstdts) . " novos estudantes no WISEflow" . $nl);

    } else {
        printf("Sem novos estudantes p/ registar" . $nl);

    }

// // actualização de estudantes c/ NEEs
//     $slctqry = "SELECT *
//                 FROM wiseflow.students
//                 WHERE xtrT IS NOT NULL
//                 ORDER BY RAND()
//                 LIMIT 100;";

//     $stdtswnees = mysqli_query($conBDInt, $slctqry)
//                       or die("Ñ foi possível consultar a tabela 'wiseflow.students': " . mysqli_error($conBDInt) . $nl);

//     if (mysqli_num_rows($stdtswnees) > 0) {
//         $nees = 0;

//         while ($std = mysqli_fetch_array($stdtswnees)) {
//             $stdid  = $std['stdid'];
//             $xtrT   = $std['xtrT'] * 60;
//             $status = $std['status'];

//             // obter lista de flows do estudante
//             $slctqry = "SELECT *
//                         FROM wiseflow.flows_assess AS flw_ass
//                             INNER JOIN wiseflow.flows AS flw ON flw.flowid = flw_ass.flowid
//                         WHERE flw_ass.stdid = '" . $stdid . "'
//                             AND flw_ass.partid IS NOT NULL
//                             AND flw.dtfrom >= NOW();";

//             $stdtflows = mysqli_query($conBDInt, $slctqry)
//                 or die("Ñ foi possível consultar a tabela 'wiseflow.flows_assess': " . mysqli_error($conBDInt) . $nl);

//             if (mysqli_num_rows($stdtflows) > 0) {
//                 // obter datas dos flows
//                 while ($stdflw = mysqli_fetch_array($stdtflows)) {
//                     $httpcode = 0;
//                     while ($httpcode <> 200) {
//                         $flowid = $stdflw['flowid'];
//                         $partid = $stdflw['partid'];

//                         $url = $base_url . "flows/" . $flowid . "/dates";

//                         $curlopt = array_replace($curlopt_base,
//                                                  array(
//                                                        CURLOPT_URL => $url,
//                                                        CURLOPT_CUSTOMREQUEST => 'GET',
//                                                       )
//                                                 );

//                         $curl = curl_init();

//                         curl_setopt_array($curl, $curlopt);

//                         $response = curl_exec($curl);
//                         // $errNo = curl_errno($curl);
//                         // $err = curl_error($curl);

//                         $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
//                     }
        
//                     $glbflw_dates = json_decode($response, true);

//                     curl_close($curl);
//                     unset($response);

//                     // obter datas específicas do estudante
//                     $httpcode = 0;
//                     while ($httpcode <> 200) {
//                         $url = $base_url . "flows/" . $flowid . "/" . "participants" . "/" . $partid . "/dates";

//                         $curlopt = array_replace($curlopt_base,
//                                                  array(
//                                                        CURLOPT_URL => $url,
//                                                        CURLOPT_CUSTOMREQUEST => 'GET',
//                                                       )
//                                                 );

//                         $curl = curl_init();

//                         curl_setopt_array($curl, $curlopt);

//                         $response = curl_exec($curl);
//                         // $errNo = curl_errno($curl);
//                         // $err = curl_error($curl);

//                         $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
//                     }
        
//                     $stdflw_dates = json_decode($response, true);

//                     curl_close($curl);
//                     unset($response);

//                     // validar e actualizar datas específicas para o estudante
//                     if ($glbflw_dates['data']['participation']['end'] >= time()) {
//                         $result['success'] = "false";

//                         if ($status == 1
//                             && $stdflw_dates['data']['participation']['end'] <> $glbflw_dates['data']['participation']['end'] + $xtrT) {
//                             $httpcode = 0;
//                             while ($httpcode <> 200) {
//                                 $start = $glbflw_dates['data']['participation']['start'];
//                                 $end = $glbflw_dates['data']['participation']['end'] + $xtrT;

//                                 $url = $base_url . "flows/" . $flowid . "/" . "participants" . "/" . $partid . "/dates";

//                                 $data = <<<DATA
//                                                {
//                                                 "participation": {
//                                                                   "start": $start,
//                                                                   "end": $end
//                                                                  }
//                                                }
//                                         DATA;

//                                 $curlopt = array_replace($curlopt_base,
//                                                         array(
//                                                               CURLOPT_URL => $url,
//                                                               CURLOPT_CUSTOMREQUEST => 'PATCH',
//                                                               CURLOPT_POSTFIELDS => $data,
//                                                              )
//                                                         );

//                                 $curl = curl_init();

//                                 curl_setopt_array($curl, $curlopt);

//                                 $response = curl_exec($curl);
//                                 // $errNo = curl_errno($curl);
//                                 // $err = curl_error($curl);

//                                 $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                
//                             }
                
//                             $result = json_decode($response, true);

//                             curl_close($curl);
//                             unset($response);

//                         } elseif ($status == 0
//                             && $xtrT > 0
//                             && $stdflw_dates['data']['participation']['end'] == $glbflw_dates['data']['participation']['end'] + $xtrT) {
//                             $httpcode = 0;
//                             while ($httpcode <> 200) {
//                                 $start = $glbflw_dates['data']['participation']['start'];
//                                 $end = $glbflw_dates['data']['participation']['end'];

//                                 $url = $base_url . "flows/" . $flowid . "/" . "participants" . "/" . $partid . "/dates";

//                                 $data = <<<DATA
//                                                {
//                                                 "participation": {
//                                                                   "start": $start,
//                                                                   "end": $end
//                                                                  }
//                                                }
//                                         DATA;

//                                 $curlopt = array_replace($curlopt_base,
//                                                          array(
//                                                                CURLOPT_URL => $url,
//                                                                CURLOPT_CUSTOMREQUEST => 'PATCH',
//                                                                CURLOPT_POSTFIELDS => $data,
//                                                               )
//                                                         );

//                                 $curl = curl_init();

//                                 curl_setopt_array($curl, $curlopt);

//                                 $response = curl_exec($curl);
//                                 // $errNo = curl_errno($curl);
//                                 // $err = curl_error($curl);

//                                 $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                
//                             }
                
//                             $result = json_decode($response, true);

//                             curl_close($curl);
//                             unset($response);

//                         }

//                         if ($result['success'] == "true") { $nees++; }

//                     }

//                 }

//             }

//         }

//         if ($nees > 0) {
//             printf("Foram actualizados " . $nees . " estudantes c/ NEEs no WISEflow" . $nl);
    
//         } else {
//             printf("Sem estudantes c/ NEEs p/ actualizar" . $nl);
    
//         }

//     } else {
//         printf("Sem estudantes c/ NEEs p/ actualizar" . $nl);

//     }

@mysqli_close($conBDInt);
