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
 * @version    2026020203
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
function set_curl_params($sys, $start_time = null)
{
    if ($sys == "wf") {
        $auth_chain = checkwftoken($start_time);

        $headers = array(
                         "accept:application/json",
                         "content-type:application/json",
                         "authorization:" . $auth_chain,
                        );

    } elseif ($sys == "mdl") {
        $headers = array(
                         "content-type:application/x-www-form-urlencoded",
                        );

    }

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

/**
 * Valida formato JSON do payload e escapa se necessário
 *
 * @return string payload formatado
 */
function normalizeJsonString($payload) {
    $payload = trim($payload);

    if ((substr($payload, 0, 1) === '"' && substr($payload, -1) === '"') ||
        (substr($payload, 0, 1) === "'" && substr($payload, -1) === "'")) {
        $payload = substr($payload, 1, -1);

    }

    $temp = str_replace('\\"', '"', $payload);
    $decoded = json_decode($temp, true);

    if ($decoded === null) {
        return '"' . addslashes($payload) . '"';
    
    }

    $encoded = json_encode($decoded, JSON_UNESCAPED_SLASHES);

    $escaped = addslashes($encoded);

    return '"' . $escaped . '"';

}

$mode = getopt("m:", ["mode:"]);

if (!empty($mode)
    && (isset($mode['m'])
    || isset($mode['mode']))) {
    $mode = isset($mode['m']) ? $mode['m'] : $mode['mode'];

    // acesso à BDInt
    $conBDInt = connect2bdint();

    $slctqry = "SELECT value AS admin
                FROM wiseflow.sentinelf_settings
                WHERE setting = 'admin';";

    $result = mysqli_query($conBDInt, $slctqry)
                  or die("Ñ foi possível consultar a tabela 'wiseflow.sentinelf_settings': " . mysqli_error($conBDInt)
                        . $nl . $nl
                        . $slctqry);

    $admin = (string)mysqli_fetch_assoc($result)['admin'];

    $email->setFrom('Sentinel_F@uab.pt', 'Sentinel_F');
    $email->addEmbeddedImage('../static/img/Sentinel_F.jpg', 'sentinelfavatar', 'Sentinel_F.jpg');
    
    if ($mode == "monitor") {
        // obtenção dos parâmetros transversais do curl
        $curlopt_base = set_curl_params("wf", time());

        $slctqry = "SELECT MAX(CASE WHEN setting = 'manageTO' THEN value END) AS manageTO,
                           MAX(CASE WHEN setting = 'manageCC' THEN value END) AS manageCC
                    FROM wiseflow.sentinelf_settings;";

        $result = mysqli_query($conBDInt, $slctqry)
                      or die("Ñ foi possível consultar a tabela 'wiseflow.sentinelf_settings': " . mysqli_error($conBDInt)
                            . $nl . $nl
                            . $slctqry);

        $manage = mysqli_fetch_assoc($result);

        $manageTO = array_map('trim', explode(';', $manage['manageTO']));
        $manageCC = array_map('trim', explode(';', $manage['manageCC']));

        // obtenção da lista de flows em realização
        $slctqry = "SELECT subtitle,
                           flowid,
                           dtfrom,
                           dtto
                    FROM wiseflow.flows
                    WHERE NOW() BETWEEN dtfrom AND dtto + INTERVAL 45 MINUTE
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
                                                       CURLOPT_POSTFIELDS => $data,
                                                       CURLOPT_CUSTOMREQUEST => 'POST',
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

            // criar tabela temporária, para suportar a análise com eficiência
            $sql = "CREATE TABLE IF NOT EXISTS wiseflow.sentinelf_tmp LIKE wiseflow.sentinelf_events;
                    INSERT IGNORE INTO wiseflow.sentinelf_tmp
                    SELECT *
                    FROM wiseflow.sentinelf_events
                    WHERE timestamp >= NOW() - INTERVAL 30 MINUTE
                        AND report IS NULL
                    ORDER BY timestamp ASC;";

            if (mysqli_multi_query($conBDInt, $sql)) {
                do {
                    if ($result = mysqli_store_result($conBDInt)) {
                        mysqli_free_result($result);

                    }

                } while (mysqli_more_results($conBDInt) && mysqli_next_result($conBDInt));

                $events = [];

                // detectar eventos não catalogados, registá-los na BD e enviar notificação de administração
                $slctqry = "SELECT evts.flowid,
                                   evts.stdid,
                                   evts.timestamp,
                                   evts.type,
                                   evts.payload
                            FROM wiseflow.sentinelf_tmp evts
                                LEFT JOIN wiseflow.sentinelf_event_types evt_tp ON evt_tp.type = evts.type
                            WHERE evts.timestamp >= NOW() - INTERVAL 30 MINUTE
                                AND evt_tp.id IS NULL;";

                $new_events = mysqli_query($conBDInt, $slctqry)
                                  or die("Ñ foi possível consultar a tabela 'wiseflow.sentinelf_tmp': " . mysqli_error($conBDInt)
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

                    $isrtqry = "INSERT IGNORE INTO wiseflow.sentinelf_event_types(type, payload, report)
                                SELECT evts.type, evts.payload, '1'
                                FROM wiseflow.sentinelf_tmp evts
                                    LEFT JOIN wiseflow.sentinelf_event_types evt_tp ON evt_tp.type = evts.type
                                WHERE evts.timestamp >= NOW() - INTERVAL 30 MINUTE
                                    AND evt_tp.id IS NULL
                                GROUP BY type;";

                    mysqli_query($conBDInt, $isrtqry)
                        or die("Ñ foi possível actualizar a tabela 'wiseflow.sentinelf_event_types': " . mysqli_error($conBDInt)
                              . $nl . $nl
                              . $isrtqry);

                    printf("Registados " . count($events) . " novos eventos não catalogados" . $nl);

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

                } else {
                    printf("Sem novos eventos para catalogar" . $nl);

                }

                $events = [];

                // detectar eventos relevantes e enviar notificação de gestão
                // eventos elementares
                    $slctqry = "SELECT evts.id,
                                       evts.flowid,
                                       evts.stdid,
                                       evts.timestamp,
                                       evts.type,
                                       evts.payload
                                FROM wiseflow.sentinelf_tmp evts
                                    INNER JOIN wiseflow.sentinelf_event_types evt_tp ON (evt_tp.type = evts.type AND evt_tp.payload = evts.payload)
                                WHERE evts.timestamp >= NOW() - INTERVAL 30 MINUTE
                                    AND evt_tp.report = 1
                                    AND evts.report IS NULL
                                ORDER BY evts.timestamp ASC;";

                    $new_events = mysqli_query($conBDInt, $slctqry)
                                      or die("Ñ foi possível consultar a tabela 'wiseflow.sentinelf_tmp': " . mysqli_error($conBDInt)
                                            . $nl . $nl
                                            . $slctqry);

                    if (mysqli_num_rows($new_events) > 0) {
                        $events = array_merge($events, mysqli_fetch_all($new_events, MYSQLI_ASSOC));

                        printf("Identificados " . mysqli_num_rows($new_events) . " eventos elementares relevantes" . $nl);

                    } else {
                        printf("Sem eventos elementares relevantes" . $nl);

                    }

                // eventos compostos
                    // aumento súbito de caracteres digitados
                    $slctqry = "SELECT t.id,
                                       t.flowid,
                                       t.stdid,
                                       t.timestamp,
                                       t.type,
                                       t.generated_payload AS payload
                                FROM (
                                      -- cálculo de diff_chars, diff_seconds, e construção do payload JSON
                                      SELECT id,
                                             flowid,
                                             stdid,
                                             timestamp,
                                             type,
                                             payload AS original_payload,
                                             JSON_QUOTE(
                                                        CAST(
                                                             JSON_OBJECT(
                                                                         'chars_per_second',
                                                                         FLOOR(diff_chars / diff_seconds)
                                                                        ) AS CHAR
                                                            )
                                                       ) AS generated_payload,
                                            reported
                                      FROM (
                                            SELECT id,
                                                   flowid,
                                                   stdid,
                                                   timestamp,
                                                   type,
                                                   payload,
                                                   -- diferença de caracteres digitados
                                                   CAST(
                                                        JSON_UNQUOTE(
                                                                     JSON_EXTRACT(
                                                                                  JSON_UNQUOTE(payload), '$.x.chars'
                                                                                 )
                                                                    ) AS SIGNED
                                                       )
                                                   - LAG(
                                                         CAST(
                                                              JSON_UNQUOTE(
                                                                           JSON_EXTRACT(
                                                                                        JSON_UNQUOTE(payload), '$.x.chars'
                                                                                       )
                                                                          ) AS SIGNED
                                                             )
                                                         ) OVER (
                                                                 PARTITION BY stdid, flowid
                                                                 ORDER BY `timestamp`
                                                                ) AS diff_chars,
                                                   -- difereça de tempo em segundos
                                                   TIMESTAMPDIFF(
                                                                 SECOND,
                                                                 LAG(`timestamp`) OVER (
                                                                                        PARTITION BY stdid, flowid
                                                                                        ORDER BY `timestamp`
                                                                                       ),
                                                                 `timestamp`
                                                                ) AS diff_seconds,
                                                   report AS reported
                                            FROM wiseflow.sentinelf_tmp
                                            WHERE type = 'CHARACTERS_TYPED'
                                            ORDER BY `timestamp`
                                           ) AS `CHARACTERS_TYPED`
                                      WHERE diff_seconds IS NOT NULL
                                     ) AS t
                                    JOIN wiseflow.sentinelf_event_types AS e ON e.type = t.type
                                    -- apenas registos com número de chars_per_second superior ao referencial
                                WHERE CAST(
                                           JSON_UNQUOTE(
                                                        JSON_EXTRACT(
                                                                     JSON_UNQUOTE(t.generated_payload), '$.chars_per_second'
                                                                    )
                                                       ) AS SIGNED
                                          ) >
                                      CAST(
                                           JSON_UNQUOTE(
                                                        JSON_EXTRACT(
                                                                     JSON_UNQUOTE(e.payload), '$.chars_per_second'
                                                                    )
                                                       ) AS SIGNED
                                          )
                                    AND e.report = 1
                                    AND t.reported IS NULL
                                ORDER BY t.timestamp;";

                    $new_events = mysqli_query($conBDInt, $slctqry)
                                      or die("Ñ foi possível consultar a tabela 'wiseflow.sentinelf_tmp': " . mysqli_error($conBDInt)
                                            . $nl . $nl
                                            . $slctqry);

                    if (mysqli_num_rows($new_events) > 0) {
                        $events = array_merge($events, mysqli_fetch_all($new_events, MYSQLI_ASSOC));

                        printf("Identificados " . mysqli_num_rows($new_events) . " picos nos caracteres digitados" . $nl);

                    } else {
                        printf("Sem picos nos caracteres digitados" . $nl);

                    }

                    // detecção de inactividade prolongada
                    $slctqry = "WITH inactivity_calc AS (
                                                         SELECT e.id,
                                                                e.flowid,
                                                                e.stdid,
                                                                e.timestamp,
                                                                e.type,
                                                                e.payload AS original_payload,
                                                                -- calcular inactividade em segundos desde o evento anterior do mesmo estudante
                                                                COALESCE(
                                                                         TIMESTAMPDIFF(
                                                                                       SECOND,
                                                                                       LAG(e.timestamp) OVER (PARTITION BY e.stdid ORDER BY e.timestamp),
                                                                                       e.timestamp
                                                                                      ),
                                                                         0
                                                                        ) AS inactivity_seconds,
                                                                CAST(
                                                                     JSON_EXTRACT(
                                                                                  JSON_UNQUOTE(t.payload), '$.inactivity'
                                                                                 ) AS SIGNED
                                                                    ) AS reference_inactivity,
                                                                -- gerar o payload correspondente
                                                                CONCAT(
                                                                       '\"{\\\"inactivity\\\": ',
                                                                        COALESCE(
                                                                                 TIMESTAMPDIFF(
                                                                                               SECOND,
                                                                                               LAG(e.timestamp) OVER (PARTITION BY e.stdid ORDER BY e.timestamp),
                                                                                               e.timestamp
                                                                                               ),
                                                                                 0
                                                                                ),
                                                                       '}\"'
                                                                      ) AS generated_payload,
                                                                t.report AS evt_report,
                                                                e.report AS reported
                                                         FROM wiseflow.sentinelf_tmp AS e
                                                             INNER JOIN wiseflow.flows AS f ON f.flowid = e.flowid
                                                             INNER JOIN wiseflow.flows_templates AS ft ON ft.id = f.template
                                                             INNER JOIN wiseflow.sentinelf_event_types AS t ON t.type = 'INACTIVITY'
                                                         WHERE ft.flowtype_name <> 'FLOWassign'
                                                        )
                                SELECT id,
                                       flowid,
                                       stdid,
                                       timestamp,
                                       'INACTIVITY' AS type,
                                       generated_payload AS payload
                                FROM inactivity_calc
                                WHERE inactivity_seconds > reference_inactivity
                                    AND evt_report = 1
                                    AND reported IS NULL
                                    AND NOT EXISTS (
                                                    SELECT 1
                                                    FROM wiseflow.sentinelf_tmp p
                                                    WHERE p.stdid = inactivity_calc.stdid
                                                        AND p.flowid = inactivity_calc.flowid
                                                        AND p.type = 'PAPER_HANDED_IN'
                                                        AND p.timestamp < inactivity_calc.timestamp
                                                   )
                                ORDER BY timestamp, stdid;";

                    $new_events = mysqli_query($conBDInt, $slctqry)
                                      or die("Ñ foi possível consultar a tabela 'wiseflow.sentinelf_tmp': " . mysqli_error($conBDInt)
                                            . $nl . $nl
                                            . $slctqry);

                    if (mysqli_num_rows($new_events) > 0) {
                        $events = array_merge($events, mysqli_fetch_all($new_events, MYSQLI_ASSOC));

                        printf("Identificadas " . mysqli_num_rows($new_events) . " inactividades prolongadas" . $nl);

                    } else {
                        printf("Sem inactividades prolongadas" . $nl);

                    }

            } else {
                die("Ñ foi possível criar/actualizar a tabela 'wiseflow.sentinelf_tmp': " . mysqli_error($conBDInt)
                   . $nl . $nl
                   . $sql);

            }

            if (count($events) > 0) {
                $students = '';
                $courses = '';

                foreach ($events as &$event) {
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

                    if (strpos($courses, substr($event['subtitle'], 0, 5)) === false) {
                        $courses .= (empty($courses) ? '' : ', ') . substr($event['subtitle'], 0, 5);
                        
                    }

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

                    if (strpos($students, $event['std_num']) === false) {
                        $students .= (empty($students) ? '' : ', ') . $event['std_num'];

                    }

                }

                unset($event);

                // verificar acesso à PlataformAbERTA, durante a prova
                $slctqry = "SELECT report
                            FROM wiseflow.sentinelf_event_types
                            WHERE type = 'PLATAFORMABERTA_ACCESS';";

                $evt_report = mysqli_query($conBDInt, $slctqry)
                                  or die("Ñ foi possível consultar a tabela 'wiseflow.sentinelf_event_types': " . mysqli_error($conBDInt)
                                        . $nl . $nl
                                        . $slctqry);

                $mdl_report = (int)mysqli_fetch_array($evt_report)['report'];

                if ($mdl_report == 1) {
                    // TODO: dividir em múltiplos requests, se necessário
                    $curlopt = array_replace(
                                             set_curl_params("mdl"),
                                             array(
                                                   CURLOPT_URL => $mdl_wsURL . '?wstoken=' . $mdl_token,
                                                   CURLOPT_POSTFIELDS => 'wsfunction=local_aida_estudantes_UC_acesso' .
                                                                         '&moodlewsrestformat=json' .
                                                                         '&lista_stds=' . base64_encode($students) .
                                                                         '&lista_ucs=' . base64_encode($courses) .
                                                                         '&de_ate=' . (string)date("Ymd") . '_' . (string)date("Ymd"),
                                                   CURLOPT_CUSTOMREQUEST => 'POST',
                                             )
                                            );

                    $curl = curl_init();

                    curl_setopt_array($curl, $curlopt);

                    $response = curl_exec($curl);
                    // $errNo = curl_errno($curl);
                    // $err = curl_error($curl);

                    $data = json_decode($response, true);

                    if (!empty($data)) {
                        $new_events = array();
                        $added = array();

                        foreach ($data as $record) {
                            if ((strtotime($record['lastaccess']) >= $dtfrom)
                            && (strtotime($record['lastaccess']) <= $dtto)) {
                                foreach ($events as $event) {
                                    if ($event['std_num'] === $record['stdnum']) {
                                        $key = $event['flowid'] . '_' . $event['stdid'] . '_' . $record['ucid'] . strtotime($record['lastaccess']);

                                        if (!isset($added[$key])) {
                                            $new_events[] = array(
                                                                  'id' => '00000000',
                                                                  'flowid' => $event['flowid'],
                                                                  'subtitle' => $event['subtitle'],
                                                                  'stdid' => $event['stdid'],
                                                                  'std_num' => $record['stdnum'],
                                                                  'timestamp' => $record['lastaccess'],
                                                                  'type' => 'PLATAFORMABERTA_ACCESS',
                                                                  'payload' => $record['ucsname']
                                                            );

                                            $added[$key] = true;

                                        }

                                        break;

                                    }

                                }

                            }

                        }

                        if (!empty($new_events)) {
                            $events = array_merge($events, $new_events);
                            
                            printf("Identificados " . count($new_events) . " acessos à PlataformAbERTA" . $nl);
                            
                        } else {
                            printf("Sem acessos à PlataformAbERTA" . $nl);
                            
                        }

                    } else {
                        printf("Sem acessos à PlataformAbERTA" . $nl);
                        
                    }

                    curl_close($curl);
                    unset($response);
                    unset($data);
                            
                }

                usort($events, function($a, $b) {
                    return strtotime($a['timestamp']) <=> strtotime($b['timestamp']);

                });

                $html_table = '';

                $html_table .= "<table border='1' cellspacing='0' cellpadding='6' style='border-collapse: collapse; font-family: Arial, sans-serif;'>";

                $html_table .= "<thead style='background-color:#f0f0f0;'>
                                    <tr>
                                        <th>flow</th>
                                        <th>std_num</th>
                                        <th>stdid</th>
                                        <th>timestamp</th>
                                        <th>type</th>
                                        <th>payload</th>
                                    </tr>
                                </thead><tbody>";

                foreach ($events as $event) {
                    $html_table .= "<tr>";
                    $html_table .= "<td><a href='https://europe.wiseflow.net/manager/display.php?id="
                                               . htmlspecialchars($event['flowid']) . "'>"
                                               . htmlspecialchars($event['subtitle']) . "</a></td>";
                    $html_table .= "<td>" . htmlspecialchars($event['std_num']) . "</td>";
                    $html_table .= "<td>" . htmlspecialchars($event['stdid']) . "</td>";
                    $html_table .= "<td>" . htmlspecialchars($event['timestamp']) . "</td>";
                    $html_table .= "<td>" . htmlspecialchars($event['type']) . "</td>";
                    $html_table .= "<td><pre style='margin:0;'>" . htmlspecialchars(normalizeJsonString($event['payload'])) . "</pre></td>";
                    $html_table .= "</tr>";

                    $isrtqry = "INSERT IGNORE INTO wiseflow.sentinelf_reported(flowid, stdid, timestamp, type, payload, report)
                                VALUES (
                                        " . intval($event['flowid']) . ",
                                        " . intval($event['stdid']) . ",
                                        '" . mysqli_real_escape_string($conBDInt, $event['timestamp']) . "',
                                        '" . mysqli_real_escape_string($conBDInt, $event['type']) . "',
                                        '" . mysqli_real_escape_string($conBDInt, normalizeJsonString($event['payload'])) . "',
                                        0
                                       );";

                    mysqli_query($conBDInt, $isrtqry)
                        or die("Ñ foi possível actualizar a tabela 'wiseflow.sentinelf_reported': " . mysqli_error($conBDInt)
                              . $nl . $nl
                              . $isrtqry);

                    $set_alert = "UPDATE wiseflow.sentinelf_tmp
                                  SET report = 0
                                  WHERE id = " . intval($event['id']) . ";
                                  UPDATE wiseflow.sentinelf_events
                                  SET report = 0
                                  WHERE id = " . intval($event['id']) . ";";

                    mysqli_multi_query($conBDInt, $set_alert)
                        or die("Ñ foi possível actualizar as tabelas 'wiseflow.sentinelf_tmp' e/ou 'wiseflow.sentinelf_events': " . mysqli_error($conBDInt)
                              . $nl . $nl
                              . $set_alert);

                    while (mysqli_more_results($conBDInt) && mysqli_next_result($conBDInt)) {;}

                }

                $html_table .= "</tbody></table>";

                foreach ($manageTO as $TO) { $email->AddAddress($TO); }
                foreach ($manageCC as $CC) { $email->AddCC($CC); }

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

                // registar hora da notificação no evento
                $set_alert = "UPDATE wiseflow.sentinelf_reported
                              SET report = NOW()
                              WHERE report IS NOT NULL
                                  AND report = 0;
                              UPDATE wiseflow.sentinelf_events
                              SET report = NOW()
                              WHERE report IS NOT NULL
                                  AND report = 0;";

                mysqli_multi_query($conBDInt, $set_alert)
                    or die("Ñ foi possível actualizar a tabela 'wiseflow.sentinelf_reported' e/ou 'wiseflow.sentinelf_events': " . mysqli_error($conBDInt)
                          . $nl . $nl
                          . $set_alert);

                while (mysqli_more_results($conBDInt) && mysqli_next_result($conBDInt)) {;}

                unset($events);

            }

            // registar hora de execução do Sentinel_F
            $set_lastrun = "UPDATE wiseflow.sentinelf_settings
                            SET value = '" . $runtime . "'
                            WHERE setting = 'lastrun';";

            mysqli_query($conBDInt, $set_lastrun)
                or die("Ñ foi possível actualizar a tabela 'wiseflow.sentinelf_settings': " . mysqli_error($conBDInt)
                      . $nl . $nl
                      . $set_lastrun);

            printf("Hora de execução actualizada" . $nl);

        } else { // depois de terminados os flows
            // verificar existência da tabela temporária
            $slctqry = "SELECT COUNT(*) AS table_exists
                        FROM information_schema.tables
                        WHERE table_schema = 'wiseflow'
                            AND table_name = 'sentinelf_tmp';";

            $result = mysqli_query($conBDInt, $slctqry)
                          or die("Ñ foi possível consultar a tabela 'information_schema.tables': " . mysqli_error($conBDInt)
                                . $nl . $nl
                                . $slctqry);

            $row = mysqli_fetch_assoc($result);
            $tmp_table_exists = (int)$row['table_exists'];

            if ($tmp_table_exists == 1) {
                // síntese de eventos reportados no período
                $slctqry = "SELECT flw.subtitle,
                                   flw.flowid,
                                   std.std_num,
                                   std.stdid,
                                   rep.type,
                                   COUNT(DISTINCT rep.timestamp) AS N
                            FROM wiseflow.sentinelf_reported rep
                                INNER JOIN wiseflow.flows flw ON flw.flowid = rep.flowid
                                INNER JOIN wiseflow.students std ON std.stdid = rep.stdid
                                INNER JOIN wiseflow.sentinelf_tmp tmp ON (tmp.flowid = rep.flowid AND tmp.stdid  = rep.stdid)
                            WHERE DATE(rep.report) = CURDATE()
                            GROUP BY flw.flowid, std.stdid, rep.type
                            ORDER BY flw.flowid, std.stdid, rep.type;";

                $report = mysqli_query($conBDInt, $slctqry)
                              or die("Ñ foi possível consultar a tabela 'wiseflow.sentinelf_reported': " . mysqli_error($conBDInt)
                                    . $nl . $nl
                                    . $slctqry);

                if (mysqli_num_rows($report) > 0) {
                    $html_table = '';

                    $html_table .= "<table border='1' cellspacing='0' cellpadding='6' style='border-collapse: collapse; font-family: Arial, sans-serif;'>";

                    $events = [];

                    $events = mysqli_fetch_all($report, MYSQLI_ASSOC);

                    $html_table .= "<thead style='background-color:#f0f0f0;'>
                                        <tr>
                                            <th>flow</th>
                                            <th>std_num</th>
                                            <th>tipo</th>
                                            <th>número de eventos</th>
                                        </tr>
                                    </thead><tbody>";

                    foreach ($events as $event) {
                        $html_table .= "<tr>";
                        $html_table .= "<td><a href='https://europe.wiseflow.net/manager/display.php?id="
                                                   . htmlspecialchars($event['flowid']) . "'>"
                                                   . htmlspecialchars($event['subtitle']) . "</a></td>";
                        $html_table .= "<td>" . htmlspecialchars($event['std_num']) . "</td>";
                        $html_table .= "<td>" . htmlspecialchars($event['type']) . "</td>";
                        $html_table .= "<td style='text-align:center;'>" . htmlspecialchars($event['N']) . "</td>";
                        $html_table .= "</tr>";

                    }
                    
                    $html_table .= "</tbody></table>";

                    foreach ($manageTO as $TO) { $email->AddAddress($TO); }
                    foreach ($manageCC as $CC) { $email->AddCC($CC); }

                    $email->Subject = 'INFO: síntese de eventos reportados no período';

                    $email->Body = '<div style="font-family: Arial, sans-serif; color: #222;">
                                        <div style="display:flex; align-items:center; gap:12px;">
                                            <img src="cid:sentinelfavatar" width="100" height="100" style="border-radius:50%; object-fit:cover;">
                                        </div>

                                        <hr>'

                                      . $html_table .

                                   '</div>';

                    $email->send();
                
                    printf("Síntese enviada" . $nl);

                    unset($events);

                } else {
                    printf("Sem eventos relevantes para síntese" . $nl);

                }

                // eliminar tabela temporária
                $drop_tmp_table = "DROP TABLE IF EXISTS wiseflow.sentinelf_tmp;";

                mysqli_multi_query($conBDInt, $drop_tmp_table)
                    or die("Ñ foi possível eliminar a tabela 'wiseflow.sentinelf_tmp': " . mysqli_error($conBDInt)
                          . $nl . $nl
                          . $drop_tmp_table);

                while (mysqli_more_results($conBDInt) && mysqli_next_result($conBDInt)) {;}

            }

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

        // eventos reportados no dia
        $slctqry = "SELECT r.type,
                           COUNT(*) AS N
                    FROM wiseflow.sentinelf_reported r
                    WHERE r.report >= CURDATE()
                        AND r.report < CURDATE() + INTERVAL 1 DAY
                    GROUP BY type
                    ORDER BY type ASC;";

        $report = mysqli_query($conBDInt, $slctqry)
                      or die("Ñ foi possível consultar a tabela 'wiseflow.sentinelf_events': " . mysqli_error($conBDInt)
                            . $nl . $nl
                            . $slctqry);

        // fluxos sem participação
        $slctqry = "SELECT flw.flowid,
                           flw.subtitle,
                           flw.title
                    FROM wiseflow.flows flw
                        INNER JOIN wiseflow.flows_assess flwass ON flwass.flowid = flw.flowid
                            AND (flw.dtfrom >= CURDATE()
                                AND flw.dtto < CURDATE() + INTERVAL 1 DAY)
                    GROUP BY flw.flowid , flw.subtitle , flw.title
                    HAVING SUM(CASE
                                    WHEN flwass.dtass IS NOT NULL THEN 1
                                    ELSE 0
                               END) = 0
                    ORDER BY flw.subtitle;";

        $empty = mysqli_query($conBDInt, $slctqry)
                     or die("Ñ foi possível consultar a tabela 'wiseflow.flows_assess': " . mysqli_error($conBDInt)
                           . $nl . $nl
                           . $slctqry);

        if (mysqli_num_rows($report) > 0
            || mysqli_num_rows($empty) > 0) {
            $html_table = '';

            $html_table .= "<table border='1' cellspacing='0' cellpadding='6' style='border-collapse: collapse; font-family: Arial, sans-serif;'>";

            $events = [];

            if (mysqli_num_rows($report) > 0) {
                $events = mysqli_fetch_all($report, MYSQLI_ASSOC);

                $html_table .= "<thead style='background-color:#f0f0f0;'>
                                    <tr>
                                        <th colspan='2'>eventos reportados</th>
                                    </tr>
                                    <tr>
                                        <th>tipo</th>
                                        <th>número</th>
                                    </tr>
                                </thead><tbody>";

                foreach ($events as $event) {
                    $html_table .= "<tr>";
                    $html_table .= "<td>" . htmlspecialchars($event['type']) . "</td>";
                    $html_table .= "<td style='text-align:center;'>" . htmlspecialchars($event['N']) . "</td>";
                    $html_table .= "</tr>";

                }

            }
            
            $events = [];

            if (mysqli_num_rows($empty) > 0) {
                $events = mysqli_fetch_all($empty, MYSQLI_ASSOC);

                $html_table .= "<thead style='background-color:#f0f0f0;'>
                                    <tr>
                                        <th colspan='2'>fluxos S/ participação</th>
                                    </tr>
                                    <tr>
                                        <th>subtítulo</th>
                                        <th>título</th>
                                    </tr>
                                </thead><tbody>";

                foreach ($events as $event) {
                    $html_table .= "<tr>";
                    $html_table .= "<td><a href='https://europe.wiseflow.net/manager/display.php?id="
                                               . htmlspecialchars($event['flowid']) . "'>"
                                               . htmlspecialchars($event['subtitle']) . "</a></td>";
                    $html_table .= "<td><a href='https://europe.wiseflow.net/manager/display.php?id="
                                               . htmlspecialchars($event['flowid']) . "'>"
                                               . htmlspecialchars($event['title']) . "</a></td>";
                    $html_table .= "</tr>";

                }

            }

            $html_table .= "</tbody></table>";

            foreach ($reportTO as $TO) { $email->AddAddress($TO); }

            $email->Subject = date("Y/M_d") . ': eventos relevantes';

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

        } else {
            printf("Sem eventos relevantes para relatório" . $nl);

        }

        // eliminar eventos obsoletos (mais de 1 ano)
        $purge_old_events = "DELETE FROM wiseflow.sentinelf_events
                             WHERE timestamp < NOW() - INTERVAL 365 DAY";

        mysqli_query($conBDInt, $purge_old_events)
            or die("Ñ foi possível actualizar a tabela 'wiseflow.sentinelf_events': " . mysqli_error($conBDInt)
                  . $nl . $nl
                  . $purge_old_events);

    } else {
        die("Chamada inválida!" . $nl);
        
    }
    
    @mysqli_close($conBDInt);

    printf("Execução concluída" . $nl);

} else {
    die("Chamada inválida!" . $nl);

}
