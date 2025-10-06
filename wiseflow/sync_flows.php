<?php
/**
 * sets up WISEflow flows
 * (developed for UAb - Universidade Aberta)
 *
 * @package    sync_flows
 * @category   php_script
 * @author     Bruno Tavares <brunustavares@gmail.com>
 * @link       https://www.linkedin.com/in/brunomastavares/
 * @copyright  Copyright (C) 2023-2025 Bruno Tavares
 * @license    GNU General Public License v3 or later
 *             https://www.gnu.org/licenses/gpl-3.0.html
 * @version    2025060504
 * @date       2023-01-05
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

// define('FULLDATE', "Y-m-d H:i:s");

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

// levantamento de novos flows, para criação
    $slctqry = "SELECT *
                FROM wiseflow.vw_newflw_2wiseflow
                ORDER BY dtfrom ASC, subtitle ASC
                LIMIT 25;";

    $newflows = mysqli_query($conBDInt, $slctqry)
                    or die("Ñ foi possível consultar a view 'wiseflow.vw_newflw_2wiseflow': " . mysqli_error($conBDInt) . $nl);

    if (mysqli_num_rows($newflows) > 0) {
        // criar novos flows
        while ($row = mysqli_fetch_array($newflows)) {
            $httpcode = 0;
            while ($httpcode <> 201) {
                // $url = $base_url . "license/create/flow";

                // $title    = $row['title'];
                // $subtitle = $row['subtitle'];
                // $type     = $row['type'];
                // $managers = $row['managers'];

                // $data = <<<DATA
                //                 {
                //                  "title": "$title",
                //                  "subTitle": "$subtitle",
                //                  "type": $type,
                //                  "managers": [
                //                               $managers
                //                              ]
                //                 }
                //            DATA;

                $url = $base_url . "flows/" . $row['sourceid'] . "/copy";

                $managers                   = $row['managers'];
                $title                      = $row['title'];
                $subtitle                   = $row['subtitle'];
                $settings                   = ($row['settings'] == 1) ? "true" : "false";
                $dates                      = ($row['dates'] == 1) ? "true" : "false";
                $coversheet                 = ($row['coversheet'] == 1) ? "true" : "false";
                $assignments                = ($row['assignments'] == 1) ? "true" : "false";
                $additionalMarkingMaterial  = ($row['additionalMarkingMaterial'] == 1) ? "true" : "false";
                $rubrics                    = ($row['rubrics'] == 1) ? "true" : "false";
                $permittedInternetResources = ($row['permittedInternetResources'] == 1) ? "true" : "false";

                $data = <<<DATA
                            {
                             "managerUserIds": [
                                                $managers
                                               ],
                             "title": "$title",
                             "subtitle": "$subtitle",
                             "configuration": {
                                               "settings": $settings,
                                               "dates": $dates,
                                               "passwords": false,
                                               "coversheet": $coversheet,
                                               "assignments": $assignments,
                                               "additionalMarkingMaterial": $additionalMarkingMaterial,
                                               "rubrics": $rubrics,
                                               "permittedInternetResources": $permittedInternetResources
                                              }
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

            }

            // $flow = json_decode($response, true);
            $flow = json_decode($response, true)['data'];

            curl_close($curl);
            unset($response);

            // configurar escala de avaliação do novo flow
            $httpcode = 0;
            while ($httpcode <> 200) {
                $url = $base_url . "flows/" . $flow['flowId'] . "/grading-scale";

                $scaleid = $row['scaleid'];

                $data = <<<DATA
                               {
                                "gradingScaleId": $scaleid
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

            // $httpcode = 0;
            // while ($httpcode <> 201) {
            //     // atribuir autor ao novo flow
            //     $url = $base_url . "flows/" . $flow['flowId'] . "/authors";

            //     $author = $row['author'];

            //     $data = <<<DATA
            //                    {
            //                     "userId": $author
            //                    }
            //             DATA;

            //     $curlopt = array_replace($curlopt_base,
            //                              array(
            //                                    CURLOPT_URL => $url,
            //                                    CURLOPT_CUSTOMREQUEST => 'POST',
            //                                    CURLOPT_POSTFIELDS => $data,
            //                                   )
            //                             );

            //     $curl = curl_init();

            //     curl_setopt_array($curl, $curlopt);

            //     $response = curl_exec($curl);
            //     // $errNo = curl_errno($curl);
            //     // $err = curl_error($curl);

            //     $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            // }

            // curl_close($curl);
            // unset($response);

            // atribuir avaliadores ao novo flow
            $url = $base_url . "flows/" . $flow['flowId'] . "/assessors";

            $assessors = explode(";", $row['assessors']);

            foreach ($assessors as $assessor) {
                $httpcode = 0;
                while ($httpcode <> 201) {
                    $data = <<<DATA
                                   {
                                    "userData": {
                                                 "userDataTypeId": 696,
                                                 "value": "$assessor"
                                    },
                                    "assessorTypeId": 170
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

                    if ($httpcode == 403) { $httpcode = 201; }

                }

                curl_close($curl);
                unset($response);

            }

            // atribuir autores e revisores ao novo flow
            $url_authors = $base_url . "flows/" . $flow['flowId'] . "/authors";
            $url_reviewers = $base_url . "flows/" . $flow['flowId'] . "/reviewers";

            $reviewers = explode(";", $row['reviewers']);

            foreach ($reviewers as $reviewer) {
                $httpcode = 0;
                while ($httpcode <> 201) {
                    $data = <<<DATA
                                {
                                    "userId": $reviewer
                                }
                            DATA;

                    $curlopt = array_replace($curlopt_base,
                                            array(
                                                CURLOPT_URL => $url_authors,
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

                $httpcode = 0;
                while ($httpcode <> 201) {
                    $data = <<<DATA
                                {
                                 "userId": $reviewer,
                                 "isAllocatedToAll": false,
                                 "hasAccessToAssessmentInformation": true,
                                 "canDecideOnFinalGrade": true
                                }
                            DATA;

                    $curlopt = array_replace($curlopt_base,
                                             array(
                                                   CURLOPT_URL => $url_reviewers,
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

                    if ($httpcode == 403) { $httpcode = 201; }

                }

                curl_close($curl);
                unset($response);

            }

            // actualizar tabela de controlo com flowid
            $updtqry = "UPDATE wiseflow.flows
                        SET flowid = '" . $flow['flowId'] . "'
                        WHERE subtitle = '" . $subtitle . "';";

            mysqli_query($conBDInt, $updtqry)
                or die("Ñ foi possível actualizar a tabela 'wiseflow.flows': " . mysqli_error($conBDInt) . $nl);

        }

        printf("Foram criados " . mysqli_num_rows($newflows) . " novos flows no WISEflow" . $nl);

    } else {
        printf("Sem novos flows p/ criar" . $nl);

    }

// levantamento de flows por realizar, para configuração/rectificação de datas
    $slctqry = "SELECT *
                FROM wiseflow.flows
                WHERE dtfrom >= NOW();";

    $flows2be = mysqli_query($conBDInt, $slctqry)
                    or die("Ñ foi possível consultar a tabela 'wiseflow.flows': " . mysqli_error($conBDInt) . $nl);

    if (mysqli_num_rows($flows2be) > 0) {
        $i = 0;

        // configurar datas dos flows
        while ($row = mysqli_fetch_array($flows2be)) {
            $httpcode = 0;
            // while ($httpcode <> 200) {
                $url = $base_url . "flows/" . $row['flowid'] . "/dates";

                $dtfrom  = strtotime($row['dtfrom']);
                $dtto    = strtotime($row['dtto']);

                $dayOfWeek = date('N', $dtfrom);
                if ($dayOfWeek <= 2) { // 1 = Segunda: aval. inicia 5ª; 2 = Terça: aval. inicia 6ª
                    $startEvalDays = 3;

                } else { // 3 = Quarta: aval. inicia 2ª; 4 = Quinta: aval. inicia 3ª; 5 = Sexta: aval. inicia 4ª
                    $startEvalDays = 5;

                }

                $stopEvalDays = 30 + (int)$startEvalDays;

                $evalstart = strtotime(date('Y-m-d 00:00:00', strtotime($row['dtto'] . ' + ' . $startEvalDays . ' days')));
                $evalstop = strtotime(date('Y-m-d 00:00:00', strtotime($row['dtto'] . ' + ' . $stopEvalDays . ' days')));

                $curlopt = array_replace($curlopt_base,
                                         array(
                                               CURLOPT_URL => $url,
                                               CURLOPT_CUSTOMREQUEST => 'GET',
                                              )
                                        );

                $curl = curl_init();

                curl_setopt_array($curl, $curlopt);
                
                $response = curl_exec($curl);
                // $errNo = curl_errno($curl);
                // $err = curl_error($curl);

                $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            // }

            $flow = json_decode($response, true);

            curl_close($curl);
            unset($response);

            $httpcode = 0;
            if (($flow['data']['participation']['start'] <> $dtfrom)
                || ($flow['data']['participation']['end'] <> $dtto)
                || ($flow['data']['marking']['start'] <> $evalstart)
                || ($flow['data']['marking']['end'] <> $evalstop)) {
                while ($httpcode <> 200) {
                    $data = <<<DATA
                                   {
                                    "participation": {
                                                      "start": $dtfrom,
                                                      "end": $dtto
                                                     },
                                    "marking": {
                                                "start": $evalstart,
                                                "end": $evalstop
                                               }
                                   }
                            DATA;

                    $curlopt = array_replace($curlopt_base,
                                             array(
                                                   CURLOPT_URL => $url,
                                                   CURLOPT_CUSTOMREQUEST => 'PATCH',
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

                $i++;

            }

        }

        if ($i > 0) {
            printf("Foram corrigidas as datas em " . $i . " flows" . $nl);

        } else {
            printf("Sem flows c/ datas p/ corrigir" . $nl);

        }

    }

// levantamento de flows por realizar nos próximos 21 dias
    $slctqry = "SELECT subtitle,
                       title,
                       flowid,
                       assign
                FROM wiseflow.flows
                WHERE (DATE(dtfrom) >= DATE(NOW())
                    AND DATE(dtfrom) <= DATE(NOW() + INTERVAL 21 DAY))
                ORDER BY dtfrom ASC;";

    $flwnoasg = mysqli_query($conBDInt, $slctqry)
                    or die("Ñ foi possível consultar a tabela 'wiseflow.flows': " . mysqli_error($conBDInt) . $nl);

    $n = 0;
    $m = 0;

    if (mysqli_num_rows($flwnoasg) > 0) {
        // verificar se há tarefa associada
        while ($row = mysqli_fetch_array($flwnoasg)) {
            $httpcode = 0;
            while ($httpcode <> 200) {
                $url = $base_url . "flow/" . $row['flowid'] . "/assignments";

                $curlopt = array_replace($curlopt_base,
                                         array(
                                              CURLOPT_URL => $url,
                                              CURLOPT_CUSTOMREQUEST => 'GET',
                                             )
                                        );

                $curl = curl_init();

                curl_setopt_array($curl, $curlopt);

                $response = curl_exec($curl);
                // $errNo = curl_errno($curl);
                // $err = curl_error($curl);

                $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            }

            $result = json_decode($response, true);

            curl_close($curl);
            unset($response);

            // actualizar tabela de controlo do flow
            if (!empty($result)
                && $row['assign'] == 0) {
                $updtqry = "UPDATE wiseflow.flows
                            SET assign = '1'
                            WHERE flowid = '" . $row['flowid'] . "';";

                mysqli_query($conBDInt, $updtqry)
                    or die("Ñ foi possível actualizar a tabela 'wiseflow.flows': " . mysqli_error($conBDInt) . $nl);

                $n++;

            } elseif (empty($result)
                && $row['assign'] == 1) {
                $updtqry = "UPDATE wiseflow.flows
                            SET assign = '0'
                            WHERE flowid = '" . $row['flowid'] . "';";

                mysqli_query($conBDInt, $updtqry)
                    or die("Ñ foi possível actualizar a tabela 'wiseflow.flows': " . mysqli_error($conBDInt) . $nl);

                $m++;

            }

        }

    }

    if (($n - $m) > 0) {
        printf("Foram confirmadas " . ($n - $m) . " novas tarefas associadas aos flows" . $nl);

    } elseif (($n - $m) < 0) {
        printf("Foram anuladas " . ($n - $m) . " tarefas associadas aos flows" . $nl);

    } else {
        printf("Sem novas tarefas associadas" . $nl);

    }

// levantamento de flows por realizar nos próximos 7 dias, com autor ainda associado
    $slctqry = "SELECT subtitle,
                       title,
                       flowid
                FROM wiseflow.flows
                WHERE (DATE(dtfrom) >= DATE(NOW())
                    AND DATE(dtfrom) <= DATE(NOW() + INTERVAL 7 DAY))
                    AND assign = 1
                    AND author = 1
                ORDER BY dtfrom ASC;";

    $flows2be = mysqli_query($conBDInt, $slctqry)
                    or die("Ñ foi possível consultar a tabela 'wiseflow.flows': " . mysqli_error($conBDInt) . $nl);

    $n = 0;

    if (mysqli_num_rows($flows2be) > 0) {
        // obter autor(es)
        while ($row = mysqli_fetch_array($flows2be)) {
            $httpcode = 0;
            while ($httpcode <> 200) {
                $url = $base_url . "flows/" . $row['flowid'] . "/authors";

                $curlopt = array_replace($curlopt_base,
                                         array(
                                              CURLOPT_URL => $url,
                                              CURLOPT_CUSTOMREQUEST => 'GET',
                                             )
                                        );

                $curl = curl_init();

                curl_setopt_array($curl, $curlopt);

                $response = curl_exec($curl);
                // $errNo = curl_errno($curl);
                // $err = curl_error($curl);

                $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            }

            $authors = json_decode($response, true);

            curl_close($curl);
            unset($response);

            if (!empty($authors)) {
                // remover autor(es)
                foreach ($authors['data'] as $author) {
                    $author_url = $url . "/" . $author['authorId'];

                    $httpcode = 0;
                    while ($httpcode <> 200) {
                        $curlopt = array_replace($curlopt_base,
                                                 array(
                                                      CURLOPT_URL => $author_url,
                                                      CURLOPT_CUSTOMREQUEST => 'DELETE',
                                                     )
                                                );

                        $curl = curl_init();

                        curl_setopt_array($curl, $curlopt);

                        $response = curl_exec($curl);
                        // $errNo = curl_errno($curl);
                        // $err = curl_error($curl);

                        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

                    }

                    $n++;

                    curl_close($curl);
                    unset($response);

                    // actualizar tabela de controlo do flow
                    $updtqry = "UPDATE wiseflow.flows
                                SET author = '0'
                                WHERE flowid = '" . $row['flowid'] . "';";

                    mysqli_query($conBDInt, $updtqry)
                        or die("Ñ foi possível actualizar a tabela 'wiseflow.flows': " . mysqli_error($conBDInt) . $nl);

                }

            }

        }

    }

    if ($n > 0) {
        printf("Foram removidos " . $n . " autores dos flows" . $nl);

    } else {
        printf("Sem autores p/ remover" . $nl);

    }

// activação de flows
    $slctqry = "SELECT *
                FROM wiseflow.flows
                WHERE dtfrom >= NOW()
                    AND DATE(dtfrom) <= DATE((NOW() + INTERVAL 2 DAY));";

    $flw2actvt = mysqli_query($conBDInt, $slctqry)
                     or die("Ñ foi possível consultar a tabela 'wiseflow.flows': " . mysqli_error($conBDInt) . $nl);

    $n = 0;

    if (mysqli_num_rows($flw2actvt) > 0) {
        // activar flows
        while ($row = mysqli_fetch_array($flw2actvt)) {
            $httpcode = 0;
            while ($httpcode <> 200) {
                $url = $base_url . "flows/" . $row['flowid'] . "/activate";

                $curlopt = array_replace($curlopt_base,
                                         array(
                                               CURLOPT_URL => $url,
                                               CURLOPT_CUSTOMREQUEST => 'PATCH',
                                              )
                                        );

                $curl = curl_init();

                curl_setopt_array($curl, $curlopt);
                
                $response = curl_exec($curl);
                // $errNo = curl_errno($curl);
                // $err = curl_error($curl);

                $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

                if ($httpcode == 403) { $httpcode = 200; }

            }

            $result = json_decode($response, true);

            if ($result['success'] == "true") { $n++; }

            curl_close($curl);
            unset($response);

        }

    }

    if ($n > 0) {
        printf("Foram activados " . mysqli_num_rows($flw2actvt) . " flows" . $nl);

    } else {
        printf("Sem flows p/ activar" . $nl);

    }

@mysqli_close($conBDInt);
