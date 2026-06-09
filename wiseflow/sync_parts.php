<?php
/**
 * manages the participation in WISEflow flows
 * (developed for UAb - Universidade Aberta)
 *
 * @package    sync_parts
 * @category   php_script
 * @author     Bruno Tavares <brunustavares@gmail.com>
 * @link       https://www.linkedin.com/in/brunomastavares/
 * @copyright  Copyright (C) 2023-present Bruno Tavares
 * @license    GNU General Public License v3 or later
 *             https://www.gnu.org/licenses/gpl-3.0.html
 * @version    2025020702
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
 * Valida token de acesso às APIs
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

/**
 * Identifica avaliadores/revisores do flow
 *
 * @return array assessors
 */
function get_subs_assessor($flowid, $role) {
    GLOBAL $base_url, $curlopt_base;

    $httpcode = 0;
    while ($httpcode <> 200) {
        if ($role == "assessors") {
            $url = $base_url . "flow/" . $flowid . "/" . $role;

        } else {
            $url = $base_url . "flows/" . $flowid . "/" . $role;

        }

        $curlopt = array_replace(
                                 $curlopt_base,
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

    $assessors = json_decode($response, true);

    curl_close($curl);
    unset($response);

    return $assessors;

}

/**
 * Atribui submissões aos avaliadores/revisores
 */
function set_subs_assessor($flowid, $roleid, $partid, $role) {
    GLOBAL $base_url, $curlopt_base;

    $httpcode = 0;
    while ($httpcode <> 200) {
        // if ($role == "assessors") {
            $method = 'POST';

        // } else {
        //     $method = 'PUT';

        // }
        
        // $url = $base_url . "flows/" . $flowid . "/" . $role . "/" . $roleid . "/allocations";
        $url = $base_url . "flows/" . $flowid . "/" . $role . "/" . $roleid . "/allocations/participants/" . $partid;

        // $data = <<<DATA
        //                 {
        //                  "participantIds": [
        //                      $partid
        //                  ]
        //                 }
        //            DATA;

        $curlopt = array_replace(
                                 $curlopt_base,
                                 array(
                                       CURLOPT_URL => $url,
                                       CURLOPT_CUSTOMREQUEST => $method,
                                       //    CURLOPT_POSTFIELDS => $data,
                                      )
                                );

        $curl = curl_init();

        curl_setopt_array($curl, $curlopt);

        $response = curl_exec($curl);
        // $errNo = curl_errno($curl);
        // $err = curl_error($curl);

        $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($role == "assessors" && $httpcode == 204) { $httpcode = 200; }

    }

    curl_close($curl);
    unset($response);

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

// registo de estudantes na tabela 'wiseflow.flows_assess'
    $isrtqry = "INSERT IGNORE INTO wiseflow.flows_assess(stdid, flowid)
                SELECT stdid, flowid
                FROM wiseflow.vw_newpart_2flwass;";

    mysqli_query($conBDInt, $isrtqry)
        or die("Ñ foi possível actualizar a tabela 'wiseflow.flows_assess': " . mysqli_error($conBDInt)
                . $nl . $nl
                . $isrtqry);

// levantamento de participantes a remover dos flows
    $slctqry = "SELECT new_rec.partid, new_rec.flowid
                FROM wiseflow.vw_takepart_fromflwass new_rec
                    LEFT JOIN wiseflow.flows_assess old_rec ON (old_rec.stdid = new_rec.stdid AND old_rec.flowid = new_rec.flowid)
                WHERE old_rec.stdid IS NOT NULL
                    AND old_rec.flowid IS NOT NULL
				ORDER BY RAND()
                LIMIT 250;";

    $takeparts = mysqli_query($conBDInt, $slctqry)
                     or die("Ñ foi possível consultar a view 'wiseflow.vw_takepart_fromflwass': " . mysqli_error($conBDInt) . $nl);

    // logging de participantes a remover dos flows
        $isrtqry = "INSERT IGNORE INTO wiseflow.takepart_log
                    SELECT firstname, lastname, std_num, stdid, partid, flowid, subtitle, title, now()
                    FROM wiseflow.vw_takepart_fromflwass
                        WHERE partid IS NOT NULL;";

        mysqli_query($conBDInt, $isrtqry)
            or die("Ñ foi possível actualizar a tabela 'wiseflow.takepart_log': " . mysqli_error($conBDInt) . $nl);

    if (mysqli_num_rows($takeparts) > 0) {
        // remover participantes dos flows
        while ($row = mysqli_fetch_array($takeparts)) {
            $httpcode = 0;
            while ($httpcode <> 200 // participante removido
                && $httpcode <> 403 // flow arquivado ou ativado
                && $httpcode <> 404 // flow ou participante não encontrado
                ) {
                $partid  = $row['partid'];
                $flowid  = $row['flowid'];

                $url = $base_url . "flows/" . $flowid . "/participants" . "/" . $partid;

                $curlopt = array_replace(
                                         $curlopt_base,
                                         array(
                                               CURLOPT_URL => $url,
                                               CURLOPT_CUSTOMREQUEST => 'DELETE'
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

            // actualizar tabela de controlo, retirando participante
            $delqry = "DELETE
                       FROM wiseflow.flows_assess
                       WHERE partid = '" . $partid . "'
                           AND flowid = '" . $flowid . "';";

            mysqli_query($conBDInt, $delqry)
                or die("Ñ foi possível actualizar a tabela 'wiseflow.flows_assess': " . mysqli_error($conBDInt)
                        . $nl . $nl
                        . $delqry);

        }

        printf("Foram removidos " . mysqli_num_rows($takeparts) . " participantes dos flows" . $nl);

    } else {
        printf("Sem participantes p/ remover dos flows" . $nl);

    }

// levantamento de participantes a inscrever nos flows a iniciar dentro de 7 dias
    $slctqry = "SELECT wf_std.stdid, wf_std.std_num, wf_std.email, wf_flw.flowid
                FROM wiseflow.flows_assess wf_ass
                    INNER JOIN wiseflow.flows wf_flw ON wf_flw.flowid = wf_ass.flowid
                    INNER JOIN wiseflow.students wf_std ON wf_std.stdid = wf_ass.stdid
                WHERE wf_flw.dtfrom >= NOW()
                    AND DATE(wf_flw.dtfrom) <= DATE((NOW() + INTERVAL 7 DAY))
                    AND partid IS NULL
				ORDER BY RAND()
                LIMIT 250;";

    $newparts = mysqli_query($conBDInt, $slctqry)
                    or die("Ñ foi possível consultar a tabela 'wiseflow.flows_assess': " . mysqli_error($conBDInt) . $nl);

    if (mysqli_num_rows($newparts) > 0) {
        // adicionar participantes aos flows
        while ($row = mysqli_fetch_array($newparts)) {
            $httpcode = 0;
            while ($httpcode <> 200) {
                $stdid   = $row['stdid'];
                $std_num = $row['std_num'];
                $email   = $row['email'];
                $flowid  = $row['flowid'];

                $url = $base_url . "flow/" . $flowid . "/participants/add";

                $data = <<<DATA
                               [
                                {
                                 "userData": {
                                              "userDataTypeId": 698,
                                              "value": $std_num
                                 },
                                 "email": "$email"
                                }
                               ]
                        DATA;

                $curlopt = array_replace(
                                         $curlopt_base,
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

            $message = json_decode($response, true);

            curl_close($curl);
            unset($response);

        }

        // obter participantes do flow
        $url = $base_url . "flows/" . $flowid . "/participants";

        $flow_parts = [];
        $offset = 0;
        $limit = 100;
        $repeat = true;

        while ($repeat) {
            $offseturl = $url . "?offset=" . (string)$offset . "&limit=" . (string)$limit;

            $httpcode = 0;
            while ($httpcode <> 200) {
                $curlopt = array_replace(
                                         $curlopt_base,
                                         array(
                                               CURLOPT_URL => $offseturl,
                                               CURLOPT_CUSTOMREQUEST => 'GET',
                                              )
                                        );

                $curl = curl_init();

                curl_setopt_array($curl, $curlopt);

                $response = curl_exec($curl);
                // $errNo = curl_errno($curl);
                // $err = curl_error($curl);

                $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

                if ($httpcode == 500) { $httpcode = 200; }
                
            }

            $result = json_decode($response, true)['data'];

            if (!empty($result)) {
                $flow_parts = array_merge($flow_parts, $result);

                if (count($result) < $limit) {
                    $repeat = false;

                } else {
                    $offset += $limit;

                }

            } else {
                $repeat = false;

            }

        }

        curl_close($curl);
        unset($response);

        foreach ($flow_parts as $part) {
            // actualizar tabela de controlo com flowid
            $updtqry = "UPDATE wiseflow.flows_assess
                        SET partid = '" . $part['participantId'] . "',
                            dtreg = \"" . date(FULLDATE) . "\"
                        WHERE stdid = '" . $part['user']['id'] . "'
                            AND flowid = '" . $flowid . "';";

            mysqli_query($conBDInt, $updtqry)
                or die("Ñ foi possível actualizar a tabela 'wiseflow.flows_assess': " . mysqli_error($conBDInt)
                        . $nl . $nl
                        . $updtqry);

        }

        printf("Foram registados " . mysqli_num_rows($newparts) . " participantes nos flows" . $nl);

    } else {
        printf("Sem participantes p/ registar nos flows" . $nl);

    }

// actualização de estudantes c/ NEEs
    // inicializar conexão à PlataformAbERTA
    $mdl_std_eval = connect2mdl('estudantes_NEEs');

    $curl_mdl = curl_init();

    curl_setopt($curl_mdl, CURLOPT_URL, $mdl_std_eval);
    curl_setopt($curl_mdl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl_mdl, CURLOPT_SSL_VERIFYHOST, 0);
    curl_setopt($curl_mdl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl_mdl, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
    
    if (curl_errno($curl_mdl)) { die('cURL error: ' . curl_error($curl_mdl)); }
    
    $std_NEEs = json_decode(curl_exec($curl_mdl), true);

    curl_close($curl_mdl);

    foreach ($std_NEEs as $std) {
        $slctqry = "UPDATE wiseflow.students
                    SET status = " . $std['status'] . ", xtrT = " . $std['xtrt'] .
                  " WHERE std_num = " . $std['stdnum'] . ";";

        mysqli_query($conBDInt, $slctqry)
            or die("Ñ foi possível actualizar a tabela 'wiseflow.students': " . mysqli_error($conBDInt) . $nl);

    }

    $slctqry = "SELECT *
                FROM wiseflow.students
                WHERE xtrT IS NOT NULL
                ORDER BY RAND()
                LIMIT 100;";

    $stdtswnees = mysqli_query($conBDInt, $slctqry)
                      or die("Ñ foi possível consultar a tabela 'wiseflow.students': " . mysqli_error($conBDInt) . $nl);

    if (mysqli_num_rows($stdtswnees) > 0) {
        $nees = 0;

        while ($std = mysqli_fetch_array($stdtswnees)) {
            $stdid  = $std['stdid'];
            $xtrT   = $std['xtrT'] * 60;
            $status = $std['status'];

            // obter lista de flows do estudante
            $slctqry = "SELECT *
                        FROM wiseflow.flows_assess AS flw_ass
                            INNER JOIN wiseflow.flows AS flw ON flw.flowid = flw_ass.flowid
                        WHERE flw_ass.stdid = '" . $stdid . "'
                            AND flw_ass.partid IS NOT NULL
                            AND flw.dtfrom >= NOW();";

            $stdtflows = mysqli_query($conBDInt, $slctqry)
                or die("Ñ foi possível consultar a tabela 'wiseflow.flows_assess': " . mysqli_error($conBDInt) . $nl);

            if (mysqli_num_rows($stdtflows) > 0) {
                // obter datas dos flows
                while ($stdflw = mysqli_fetch_array($stdtflows)) {
                    $httpcode = 0;
                    while ($httpcode <> 200) {
                        $flowid = $stdflw['flowid'];
                        $partid = $stdflw['partid'];

                        $url = $base_url . "flows/" . $flowid . "/dates";

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
        
                    $glbflw_dates = json_decode($response, true);

                    curl_close($curl);
                    unset($response);

                    // obter datas específicas do estudante
                    $httpcode = 0;
                    while ($httpcode <> 200) {
                        $url = $base_url . "flows/" . $flowid . "/" . "participants" . "/" . $partid . "/dates";

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
        
                    $stdflw_dates = json_decode($response, true);

                    curl_close($curl);
                    unset($response);

                    // validar e actualizar datas específicas para o estudante
                    if ($glbflw_dates['data']['participation']['end'] >= time()) {
                        $result['success'] = "false";

                        if ($status == 1
                            && $stdflw_dates['data']['participation']['end'] <> $glbflw_dates['data']['participation']['end'] + $xtrT) {
                            $httpcode = 0;
                            while ($httpcode <> 200) {
                                $start = $stdflw_dates['data']['participation']['start'];
                                $end = $glbflw_dates['data']['participation']['end'] + $xtrT;

                                $url = $base_url . "flows/" . $flowid . "/" . "participants" . "/" . $partid . "/dates";

                                $data = <<<DATA
                                               {
                                                "participation": {
                                                                  "start": $start,
                                                                  "end": $end
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
                
                            $result = json_decode($response, true);

                            curl_close($curl);
                            unset($response);

                        } elseif ($status == 0
                            && $xtrT > 0
                            && $stdflw_dates['data']['participation']['end'] == $glbflw_dates['data']['participation']['end'] + $xtrT) {
                            $httpcode = 0;
                            while ($httpcode <> 200) {
                                $start = $glbflw_dates['data']['participation']['start'];
                                $end = $glbflw_dates['data']['participation']['end'];

                                $url = $base_url . "flows/" . $flowid . "/" . "participants" . "/" . $partid . "/dates";

                                $data = <<<DATA
                                               {
                                                "participation": {
                                                                  "start": $start,
                                                                  "end": $end
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
                
                            $result = json_decode($response, true);

                            curl_close($curl);
                            unset($response);

                        }

                        if ($result['success'] == "true") { $nees++; }

                    }

                }

            }

        }

        if ($nees > 0) {
            printf("Foram actualizados " . $nees . " participantes c/ NEEs nos flows" . $nl);
    
        } else {
            printf("Sem participantes c/ NEEs p/ actualizar" . $nl);
    
        }

    } else {
        printf("Sem participantes c/ NEEs p/ actualizar" . $nl);

    }

// levantamento de notas a migrar do WISEflow p/ a BDInt
    $slctqry = "SELECT flw_ass.id, flw_ass.flowid
                FROM wiseflow.flows_assess AS flw_ass
                    INNER JOIN wiseflow.flows AS flw ON flw.flowid = flw_ass.flowid
                WHERE (flw_ass.dtass IS NOT NULL
                        AND flw_ass.mdl_sync <> 1)
                    AND DATE(flw.dtfrom) >= DATE(NOW() - INTERVAL 45 DAY)
                    AND (status = 0
						OR status IS NULL)
                    AND grade IS NULL
                GROUP BY flw_ass.flowid
                ORDER BY flw.dtfrom;";

    $nogrades = mysqli_query($conBDInt, $slctqry)
                    or die("Ñ foi possível consultar a tabela 'wiseflow.flows_assess': " . mysqli_error($conBDInt) . $nl);

    $n = 0;

    if (mysqli_num_rows($nogrades) > 0) {
        while ($row = mysqli_fetch_array($nogrades)) {
            $httpcode = 0;
            while ($httpcode <> 200) {
                $flowid = $row['flowid'];

                // verificar data termo da avaliação
                $url = $base_url . "flows/" . $flowid . "/dates";

                $curlopt = array_replace(
                                         $curlopt_base,
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

            $flow_dates = json_decode($response, true);

            curl_close($curl);
            unset($response);

            $flow_markend = date(FULLDATE, $flow_dates['data']['marking']['end']);

            if ($flow_markend <= date(FULLDATE)
                && date("Y", strtotime($flow_markend)) <> '1970') { // controlo do erro da data vazia
                // procurar notas lançadas nos flows
                $httpcode = 0;
                while ($httpcode <> 200) {
                    $url = $base_url . "flow/" . $flowid . "/assessments";

                    $curlopt = array_replace(
                                             $curlopt_base,
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

                $flow_assess = json_decode($response, true);

                curl_close($curl);
                unset($response);

                foreach ($flow_assess as $assess) {
                    if (isset($assess['participantId'])
                    && $assess['participantId'] <> '') {
                        // actualizar tabela de controlo com nota e data
                        $updtqry = "UPDATE wiseflow.flows_assess
                                    SET grade = '" . $assess['grade'] . "',
                                        dtgrd = '" . date(FULLDATE, $assess['date']) . "'
                                    WHERE flowid = '" . $flowid . "'
                                        AND partid = '" . $assess['participantId'] . "'
                                        AND mdl_sync <> 1;";
            
                        mysqli_query($conBDInt, $updtqry)
                            or die("Ñ foi possível actualizar a tabela 'wiseflow.flows_assess': " . mysqli_error($conBDInt) . $nl . 
                                "query: " . $updtqry);
                        
                        $n += mysqli_affected_rows($conBDInt);

                    }

                }

            }

        }

        if ($n > 0) {
            printf("Foram migradas " . $n . " notas" . $nl);

        } else {
            printf("Sem notas p/ migrar" . $nl);

        }

    } else {
        printf("Sem notas p/ migrar" . $nl);

    }

// levantamento dos flows realizados nos últimos 3 dias, p/ atribuição de submissões aos avaliadores
    $slctqry = "SELECT flowid, subtitle
                FROM wiseflow.flows
                WHERE (DATE(dtfrom) >= DATE(NOW() - INTERVAL 3 DAY)
					AND DATE(dtto) <= DATE(NOW() - INTERVAL 1 DAY))
                    AND to_eval <> 1
                ORDER BY RAND()
                LIMIT 5;";

    $doneflows = mysqli_query($conBDInt, $slctqry)
                     or die("Ñ foi possível consultar a tabela 'wiseflow.flows': " . mysqli_error($conBDInt) . $nl);

    $subs_sum = 0;

    if (mysqli_num_rows($doneflows) > 0) {
        while ($row = mysqli_fetch_array($doneflows)) {
            // levantamento das submissões dos respectivos participantes
            $slctqry = "SELECT flowid, partid
                        FROM wiseflow.flows_assess
                        WHERE flowid = '" . $row['flowid'] . "'
                            AND (dtass IS NOT NULL
                                AND dtass <> '')
                        ORDER BY flowid, partid;";

            $result = mysqli_query($conBDInt, $slctqry)
                          or die("Ñ foi possível consultar a tabela 'wiseflow.flows_assess': " . mysqli_error($conBDInt) . $nl);

            $subs = array();

            if (mysqli_num_rows($result) > 0) {
                $subs_sum += mysqli_num_rows($result);
                $i = 0;

                while ($rec = mysqli_fetch_array($result)) {
                    $subs[$i]['flowid'] = $rec['flowid'];
                    $subs[$i]['partid'] = $rec['partid'];
                    $i++;

                }

                // levantamento dos respectivos docentes/revisores e turmas no Moodle
                    $slctqry = "SELECT *
                                FROM wiseflow.vw_reviewer_2part
                                WHERE flowid = '" . $row['flowid'] . "'
                                ORDER BY mdl_cls, partid;";

                    $result = mysqli_query($conBDInt, $slctqry)
                                  or die("Ñ foi possível consultar a view 'wiseflow.vw_reviewer_2part': " . mysqli_error($conBDInt) . $nl);

                    $docs = array();
                    $i = 0;

                    while ($rec = mysqli_fetch_array($result)) {
                        $docs[$i]['partid'] = $rec['partid'];
                        $docs[$i]['flowid'] = $rec['flowid'];
                        $docs[$i]['mdl_cls'] = $rec['mdl_cls'];
                        $docs[$i]['docid'] = $rec['userid'];
                        $i++;

                    }

                    $reviewers = get_subs_assessor($row['flowid'], "reviewers");

                    if (empty($reviewers)) {
                        die("erro: sem revisores p/ o flow " . $row['flowid'] . $nl);
                    
                    }

                    foreach ($reviewers['data'] as $reviewer) {
                        foreach ($docs as &$doc) {
                            if ($reviewer['user']['userId'] == $doc['docid']) {
                                $doc['revid'] = $reviewer['reviewerId'];
                            
                            }

                        }

                        unset($doc);

                    }

                // levantamento dos respectivos tutores e turmas afectas no Moodle
                    $slctqry = "SELECT *
                                FROM wiseflow.vw_assessor_2part
                                WHERE flowid = '" . $row['flowid'] . "'
                                ORDER BY mdl_cls, partid;";

                    $result = mysqli_query($conBDInt, $slctqry)
                                  or die("Ñ foi possível consultar a view 'wiseflow.vw_assessor_2part': " . mysqli_error($conBDInt) . $nl);

                    if (mysqli_num_rows($result) > 0) {
                        $tuts = array();
                        $i = 0;

                        while ($rec = mysqli_fetch_array($result)) {
                            $tuts[$i]['partid'] = $rec['partid'];
                            $tuts[$i]['flowid'] = $rec['flowid'];
                            $tuts[$i]['mdl_cls'] = $rec['mdl_cls'];
                            $tuts[$i]['tutid'] = $rec['userid'];
                            $i++;

                        }

                    }

                $assessors = get_subs_assessor($row['flowid'], "assessors");

                if (empty($assessors)) {
                    die("erro: sem avaliadores p/ o flow " . $row['flowid'] . $nl);
                
                }

                foreach ($assessors as $assessor) {
                    if (!empty($tuts)) {
                        foreach ($tuts as &$tut) {
                            if ($tut['tutid'] == $assessor['user']['id']) {
                                $tut['asseid'] = $assessor['id'];
                            
                            }

                        }

                        unset($tut);

                    }

                    foreach ($docs as &$doc) {
                        if ($doc['docid'] == $assessor['user']['id']) {
                            $doc['asseid'] = $assessor['id'];
                        
                        }

                    }

                    unset($doc);

                }

                foreach ($subs as &$sub) {
                    if (!empty($tuts)) {
                        foreach ($tuts as $tut) {
                            if ($sub['partid'] == $tut['partid']
                            && $sub['flowid'] == $tut['flowid']) {
                                set_subs_assessor($sub['flowid'], $tut['asseid'], $sub['partid'], "assessors");
                                $sub['asseid'] = $tut['asseid'];

                            }

                        }
                        
                        unset($tut);

                    }

                    foreach ($docs as $doc) {
                        if ($sub['partid'] == $doc['partid']
                        && $sub['flowid'] == $doc['flowid']) {
                            if (isset($doc['asseid'])
                            && !isset($sub['asseid'])) {
                                set_subs_assessor($sub['flowid'], $doc['asseid'], $sub['partid'], "assessors");
                                $sub['asseid'] = $doc['asseid'];

                            }

                            if (isset($doc['revid'])) {
                                set_subs_assessor($sub['flowid'], $doc['revid'], $sub['partid'], "reviewers");
                                $sub['revid'] = $doc['revid'];

                            }

                        }
                        
                    }

                    unset($doc);

                }

                $updtqry = "UPDATE wiseflow.flows
                            SET to_eval = 1
                            WHERE flowid = '" . $row['flowid'] . "';";

                $result = mysqli_query($conBDInt, $updtqry)
                              or die("Ñ foi possível actualizar a tabela 'wiseflow.flows': " . mysqli_error($conBDInt) . $nl);

            }

        }

        if ($subs_sum > 0) {
            printf("Foram atribuídas " . $subs_sum . " submissões aos respectivos avaliadores/revisores" . $nl);

        } else {
            printf("Sem submissões p/ atribuir aos avaliadores/revisores" . $nl);

        }

    } else {
        printf("Sem flows realizados p/ processar" . $nl);

    }

@mysqli_close($conBDInt);
