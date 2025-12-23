<?php
/**
 * manages users in WISEflow
 * (developed for UAb - Universidade Aberta)
 *
 * @package    sync_users
 * @category   php_script
 * @author     Bruno Tavares <brunustavares@gmail.com>
 * @link       https://www.linkedin.com/in/brunomastavares/
 * @copyright  Copyright (C) 2022-present Bruno Tavares
 * @license    GNU General Public License v3 or later
 *             https://www.gnu.org/licenses/gpl-3.0.html
 * @version    2025122308
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

// levantamento de docentes a actualizar
    $slctqry = "SELECT userid, username, email
                FROM wiseflow.vw_teacher_2wiseflow
                WHERE RIGHT(lectyear, 2) = CASE
                                               WHEN MONTH(CURDATE()) < 10
                                                   THEN RIGHT(YEAR(CURDATE()), 2)
                                               ELSE RIGHT(YEAR(CURDATE()) + 1, 2)
                                           END
                    AND userid IS NOT NULL
                GROUP BY username
                ORDER BY username;";

    $docs2updt = mysqli_query($conBDInt, $slctqry)
                     or die("Ñ foi possível consultar a view 'wiseflow.vw_teacher_2wiseflow': " . mysqli_error($conBDInt) . $nl);

    if (mysqli_num_rows($docs2updt) > 0) {
        // actualizar perfil dos docentes
        while ($row = mysqli_fetch_array($docs2updt)) {
            $httpcode = 0;
            while ($httpcode <> 201) {
                $userid     = $row['userid'];
                // $username = $row['username'];
                // $email  = $row['email'];

                $url = $base_url . "users/" . $userid . "/roles";

                $data = <<<DATA
                               [
                                {
                                 "licenseRoleId": 5726
                                },
                                {
                                 "licenseRoleId": 5727
                                },
                                {
                                 "licenseRoleId": 5731
                                },
                                {
                                 "licenseRoleId": 5732
                                }
                               ]
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

            }

            curl_close($curl);
            unset($response);

        }

        printf("Foram actualizados " . mysqli_num_rows($docs2updt) . " docentes no WISEflow" . $nl);

    }

// levantamento de tutores a actualizar
    $slctqry = "SELECT userid, username, email
                FROM wiseflow.vw_tutor_2wiseflow
                WHERE RIGHT(lectyear, 2) = CASE
                                               WHEN MONTH(CURDATE()) < 10
                                                   THEN RIGHT(YEAR(CURDATE()), 2)
                                               ELSE RIGHT(YEAR(CURDATE()) + 1, 2)
                                           END
                    AND userid IS NOT NULL
                GROUP BY username
                ORDER BY username;";

    $tuts2updt = mysqli_query($conBDInt, $slctqry)
                     or die("Ñ foi possível consultar a view 'wiseflow.vw_tutor_2wiseflow': " . mysqli_error($conBDInt) . $nl);

    if (mysqli_num_rows($tuts2updt) > 0) {
        // actualizar perfil dos tutores
        while ($row = mysqli_fetch_array($tuts2updt)) {
            $httpcode = 0;
            while ($httpcode <> 201) {
                $userid     = $row['userid'];
                // $username = $row['username'];
                // $email  = $row['email'];

                $url = $base_url . "users/" . $userid . "/roles";

                $data = <<<DATA
                               [
                                {
                                 "licenseRoleId": 5726
                                },
                                {
                                 "licenseRoleId": 5727
                                }
                               ]
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

            }

            curl_close($curl);
            unset($response);

        }

        printf("Foram actualizados " . mysqli_num_rows($tuts2updt) . " tutores no WISEflow" . $nl);

    }

@mysqli_close($conBDInt);
