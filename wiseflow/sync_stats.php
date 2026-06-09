<?php
/**
 * collects activity stats from WISEflow flows
 * (developed for UAb - Universidade Aberta)
 *
 * @package    sync_stats
 * @category   php_script
 * @author     Bruno Tavares <brunustavares@gmail.com>
 * @link       https://www.linkedin.com/in/brunomastavares/
 * @copyright  Copyright (C) 2023-present Bruno Tavares
 * @license    GNU General Public License v3 or later
 *             https://www.gnu.org/licenses/gpl-3.0.html
 * @version    2023122002
 * @date       2023-01-26
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

ini_set('memory_limit', '-1');

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

    // levantamento de flows com logs a pesquisar
    $qrylogs = "SELECT wf_flw.flowid, 
                       CAST(UNIX_TIMESTAMP(wf_flw.dtfrom) AS SIGNED) AS 'offset', 
                       CAST(UNIX_TIMESTAMP(wf_flw.dtto) AS SIGNED) AS 'limit'
                FROM wiseflow.flows wf_flw
                WHERE wf_flw.dtto < NOW()
				    AND wf_flw.log = 0
                ORDER BY RAND()
                LIMIT 5;";

    $flwlogs = mysqli_query($conBDInt, $qrylogs)
                   or die("Ñ foi possível consultar a tabela 'wiseflow.flows': " . mysqli_error($conBDInt) . $nl);

    $bio = 0;

    if (mysqli_num_rows($flwlogs) > 0) {
        // consultar logs para cada flow
        $log_arr = array();
        
        $i = 0;
        $j = 0;

        while ($row = mysqli_fetch_array($flwlogs)) {
            $flowid  = $row['flowid'];

            // $offset  = $row['offset'];
            // $limit  = $row['limit'];

            // $url = $base_url . "flows/" . $flowid . "/logs?offset=" . $offset . "&limit=10000&type=participant";

            // $curlopt = array_replace(
            //                          $curlopt_base,
            //                          array(
            //                                CURLOPT_URL => $url,
            //                                CURLOPT_CUSTOMREQUEST => 'GET',
            //                               )
            //                         );

            // $curl = curl_init();

            // curl_setopt_array($curl, $curlopt);

            // $response = curl_exec($curl);
            // // $errNo = curl_errno($curl);
            // // $err = curl_error($curl);

            // $httpcode = 0;
            // while ($httpcode <> 200) {
            //     $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

            // }

            // $log = json_decode($response, true);

            // curl_close($curl);
            // unset($response);

            // // debug do log integral
            // echo '<pre>';
            // print_r($log);
            // die();

            // foreach ($log['data'] as $std_action) {
            //     $src_ctxt = $std_action['_source']['context'];
            //     $msg_ctxt = $src_ctxt['message']['context'];

            //     if (isset($msg_ctxt['userId'])
            //         && isset($msg_ctxt['localizationMessage'])) {

            //         $stdid = $msg_ctxt['userId'];

            //         // eliminar levantamento de submissões por este método
            //         if ($msg_ctxt['localizationMessage'] == "LPaperHanded-in") { // caso se trate de submissão confirmada
            //             if (!isset($log_arr[$flowid][$stdid]['handin'])) {
            //                 $log_arr[$flowid][$stdid]['handin'] = date(FULLDATE, $src_ctxt['timestamp']);
            //                 $log_arr[$flowid][$stdid]['out'] = $log_arr[$flowid][$stdid]['handin'];
                            
            //                 $i++;

            //             } elseif ($log_arr[$flowid][$stdid]['handin'] < date(FULLDATE, $src_ctxt['timestamp'])) {
            //                 $log_arr[$flowid][$stdid]['handin'] = date(FULLDATE, $src_ctxt['timestamp']);
            //                 $log_arr[$flowid][$stdid]['out'] = $log_arr[$flowid][$stdid]['handin'];

            //             }

            //         } elseif ($msg_ctxt['localizationMessage'] == "LUploadCompleted") { // caso haja submissão de ficheiro
            //             if (!isset($log_arr[$flowid][$stdid]['filename'])
            //                 && (!isset($log_arr[$flowid][$stdid]['filedate'])
            //                 || $log_arr[$flowid][$stdid]['filedate'] < date(FULLDATE, $src_ctxt['timestamp']))) {

            //                 $log_arr[$flowid][$stdid]['filename'] = substr($msg_ctxt['parameterBag'],
            //                                                                strpos($msg_ctxt['parameterBag'], "fileName") + 11,
            //                                                                strpos($msg_ctxt['parameterBag'], "fileSize") - (strpos($msg_ctxt['parameterBag'], "fileName")) - 14);

            //                 $log_arr[$flowid][$stdid]['filedate'] = date(FULLDATE, $src_ctxt['timestamp']);
                            
            //             }

            //         } elseif ($msg_ctxt['localizationMessage'] == "LPaperWithdrawn") { // caso haja remoção de ficheiro
            //             if (isset($log_arr[$flowid][$stdid]['handin'])
            //                 && $log_arr[$flowid][$stdid]['handin'] < date(FULLDATE, $src_ctxt['timestamp'])) {
            //                 $log_arr[$flowid][$stdid]['handin'] = null;
            //                 $log_arr[$flowid][$stdid]['filename'] = null;
            //                 $log_arr[$flowid][$stdid]['filedate'] = null;

            //                 $i--;

            //             }

            //         } else {

            //             $earlystart = 1800; // início antecipado até 30min
            //             $latestart = 300; // início postecipado até 5min
            //             $latestop = 3600; // fim postecipado até 60min
                        
            //             if ($src_ctxt['timestamp'] >= $offset - $earlystart
            //                 && $src_ctxt['timestamp'] <= $limit + $latestop) {
            //                 if (!isset($log_arr[$flowid][$stdid]['in'])) {
            //                     $log_arr[$flowid][$stdid]['in'] = date(FULLDATE, $src_ctxt['timestamp']);

            //                     $j++;

            //                 } elseif ($log_arr[$flowid][$stdid]['in'] < date(FULLDATE, $src_ctxt['timestamp'])
            //                     && $src_ctxt['timestamp'] < ($offset + $latestart)) {
            //                     $log_arr[$flowid][$stdid]['in'] = date(FULLDATE, $src_ctxt['timestamp']);

            //                 } elseif (!isset($log_arr[$flowid][$stdid]['out'])
            //                     || $log_arr[$flowid][$stdid]['out'] < date(FULLDATE, $src_ctxt['timestamp'])
            //                     && $src_ctxt['timestamp'] < ($limit + $latestop)) {
            //                     $log_arr[$flowid][$stdid]['out'] = date(FULLDATE, $src_ctxt['timestamp']);

            //                 }

            //             }

            //         }

            //     }

            // }

            // // debug do log filtrado
            // // echo '<pre>';
            // // print_r($log_arr);
            // // die();

            // // gerar as múltiplas queries, para registar os valores na base de dados

            // foreach ($log_arr[$flowid] as $stdid=>$data) {
            //     $in  = (isset($data['in'])) ? ("\"" . $data['in'] . "\"") : "NULL";
            //     $out = (isset($data['out'])) ? ("\"" . $data['out'] . "\"") : "NULL";

            //     if (isset($data['handin'])
            //         && $data['handin'] <> null) {
            //         $handin   = "\"" . $data['handin'] . "\"";
            //         $filename = "\"" . $data['filename'] . "\"";

            //     } else {
            //         $handin   = "NULL";
            //         $filename = "NULL";

            //     }

            //     $updtqry = "UPDATE wiseflow.flows_assess
            //                 SET dtin = " . $in . ",
            //                     dtout = " . $out . ",
            //                     filename = " . $filename . "
            //                 WHERE flowid = '" . $flowid . "'
            //                     AND stdid = '" . $stdid . "';";

            //     mysqli_query($conBDInt, $updtqry)
            //         or die("Ñ foi possível actualizar a tabela 'wiseflow.flows_assess': " . mysqli_error($conBDInt) . $nl);

            // }

            // levantamento de participações
            $url = $base_url . "flows/" . $flowid . "/participants";

            $flowpart = [];
            $offset = 0;
            $limit = 100;
            $repeat = true;

            while ($repeat) {
                $offseturl = $url . "?offset=" . (string)$offset . "&limit=" . (string)$limit;

                $httpcode = 0;
                while ($httpcode <> 200) {
                    $curlopt = array_replace(
                                            set_curl_params(date(time())),
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
                    $flowpart = array_merge($flowpart, $result);

                    if (count($result) >= $limit) {
                        $offset += $limit;

                    } else {
                        $repeat = false;

                    }

                } else {
                    $repeat = false;

                }

            }

            curl_close($curl);
            unset($response);

            // levantamento de submissões
            $httpcode = 0;
            while ($httpcode <> 200) {
                $url = $base_url . "flow/" . $flowid . "/submissions";

                $curlopt = array_replace(
                                         set_curl_params(date(time())),
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

            $flowdata = json_decode($response, true);

            curl_close($curl);
            unset($response);

            foreach ($flowpart as $flw_std) {
                foreach ($flowdata as $flw_sub) {
                    if ($flw_sub['handedIn'] == "1"
                        && $flw_sub['id'] == $flw_std['submissionId']) {
        
                        $updtqry = "UPDATE wiseflow.flows_assess
                                    SET dtass = from_unixtime(" . $flw_sub['handedInDate'] . ")
                                    WHERE flowid = '" . $flowid . "'
                                        AND partid = '" . $flw_std['participantId'] . "';";

                        mysqli_query($conBDInt, $updtqry)
                            or die("Ñ foi possível actualizar a tabela 'wiseflow.flows_assess': " . mysqli_error($conBDInt) . $nl);

                        $i++;

                        break;

                    }
    
                }
                
            }

            // levantamento dos marcadores biométricos
                $qrybio = "SELECT stdid
                           FROM wiseflow.flows_assess
                           WHERE flowid = " . $flowid .
                             " AND dtass IS NOT NULL
                               AND (fr_avgM IS NULL
                                   OR fr_avgM = '')
                               AND stdid <> 0;";

                $nobiostdts = mysqli_query($conBDInt, $qrybio)
                                  or die("Ñ foi possível consultar a tabela 'wiseflow.flows_assess': " . mysqli_error($conBDInt) . $nl);

                $stds_bio = array();

                if (mysqli_num_rows($nobiostdts) > 0) {
                    while ($row = mysqli_fetch_array($nobiostdts)) {
                        $stdid = $row['stdid'];

                        $tmp = [];

                        $offset = 0;
                        $repeat = true;
                
                        while ($repeat) {
                            // obter amostras de correspondência facial
                            $httpcode = 0;
                            while ($httpcode <> 200) {
                                $url = $base_url . "users/" . $stdid . "/facial-recognition/matches?offset=" . $offset . "&limit=100";

                                $curlopt = array_replace($curlopt_base,
                                                         array(
                                                               CURLOPT_URL => $url,
                                                               CURLOPT_CUSTOMREQUEST => 'GET',
                                                              )
                                                        );
                    
                                $curl = curl_init();
                    
                                curl_setopt_array($curl, $curlopt);
                    
                                $response = curl_exec($curl);

                                $httpcode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

                            }

                            $result = json_decode($response, true);

                            curl_close($curl);
                            unset($response);

                            if (isset($result['data'])
                                && count($result['data']) > 0) {
                                foreach($result['data'] as $rec) {
                                    // construir array com dados-chave
                                    array_push($tmp, array('flowid'=>$rec['flowId'], 'similarity'=>$rec['similarity']));

                                }

                                // caso o número de amostras seja superior a 100, repete o ciclo e recolhe mais amostras
                                if ((count($result['data']) + $offset) < $result['pagination']['total']) {
                                    $offset += count($result['data']);

                                } else {
                                    $repeat = false;

                                }

                            } else {
                                break;

                            }

                        }

                        if (!empty($tmp)) {
                            $result = array_reduce($tmp, function($carry, $item) {
                                // obter soma das taxas de correspondência facial
                                $carry[$item['flowid']] = isset($carry[$item['flowid']]) ?
                                                          $carry[$item['flowid']] + $item['similarity'] :
                                                          $item['similarity'];
                
                                // obter soma do número de amostras
                                $carry[$item['flowid'] . "_samples"] = isset($carry[$item['flowid'] . "_samples"]) ?
                                                                       $carry[$item['flowid'] . "_samples"] + 1 :
                                                                       1;
                
                                return $carry;
                
                            }, []);
                
                            // calcular taxa média, com base no número total de amostras
                            // e limpar array
                            foreach ($result as $flowid => $value) {
                                if (strpos($flowid, "_samples") !== false) { continue; }
                
                                $result[$flowid] = round($value / $result[$flowid . "_samples"], 2);
                
                                unset($result[$flowid . "_samples"]);
                
                            }
                
                            // carregar informação completa no array de estudantes
                            array_push($stds_bio, array('stdid'=>$stdid, 'flowdata'=>$result));
                        }

                    }

                    if (!empty($stds_bio)) {
                        foreach ($stds_bio as $std) {
                            $stdid = $std['stdid'];

                            foreach($std['flowdata'] as $flow=>$similarity) {
                                // actualizar tabela de controlo com marcadores biométricos
                                $updtqry = "UPDATE wiseflow.flows_assess
                                            SET fr_avgM = '" . $similarity . "'
                                            WHERE flowid = " . $flow . "
                                                AND stdid = " . $stdid . ";";

                                mysqli_query($conBDInt, $updtqry)
                                    or die("Ñ foi possível actualizar a tabela 'wiseflow.flows_assess': " . mysqli_error($conBDInt) . $nl);
                
                                $bio += mysqli_affected_rows($conBDInt);
                                
                            }
                    
                            // actualizar tabela de controlo com dados nulos nos flows
                            // já decorridos e sem informação biométrica
                            $updtqry = "UPDATE wiseflow.flows_assess
                                        SET fr_avgM = '-1'
                                        WHERE stdid = " . $stdid . "
                                            AND DATE(dtass) < DATE(NOW())
                                            AND (fr_avgM IS NULL
                                                OR fr_avgM = '');";

                            mysqli_query($conBDInt, $updtqry)
                                or die("Ñ foi possível actualizar a tabela 'wiseflow.flows_assess': " . mysqli_error($conBDInt) . $nl);

                        }
                
                    }

                }

            // sinalizar no flow como já tendo sido lido o respectivo log, para não haver repetição
            $updtqry = "UPDATE wiseflow.flows
                        SET log = 1
                        WHERE flowid = '" . $flowid . "';";

            mysqli_query($conBDInt, $updtqry)
                or die("Ñ foi possível actualizar a tabela 'wiseflow.flows': " . mysqli_error($conBDInt) . $nl);

        }

        // printf("Foram registados " . $j . " comparecentes e " . $i . " submissões nos flows" . $nl);
        printf("Foram registadas " . $i . " submissões nos flows" . $nl);

        if ($bio > 0) {
            printf("Foram carregados " . $bio . " marcadores biométricos" . $nl);

        } else {
            printf("Sem marcadores biométricos p/ carregar" . $nl);

        }

    } else {
        printf("Sem eventos p/ registar" . $nl);

    }

@mysqli_close($conBDInt);
