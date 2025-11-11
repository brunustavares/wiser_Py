<?php
/**
 * monitors the students behaviour within the flows running in WISEflow
 * (developed for UAb - Universidade Aberta)
 *
 * @package    Sentinel_F
 * @category   php_script
 * @author     Bruno Tavares <brunustavares@gmail.com>
 * @link       https://www.linkedin.com/in/brunomastavares/
 * @copyright  Copyright (C) 2025-present Bruno Tavares
 * @license    GNU General Public License v3 or later
 *             https://www.gnu.org/licenses/gpl-3.0.html
 * @version    2025103104
 * @date       2025-10-31
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

// quebras de linha nas mensagens, em função do ambiente gráfico de chamada
$nl = "";
if (Is_cli()) {
    $nl = "\n\n";

} else {
    $nl = "<br><br>";

    echo '<link rel="shortcut icon" href="https://europe.wiseflow.net/favicon.ico" type="image/x-icon"/>';

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
 * Valida token de acesso aos endpoints da API
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

// acesso à BDInt
$conBDInt = connect2bdint();

$mode = getopt("m:", ["mode:"]);

if (!empty($mode)
    && (isset($mode['m'])
    || isset($mode['mode']))) {
    $mode = isset($mode['m']) ? $mode['m'] : $mode['mode'];

    $slctqry = "SELECT value AS admin
                FROM wiseflow.sentinelf_settings
                WHERE setting = 'admin';";

    $result = mysqli_query($conBDInt, $slctqry)
                  or die("Ñ foi possível consultar a tabela 'wiseflow.sentinelf_settings': " . mysqli_error($conBDInt)
                        . $nl . $nl
                        . $slctqry);

    $admin = (string)mysqli_fetch_assoc($result)['admin'];
    
    if ($mode == "sentinel") {
        // obtenção dos parâmetros transversais do curl
        $curlopt_base = set_curl_params(time());

        // obtenção da lista de flows em realização
        $slctqry = "SELECT subtitle,
                           flowid,
                           dtfrom,
                           dtto
                    FROM wiseflow.flows
                    WHERE NOW() BETWEEN dtfrom AND dtto + INTERVAL 30 MINUTE
                    ORDER BY dtfrom, dtto, subtitle;";

        $running_flows = mysqli_query($conBDInt, $slctqry)
                             or die("Ñ foi possível consultar a tabela 'wiseflow.flows': " . mysqli_error($conBDInt)
                                   . $nl . $nl
                                   . $slctqry);

        $total = mysqli_num_rows($running_flows);

        if ($total > 0) {
            $runtime = (string)date(FULLDATE);

            $get_lastrun = "SELECT value AS lastrun
                            FROM wiseflow.sentinelf_settings
                            WHERE setting = 'lastrun';";
            
            $result = mysqli_query($conBDInt, $get_lastrun)
                          or die("Ñ foi possível consultar a tabela 'wiseflow.sentinelf_settings': " . mysqli_error($conBDInt)
                                . $nl . $nl
                                . $get_lastrun);

            $lastrun = (string)mysqli_fetch_assoc($result)['lastrun'];

            $slctqry = "SELECT MAX(CASE WHEN setting = 'manageTO' THEN value END) AS manageTO,
                               MAX(CASE WHEN setting = 'manageCC' THEN value END) AS manageCC
                        FROM wiseflow.sentinelf_settings;";

            $result = mysqli_query($conBDInt, $slctqry)
                        or die("Ñ foi possível consultar a tabela 'wiseflow.sentinelf_settings': " . mysqli_error($conBDInt)
                              . $nl . $nl
                              . $slctqry);

            $manageTO = array_map('trim', explode(';', mysqli_fetch_assoc($result)['manageTO']));
            $manageCC = array_map('trim', explode(';', mysqli_fetch_assoc($result)['manageCC']));

            printf("A ler " . $total . " flows..." . $nl);

            $i = 0;

            // percorrer os flows
            while ($row = mysqli_fetch_array($running_flows)) {
                $flowid = $row['flowid'];

                if ($lastrun > $row['dtfrom']) {
                    $dtfrom = strtotime($lastrun);

                } else {
                    $dtfrom = strtotime($row['dtfrom']);

                }
                $dtto = strtotime($row['dtto']) + (30 * 60);

                $events = [];

                // obter eventos de participação no flow
                $url = $base_url . "participation-events";

                echo "a recolher eventos do flow " . $flowid . "...";

                $offset = 0;
                $limit = 1000;
                $repeat = true;

                while ($repeat) {
                    // $data = <<<DATA
                    //                {
                    //                 "flowId": $flowid,
                    //                 "userId": $stdid,
                    //                 "types": [
                    //                           "ASSIGNMENT_ACCESSED", //Logs when the participant accesses the specific content or documents related to the assignment [16, 17].
                    //                           "CHARACTERS_TYPED", //Logs the counting or input activity of characters entered by the participant, typically used for activity monitoring.
                    //                           "DEVICE_MONITOR_SCREENSHOT", //Logs when a screenshot is captured during the device monitoring/invigilation process [4, 11, 12].
                    //                           "FACIAL_RECOGNITION", //Logs an event related to facial recognition matching attempts, typically captured during invigilated exams [6-8].
                    //                           "FEEDBACK_ACCESSED", //Logs when the participant accesses feedback, marks, or assessment information provided by assessors/reviewers.
                    //                           "FLOW_OPENED", //Logs when a flow (exam or assignment) is accessed or initiated by the participant/user.
                    //                           "HEARTBEAT", //Logs a general system connectivity or status signal.
                    //                           "INDIVIDUAL_DEADLINE", //Logs events related to individualized participation deadlines being set or modified for a specific participant [18, 19].
                    //                           "LOG" //Captures general raw system log entries. Note that the structure and content of raw logs are stated to vary and are not recommended for building software that depends on the structure [20-22].
                    //                           "PAPER_HANDED_IN", //Logs the successful submission (hand-in) of a paper or assignment. An example payload showed this event includes participant end date information [13-15].
                    //                           "PAPER_SAVED", //Logs intermediate saving of the participant's work within the assignment/exam environment.
                    //                           "PAPER_WITHDRAWN", //Logs the withdrawal of a previously handed-in paper/submission.
                    //                           "PARTICIPANT_PRESENCE", //Logs the active presence or attendance state of the participant in the system or examination room.
                    //                           "PROGRESS", //Logs general updates regarding the participant's activity or advancement through the flow.
                    //                           "VOICE_DETECTION", //Logs an event related to audio recordings captured during the invigilation process [9, 10].
                    //                 ],
                    //                 "timestamp": {
                    //                               "from": $dtfrom,
                    //                               "to": $dtto
                    //                 },
                    //                 "pagination": {
                    //                                "limit": $limit,
                    //                                "offset": $offset
                    //                 }
                    //                }
                    //         DATA;

                    $data = <<<DATA
                                {
                                 "flowId": $flowid,
                                 "timestamp": {
                                               "from": $dtfrom,
                                               "to": $dtto
                                 },
                                 "pagination": {
                                                "limit": $limit,
                                                "offset": $offset
                                 }
                                }
                            DATA;

                    $httpcode = 0;
                    while ($httpcode <> 200) {
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

                        if ($httpcode <> 200) {
                            echo " | HTTP error: " . $httpcode;
                            die();

                        }

                    }

                    $events = json_decode($response, true)['data'];

                    if (!empty($events)) {
                        foreach ($events as $event) {
                            $isrtqry = "INSERT IGNORE INTO wiseflow.sentinelf_events (flowid, stdid, timestamp, type, payload)
                                        VALUES (
                                                " . intval($event['flowId']) . ",
                                                " . intval($event['userId']) . ",
                                                FROM_UNIXTIME(" . intval($event['timestamp']) . "),
                                                '" . mysqli_real_escape_string($conBDInt, $event['type']) . "',
                                                '" . mysqli_real_escape_string($conBDInt, json_encode($event['payload'])) . "'
                                               );";

                            mysqli_query($conBDInt, $isrtqry)
                                or die("Ñ foi possível actualizar a tabela 'wiseflow.sentinelf_events': " . mysqli_error($conBDInt)
                                      . $nl . $nl
                                      . $isrtqry);

                        }

                        if (count($events) >= $limit) {
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
                unset($events);

                $i++;

                echo " | " . number_format(($i / $total) * 100, 2, '.', '') . "% concluídos com sucesso!" . $nl;

            }



            // TODO: criar área de gestão no wiser.Py, para tabelas de eventos, tipos de evento, destinatários dos relatórios



            // detectar eventos não catalogados, registá-los na BD e enviar notificação de administração
            $slctqry = "SELECT evts.flowid,
                               evts.stdid,
                               evts.timestamp,
                               evts.type,
                               evts.payload
                        FROM wiseflow.sentinelf_events evts
                            LEFT JOIN wiseflow.sentinelf_event_types evt_tp ON evt_tp.type = evts.type
                        WHERE evts.timestamp >= NOW() - INTERVAL 30 MINUTE
                            AND evt_tp.id IS NULL;";

            $new_events = mysqli_query($conBDInt, $slctqry)
                              or die("Ñ foi possível consultar a tabela 'wiseflow.sentinelf_events': " . mysqli_error($conBDInt)
                                    . $nl . $nl
                                    . $slctqry);

            if (mysqli_num_rows($new_events) > 0) {
                $events = mysqli_fetch_all($new_events, MYSQLI_ASSOC);

                $html_table = '';

                $html_table .= "<table border='1' cellspacing='0' cellpadding='6' style='border-collapse: collapse; font-family: Arial, sans-serif;'>";

                $html_table .= "<thead style='background-color:#f0f0f0;'>
                                    <tr>
                                        <th>flow</th>
                                        <th>std_num</th>
                                        <th>timestamp</th>
                                        <th>type</th>
                                        <th>payload</th>
                                    </tr>
                                </thead><tbody>";

                foreach ($events as $event) {
                    $slctqry = "SELECT flowid,
                                       subtitle
                                FROM wiseflow.flows
                                WHERE flowid = " . intval($event['flowid']) . ";";

                    $flow_info = mysqli_query($conBDInt, $slctqry)
                                     or die("Ñ foi possível consultar a tabela 'wiseflow.flows': " . mysqli_error($conBDInt)
                                           . $nl . $nl
                                           . $slctqry);

                    $flow_row = mysqli_fetch_array($flow_info);

                    $event['subtitle'] = $flow_row['subtitle'];

                    $slctqry = "SELECT stdid,
                                       std_num
                                FROM wiseflow.students
                                WHERE stdid = " . intval($event['stdid']) . ";";

                    $std_info = mysqli_query($conBDInt, $slctqry)
                                    or die("Ñ foi possível consultar a tabela 'wiseflow.students': " . mysqli_error($conBDInt)
                                          . $nl . $nl
                                          . $slctqry);

                    $std_row = mysqli_fetch_array($std_info);

                    $event['std_num'] = $std_row['std_num'];
                    
                    $html_table .= "<tr>";
                    $html_table .= "<td><a href='https://europe.wiseflow.net/manager/display.php?id="
                                               . htmlspecialchars($event['flowid']) . "'>"
                                               . htmlspecialchars($event['subtitle']) . "</a></td>";
                    $html_table .= "<td>" . htmlspecialchars($event['std_num']) . "</td>";
                    $html_table .= "<td>" . htmlspecialchars($event['timestamp']) . "</td>";
                    $html_table .= "<td>" . htmlspecialchars($event['type']) . "</td>";
                    $html_table .= "<td><pre style='margin:0;'>" . htmlspecialchars($event['payload']) . "</pre></td>";
                    $html_table .= "</tr>";

                }

                $html_table .= "</tbody></table>";

                $isrtqry = "INSERT INTO wiseflow.sentinelf_event_types(type, report)
                            SELECT evts.type, '1'
                            FROM wiseflow.sentinelf_events evts
                                LEFT JOIN wiseflow.sentinelf_event_types evt_tp ON evt_tp.type = evts.type
                            WHERE evts.timestamp >= NOW() - INTERVAL 30 MINUTE
                                AND evt_tp.id IS NULL
                            GROUP BY type;";

                mysqli_query($conBDInt, $isrtqry)
                    or die("Ñ foi possível actualizar a tabela 'wiseflow.sentinelf_event_types': " . mysqli_error($conBDInt)
                          . $nl . $nl
                          . $isrtqry);

                $email->AddAddress($admin);

                $email->Subject = 'ALERTA: novos eventos registados';

                $email->Body = '<div style="font-family: Arial, sans-serif; color: #222;">
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <img src="cid:sentinelfavatar" width="100" height="100" style="border-radius:50%; object-fit:cover;">
                                    </div>

                                    <hr>'

                                . $html_table .

                                '</div>';

                $email->send();
            
                printf("Notificação de administração enviada" . $nl);

                unset($events);

            }

            // detectar eventos relevantes e enviar notificação de gestão
            $slctqry = "SELECT evts.flowid,
                               evts.stdid,
                               evts.timestamp,
                               evts.type,
                               evts.payload
                        FROM wiseflow.sentinelf_events evts
                            INNER JOIN wiseflow.sentinelf_event_types evt_tp ON evt_tp.type = evts.type
                        WHERE evts.timestamp >= NOW() - INTERVAL 30 MINUTE
                            AND evt_tp.report = 1
                            AND payload IN (
                                            '{\"x\": {\"skipped\": 0}, \"cf\": 0, \"mi\": 0, \"nr\": false, \"sm\": 0, \"ts\": \"continuous\"}' ,
                                            '{}'
                                           )
                            AND evts.report IS NULL;";

            $new_events = mysqli_query($conBDInt, $slctqry)
                              or die("Ñ foi possível consultar a tabela 'wiseflow.sentinelf_events': " . mysqli_error($conBDInt)
                                    . $nl . $nl
                                    . $slctqry);

            if (mysqli_num_rows($new_events) > 0) {
                $events = mysqli_fetch_all($new_events, MYSQLI_ASSOC);

                $html_table = '';

                $html_table .= "<table border='1' cellspacing='0' cellpadding='6' style='border-collapse: collapse; font-family: Arial, sans-serif;'>";

                $html_table .= "<thead style='background-color:#f0f0f0;'>
                                    <tr>
                                        <th>flow</th>
                                        <th>std_num</th>
                                        <th>timestamp</th>
                                        <th>type</th>
                                        <th>payload</th>
                                    </tr>
                                </thead><tbody>";

                foreach ($events as $event) {
                    $slctqry = "SELECT flowid,
                                       subtitle
                                FROM wiseflow.flows
                                WHERE flowid = " . intval($event['flowid']) . ";";

                    $flow_info = mysqli_query($conBDInt, $slctqry)
                                     or die("Ñ foi possível consultar a tabela 'wiseflow.flows': " . mysqli_error($conBDInt)
                                           . $nl . $nl
                                           . $slctqry);

                    $flow_row = mysqli_fetch_array($flow_info);

                    $event['subtitle'] = $flow_row['subtitle'];

                    $slctqry = "SELECT stdid,
                                       std_num
                                FROM wiseflow.students
                                WHERE stdid = " . intval($event['stdid']) . ";";

                    $std_info = mysqli_query($conBDInt, $slctqry)
                                    or die("Ñ foi possível consultar a tabela 'wiseflow.students': " . mysqli_error($conBDInt)
                                          . $nl . $nl
                                          . $slctqry);

                    $std_row = mysqli_fetch_array($std_info);

                    $event['std_num'] = $std_row['std_num'];
                    
                    $html_table .= "<tr>";
                    $html_table .= "<td><a href='https://europe.wiseflow.net/manager/display.php?id="
                                               . htmlspecialchars($event['flowid']) . "'>"
                                               . htmlspecialchars($event['subtitle']) . "</a></td>";
                    $html_table .= "<td>" . htmlspecialchars($event['std_num']) . "</td>";
                    $html_table .= "<td>" . htmlspecialchars($event['timestamp']) . "</td>";
                    $html_table .= "<td>" . htmlspecialchars($event['type']) . "</td>";
                    $html_table .= "<td><pre style='margin:0;'>" . htmlspecialchars($event['payload']) . "</pre></td>";
                    $html_table .= "</tr>";

                    $set_alert = "UPDATE wiseflow.sentinelf_events
                                  SET report = 1
                                  WHERE flowid = " . intval($event['flowid']) . "
                                      AND stdid = " . intval($event['stdid']) . "
                                      AND timestamp = '" . mysqli_real_escape_string($conBDInt, $event['timestamp']) . "'
                                      AND type = '" . mysqli_real_escape_string($conBDInt, $event['type']) . "';";

                    mysqli_query($conBDInt, $set_alert)
                        or die("Ñ foi possível actualizar a tabela 'wiseflow.sentinelf_events': " . mysqli_error($conBDInt)
                              . $nl . $nl
                              . $set_alert);

                }

                $html_table .= "</tbody></table>";

                foreach ($manageTO as $TO) {
                    $email->AddAddress($TO);
                    
                }
    
                foreach ($manageCC as $CC) {
                    $email->AddCC($CC);
                    
                }

                $email->Subject = 'ALERTA: eventos relevantes detectados';

                $email->Body = '<div style="font-family: Arial, sans-serif; color: #222;">
                                    <div style="display:flex; align-items:center; gap:12px;">
                                        <img src="cid:sentinelfavatar" width="100" height="100" style="border-radius:50%; object-fit:cover;">
                                    </div>

                                    <hr>'

                                . $html_table .

                                '</div>';

                $email->send();
            
                printf("Notificação de gestão enviada" . $nl);

                // registar hora da notificação
                $set_alert = "UPDATE wiseflow.sentinelf_events
                              SET report = NOW()
                              WHERE report = 1;";

                mysqli_query($conBDInt, $set_alert)
                    or die("Ñ foi possível actualizar a tabela 'wiseflow.sentinelf_events': " . mysqli_error($conBDInt)
                          . $nl . $nl
                          . $set_alert);

                unset($events);

            }

            // TODO: enviar notificações em caso de detecção de inatividade prolongada

            // TODO: enviar notificações em caso de detecção de aumento súbito de caracteres digitados

            // registar hora de execução
            $set_lastrun = "UPDATE wiseflow.sentinelf_settings
                            SET value = '" . $runtime . "'
                            WHERE setting = 'lastrun';";

            mysqli_query($conBDInt, $set_lastrun)
                or die("Ñ foi possível actualizar a tabela 'wiseflow.sentinelf_settings': " . mysqli_error($conBDInt)
                      . $nl . $nl
                      . $set_lastrun);

        } else {
            printf("Sem flows em realização" . $nl);

        }

    } elseif ($mode == "report") {
        // enviar relatório de gestão
        $slctqry = "SELECT value AS reportTO
                    FROM wiseflow.sentinelf_settings
                    WHERE setting = 'reportTO';";

        $result = mysqli_query($conBDInt, $slctqry)
                    or die("Ñ foi possível consultar a tabela 'wiseflow.sentinelf_settings': " . mysqli_error($conBDInt)
                          . $nl . $nl
                          . $slctqry);

        $reportTO = array_map('trim', explode(';', mysqli_fetch_assoc($result)['reportTO']));

        $slctqry = "SELECT evts.type,
                           COUNT(*) AS N
                    FROM wiseflow.sentinelf_events evts
                    WHERE report IS NOT NULL
                        AND DATE(report) = DATE(NOW())
                    GROUP BY type;";

        $report = mysqli_query($conBDInt, $slctqry)
                      or die("Ñ foi possível consultar a tabela 'wiseflow.sentinelf_events': " . mysqli_error($conBDInt)
                            . $nl . $nl
                            . $slctqry);

        if (mysqli_num_rows($report) > 0) {
            $events = mysqli_fetch_all($report, MYSQLI_ASSOC);

            $html_table = '';

            $html_table .= "<table border='1' cellspacing='0' cellpadding='6' style='border-collapse: collapse; font-family: Arial, sans-serif;'>";

            $html_table .= "<thead style='background-color:#f0f0f0;'>
                                <tr>
                                    <th>type</th>
                                    <th>N</th>
                                </tr>
                            </thead><tbody>";

            foreach ($events as $event) {
                $html_table .= "<tr>";
                $html_table .= "<td>" . htmlspecialchars($event['type']) . "</td>";
                $html_table .= "<td>" . htmlspecialchars($event['N']) . "</td>";
                $html_table .= "</tr>";

            }

            $html_table .= "</tbody></table>";

            foreach ($reportTO as $TO) {
                $email->AddAddress($TO);
                
            }

            $email->Subject = date("Y/M_d") . ': eventos relevantes detectados';

            $email->Body = '<div style="font-family: Arial, sans-serif; color: #222;">
                                <div style="display:flex; align-items:center; gap:12px;">
                                    <img src="cid:sentinelfavatar" width="100" height="100" style="border-radius:50%; object-fit:cover;">
                                </div>

                                <hr>'

                            . $html_table .

                            '</div>';

            $email->send();
        
            printf("Relatório de gestão enviado" . $nl);

            unset($events);

        }
    
    } else {
        die("Chamada inválida!" . $nl);
        
    }
    
    // eliminar eventos obsoletos (mais de 1 ano)
    $purge_old_events = "DELETE FROM wiseflow.sentinelf_events
                         WHERE timestamp < NOW() - INTERVAL 365 DAY";

    mysqli_query($conBDInt, $purge_old_events)
        or die("Ñ foi possível actualizar a tabela 'wiseflow.sentinelf_events': " . mysqli_error($conBDInt)
              . $nl . $nl
              . $purge_old_events);

    @mysqli_close($conBDInt);

    printf("Execução concluída" . $nl);

} else {
    die("Chamada inválida!" . $nl);

}
