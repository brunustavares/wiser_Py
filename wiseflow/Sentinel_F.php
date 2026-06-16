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
 * @version    2026061611
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
function set_curl_params($sys, $start_time=null)
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

/**
 * Wrapper HTML para emails, com estilos injectados inline
 *
 * @return string HTML body
 */
function build_email_wrapper($content_html) {
    $bg_main = "#0B0F14";
    $bg_card = "#141A22";
    $border = "#2A3544";
    $text_main = "#E6E9ED";
    $accent = "#F28C28";
    $logo_size = 100;
    $logo_border = 2;
    $logo_vml_size = $logo_size + ($logo_border * 2);

    $wrapper = "<table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
                       style='width:100%; margin:0; padding:24px 0; mso-table-lspace:0pt; mso-table-rspace:0pt;
                              background-color:" . $bg_main . ";'>
                    <tr>
                        <td align='center' style='padding:24px 12px;'>

                        <!--[if mso]>
                            <v:roundrect xmlns:v='urn:schemas-microsoft-com:vml'
                                         xmlns:w='urn:schemas-microsoft-com:office:word'
                                         arcsize='3%'
                                         strokecolor='" . $border . "'
                                         strokeweight='1px'
                                         fillcolor='" . $bg_card . "'
                                         style='width:900px; mso-width-percent:1000;'>
                                <w:anchorlock/>
                                <v:textbox inset='0,0,0,0' style='mso-fit-shape-to-text:t'>
                                    <table role='presentation' width='900' cellpadding='0' cellspacing='0' border='0'
                                           style='width:900px;'>
                        <![endif]-->

                        <!--[if !mso]><!-- -->
                            <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'
                                   style='width:100%; max-width:900px; border-radius:12px; mso-table-lspace:0pt; mso-table-rspace:0pt;
                                          border:1px solid " . $border . "; background-color:" . $bg_card . ";'>
                        <!--<![endif]-->

                                <tr>
                                    <td style='padding:18px 20px; border-bottom:1px solid " . $border . ";'>
                                        <table role='presentation' width='100%' cellpadding='0' cellspacing='0' border='0'>
                                            <tr>
                                                <td width='" . $logo_vml_size . "' valign='middle' style='padding-right:12px;'>

                                                    <!--[if mso]>
                                                        <table role='presentation' align='center' cellpadding='0' cellspacing='0' border='0'>
                                                            <tr>
                                                                <td align='left' valign='middle' width='" . $logo_vml_size. "' height='" . $logo_vml_size. "'
                                                                    style='margin:3px;'>
                                                                    <v:oval xmlns:v='urn:schemas-microsoft-com:vml'
                                                                            strokecolor='" . $accent. "'
                                                                            strokeweight='" . $logo_border. "px'
                                                                            fill='t'
                                                                            style='width:" . $logo_vml_size. "px; height:" . $logo_vml_size. "px;'>
                                                                        <v:fill src='cid:sentinelfavatar' type='frame'/>
                                                                    </v:oval>
                                                                </td>
                                                            </tr>
                                                        </table>
                                                    <![endif]-->

                                                    <!--[if !mso]><!-- -->
                                                        <img src='cid:sentinelfavatar' width='100' height='100'
                                                             style='display:block; border:0; outline:none; text-decoration:none; border-radius:50%; border:2px solid " . $accent . "; -ms-interpolation-mode:bicubic;' alt='Sentinel_F'>
                                                    <!--<![endif]-->

                                                </td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                                <tr>
                                    <td style='padding:20px; font-family: Segoe UI, Arial, sans-serif; color:" . $text_main . ";'>
                                    " . $content_html . "
                                    </td>
                                </tr>

                        <!--[if !mso]><!-- -->
                            </table>
                        <!--<![endif]-->

                        <!--[if mso]>
                                    </table>
                                </v:textbox>
                            </v:roundrect>
                        <![endif]-->

                        </td>
                    </tr>
                </table>";

    return $wrapper;

}

/**
 * Tabela com estilos inline, para compatibilidade com clientes de email
 *
 * @return array<string, string>
 */
function get_email_table_styles() {
    return array(
        "table" => "width:100%; border-collapse:collapse; background-color:#141A22; color:#E6E9ED; font-family: Segoe UI, Arial, sans-serif; mso-table-lspace:0pt; mso-table-rspace:0pt;",
        "th" => "padding:14px 16px; text-align:left; font-size:12px; letter-spacing:0.08em; text-transform:uppercase; background-color:#1C2430; color:#E6E9ED; border-bottom:1px solid rgba(242,140,40,0.25);",
        "td" => "padding:12px 16px; font-size:14px; color:#9AA3AE; border-bottom:1px solid rgba(255,255,255,0.04); vertical-align:top;",
        "td_primary" => "padding:12px 16px; font-size:14px; color:#E6E9ED; font-weight:500; border-bottom:1px solid rgba(255,255,255,0.04); vertical-align:top;",
        "td_accent" => "padding:12px 16px; font-size:14px; color:#F28C28; font-weight:500; border-bottom:1px solid rgba(255,255,255,0.04); vertical-align:top;",
        "td_center" => "padding:12px 16px; font-size:14px; color:#9AA3AE; border-bottom:1px solid rgba(255,255,255,0.04); vertical-align:top; text-align:center;",
        "th_center" => "padding:14px 16px; text-align:center; font-size:12px; letter-spacing:0.08em; text-transform:uppercase; background-color:#1C2430; color:#E6E9ED; border-bottom:1px solid rgba(242,140,40,0.25);",
        "section_title" => "padding:14px 16px; font-size:12px; font-weight:600; text-transform:uppercase; letter-spacing:0.08em; color:#E6E9ED; background-color:#1C2430; border-top:1px solid rgba(242,140,40,0.25); border-bottom:1px solid rgba(242,140,40,0.25);",
        "link" => "color:#E6E9ED; text-decoration:none;",
        "row_even" => "background-color:#141A22;",
        "row_odd" => "background-color:#1C2430;",
        "payload" => "font-family: Consolas, monospace; font-size:12px; line-height:1.4; white-space:pre-wrap; word-break:break-word; color:#9AA3AE; margin:0;"
    );
}

/**
 * Exportação de registos para CSV a enviar por email
 *
 * @return string file_name
 */
function export_2_CSV($conBDInt, $mode, $reportTO=null) {
    $file_name      = "Sentinel_F-" . date("YmdHis") . ".csv";
    $file_path_qry  = "c://temp//" . $file_name;
    $file_path_php  = "c:\\temp\\" . $file_name;

    if ($mode == "monitor") {
        $slctqry = "SELECT 'flow',
                           'std_num',
                           'tipo',
                           'numero_eventos'
                    UNION ALL
                   (SELECT flw.subtitle,
                           std.std_num,
                           rep.type,
                           COUNT(DISTINCT rep.timestamp) AS N
                    FROM wiseflow.sentinelf_reported rep
                        INNER JOIN wiseflow.flows flw ON flw.flowid = rep.flowid
                        INNER JOIN wiseflow.students std ON std.stdid = rep.stdid
                        INNER JOIN wiseflow.sentinelf_tmp tmp ON (tmp.flowid = rep.flowid AND tmp.stdid  = rep.stdid)
                    WHERE DATE(rep.report) = CURDATE()
                    GROUP BY flw.flowid, std.stdid, rep.type
                    ORDER BY flw.flowid, std.stdid, rep.type) ";

    } elseif ($mode == "report") {
        $slctqry = "SELECT 'flow',
                           'std_numero',
                           'std_nome',
                           'turma',
                           'evento',
                           'ocorrencias(N)'
                    UNION ALL
                   (SELECT r.subtitle AS subtitle,
                           r.std_num AS std_num,
                           CONCAT(r.firstname, ' ', r.lastname) AS std_name,
                           CONCAT(r.course, ai.TURMA_MOODLE) AS turma,
                           r.dict AS evento,
                           r.T AS T
                    FROM (
                          SELECT f.lectyear AS lectyear,
                                 sfr.timestamp AS timestamp,
                                 f.subtitle AS subtitle,
                                 SUBSTR(f.subtitle, 1, 5) AS course,
                                 s.firstname AS firstname,
                                 s.lastname AS lastname,
                                 s.std_num AS std_num,
                                 sfr.type AS type,
                                 sfe.dict AS dict,
                                 COUNT(sfr.type) AS T
                          FROM sentinelf_reported sfr
                              JOIN sentinelf_event_types sfe ON sfe.type = sfr.type
                              JOIN students s ON s.stdid = sfr.stdid
                              JOIN flows f ON f.flowid = sfr.flowid
                          WHERE sfe.red_flag = 1
                          GROUP BY f.subtitle, s.std_num, sfr.type
                         ) r
                        JOIN vw_teacher_2wiseflow t ON (t.cd_discip = r.course AND t.lectyear = r.lectyear)
                        JOIN lead.alunos_inscricoes ai ON (ai.CD_DISCIP = r.course AND ai.CD_ALUNO = r.std_num AND ai.CD_LECTIVO = r.lectyear)
                    WHERE r.timestamp >= CURDATE()
						AND t.email = '" . $reportTO . "'
                    GROUP BY r.course, r.std_num, r.type
                    ORDER BY t.email, r.course, r.subtitle, r.std_num, r.timestamp) ";

    }

    $exprtqry = $slctqry . "INTO OUTFILE '" . $file_path_qry . "'
                            FIELDS TERMINATED BY ';' OPTIONALLY ENCLOSED BY '\"'
                            LINES TERMINATED BY '\r\n';";

    mysqli_query($conBDInt, $exprtqry)
        or die("Ñ foi possível exportar os dados: " . mysqli_error($conBDInt) . "\n\n");

    return $file_path_php;

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

    $email_styles = get_email_table_styles();

    $email->setFrom('Sentinel_F@uab.pt', 'Sentinel_F');
    $email->addEmbeddedImage('../static/img/Sentinel_F.jpg', 'sentinelfavatar', 'Sentinel_F.jpg');
    
    if ($mode == "monitor") {
        // obtenção dos parâmetros transversais do curl
        $curlopt_base = set_curl_params("wf", time());

        // obtenção da lista de flows em realização
        $slctqry = "SELECT subtitle,
                           flowid,
                           dtfrom,
                           dtto
                    FROM wiseflow.flows
                    WHERE NOW() BETWEEN dtfrom AND dtto + INTERVAL 60 MINUTE
                    ORDER BY dtfrom, dtto, subtitle;";

        $running_flows = mysqli_query($conBDInt, $slctqry)
                             or die("Ñ foi possível consultar a tabela 'wiseflow.flows': " . mysqli_error($conBDInt)
                                   . $nl . $nl
                                   . $slctqry);

        $total = mysqli_num_rows($running_flows);

        if ($total > 0) {
            $runtime = (string)date("Y-m-d H:i:s");

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
                    $html_table .= "<table border='0' cellspacing='0' cellpadding='0' style='" . $email_styles['table'] . "'>";
                    $html_table .= "<thead>
                                        <tr>
                                            <th style='" . $email_styles['th'] . "'>flow</th>
                                            <th style='" . $email_styles['th'] . "'>std_num</th>
                                            <th style='" . $email_styles['th'] . "'>timestamp</th>
                                            <th style='" . $email_styles['th'] . "'>type</th>
                                            <th style='" . $email_styles['th'] . "'>payload</th>
                                        </tr>
                                    </thead><tbody>";

                    $row_index = 0;

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
                        
                        $row_style = ($row_index % 2 == 0) ? $email_styles['row_even'] : $email_styles['row_odd'];
                        $html_table .= "<tr style='" . $row_style . "'>
                                            <td style='" . $email_styles['td_primary'] . "'>
                                                <a style='" . $email_styles['link'] . "' href='https://europe.wiseflow.net/manager/display.php?id="
                                                            . htmlspecialchars($event['flowid']) . "'>"
                                                            . htmlspecialchars($event['subtitle']) . "
                                                </a>
                                            </td>
                                            <td style='" . $email_styles['td'] . "'>" . htmlspecialchars($event['std_num']) . "</td>
                                            <td style='" . $email_styles['td'] . "'>" . htmlspecialchars($event['timestamp']) . "</td>
                                            <td style='" . $email_styles['td_accent'] . "'>" . htmlspecialchars($event['type']) . "</td>
                                            <td style='" . $email_styles['td'] . "'>
                                                <span style='" . $email_styles['payload'] . "'>" . htmlspecialchars($event['payload']) . "</span>
                                            </td>
                                        </tr>";
                        $row_index++;

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
                    $email->Body = build_email_wrapper($html_table);
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
                                FROM wiseflow.students s
                                WHERE stdid = " . intval($event['stdid']) . "
                                    AND NOT EXISTS (
                                                    SELECT 1
                                                    FROM wiseflow.sentinelf_tmp p
                                                    WHERE p.stdid = s.stdid
                                                        AND p.type = 'PAPER_HANDED_IN'
                                                   );";

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
                $html_table .= "<table border='0' cellspacing='0' cellpadding='0' style='" . $email_styles['table'] . "'>";
                $html_table .= "<thead>
                                    <tr>
                                        <th style='" . $email_styles['th'] . "'>flow</th>
                                        <th style='" . $email_styles['th'] . "'>std_num</th>
                                        <th style='" . $email_styles['th'] . "'>stdid</th>
                                        <th style='" . $email_styles['th'] . "'>timestamp</th>
                                        <th style='" . $email_styles['th'] . "'>type</th>
                                        <th style='" . $email_styles['th'] . "'>payload</th>
                                    </tr>
                                </thead><tbody>";

                $row_index = 0;

                foreach ($events as $event) {
                    $row_style = ($row_index % 2 == 0) ? $email_styles['row_even'] : $email_styles['row_odd'];
                    $html_table .= "<tr style='" . $row_style . "'>
                                        <td style='" . $email_styles['td_primary'] . "'>
                                            <a style='" . $email_styles['link'] . "' href='https://europe.wiseflow.net/manager/display.php?id="
                                                        . htmlspecialchars($event['flowid']) . "'>"
                                                        . htmlspecialchars($event['subtitle']) . "
                                            </a>
                                        </td>
                                        <td style='" . $email_styles['td'] . "'>" . htmlspecialchars($event['std_num']) . "</td>
                                        <td style='" . $email_styles['td'] . "'>" . htmlspecialchars($event['stdid']) . "</td>
                                        <td style='" . $email_styles['td'] . "'>" . htmlspecialchars($event['timestamp']) . "</td>
                                        <td style='" . $email_styles['td_accent'] . "'>" . htmlspecialchars($event['type']) . "</td>
                                        <td style='" . $email_styles['td'] . "'>
                                            <span style='" . $email_styles['payload'] . "'>" . htmlspecialchars(normalizeJsonString($event['payload'])) . "</span>
                                        </td>
                                    </tr>";
                    $row_index++;

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
                $email->Body = build_email_wrapper($html_table);
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
                $events = [];

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
                    $events = mysqli_fetch_all($report, MYSQLI_ASSOC);

                    $CSV_file = export_2_CSV($conBDInt, $mode);
                    $email->addAttachment($CSV_file);

                    $html_table = '';
                    $html_table .= "<table border='0' cellspacing='0' cellpadding='0' style='" . $email_styles['table'] . "'>";
                    $html_table .= "<thead>
                                        <tr>
                                            <th style='" . $email_styles['th'] . "'>flow</th>
                                            <th style='" . $email_styles['th'] . "'>std_num</th>
                                            <th style='" . $email_styles['th'] . "'>tipo</th>
                                            <th style='" . $email_styles['th'] . "'>número de eventos</th>
                                        </tr>
                                    </thead><tbody>";

                    foreach ($events as $event) {
                        $row_style = ($row_index % 2 == 0) ? $email_styles['row_even'] : $email_styles['row_odd'];
                        $html_table .= "<tr style='" . $row_style . "'>
                                            <td style='" . $email_styles['td_primary'] . "'>
                                                <a style='" . $email_styles['link'] . "' href='https://europe.wiseflow.net/manager/display.php?id="
                                                            . htmlspecialchars($event['flowid']) . "'>"
                                                            . htmlspecialchars($event['subtitle']) . "
                                                </a>
                                            </td>
                                            <td style='" . $email_styles['td'] . "'>" . htmlspecialchars($event['std_num']) . "</td>
                                            <td style='" . $email_styles['td_accent'] . "'>" . htmlspecialchars($event['type']) . "</td>
                                            <td style='" . $email_styles['td_center'] . "'>" . htmlspecialchars($event['N']) . "</td>
                                        </tr>";
                        $row_index++;

                    }
                    
                    $html_table .= "</tbody></table>";

                    foreach ($manageTO as $TO) { $email->AddAddress($TO); }
                    foreach ($manageCC as $CC) { $email->AddCC($CC); }

                    $email->Subject = 'INFO: síntese de eventos reportados no período';
                    $email->Body = build_email_wrapper($html_table);
                    if ($email->Send()) {
                        printf("INFO: Síntese enviada" . $nl);

                        sleep(5);

                        if (file_exists($CSV_file)) {
                            echo exec('del '. $CSV_file);

                            if (!file_exists($CSV_file)) {
                                printf("INFO: Ficheiro eliminado" . $nl);

                            } else {
                                printf("ALERTA: Ficheiro NAO eliminado" . $nl);

                            }

                        } else {
                            printf("INFO: Ficheiro inexistente" . $nl);

                        }

                    } else {
                        printf("ALERTA: Mensagem NAO enviada | erro: " . $email->ErrorInfo . $nl);

                    }

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
        $events = [];
        
        // enviar relatórios parcelares p/ docentes
        $slctqry = "SELECT r.subtitle AS subtitle,
                           r.flowid AS flowid,
                           CONCAT(r.firstname, ' ', r.lastname) AS std_name,
                           r.std_num AS std_num,
                           r.email AS std_email,
                           CONCAT(r.course, ai.TURMA_MOODLE) AS turma,
                           r.dict AS evento,
                           r.T AS T,
                           CAST(r.timestamp AS DATE) AS data,
                           CONCAT(t.firstname, ' ', t.lastname) AS t_name,
                           t.email AS t_email
                    FROM (
                          SELECT f.lectyear AS lectyear,
                                 sfr.timestamp AS timestamp,
                                 f.subtitle AS subtitle,
                                 f.flowid AS flowid,
                                 SUBSTR(f.subtitle, 1, 5) AS course,
                                 s.firstname AS firstname,
                                 s.lastname AS lastname,
                                 s.std_num AS std_num,
                                 s.email AS email,
                                 sfr.type AS type,
                                 sfe.dict AS dict,
                                 COUNT(sfr.type) AS T
                          FROM sentinelf_reported sfr
                              JOIN sentinelf_event_types sfe ON sfe.type = sfr.type
                              JOIN students s ON s.stdid = sfr.stdid
                              JOIN flows f ON f.flowid = sfr.flowid
                          WHERE sfe.red_flag = 1
                          GROUP BY f.subtitle, s.std_num, sfr.type
                         ) r
                        JOIN vw_teacher_2wiseflow t ON (t.cd_discip = r.course AND t.lectyear = r.lectyear)
                        JOIN lead.alunos_inscricoes ai ON (ai.CD_DISCIP = r.course AND ai.CD_ALUNO = r.std_num AND ai.CD_LECTIVO = r.lectyear)
                    WHERE r.timestamp >= CURDATE()
                    GROUP BY r.course, r.std_num, r.type
                    ORDER BY t.email, r.course, r.subtitle, r.std_num, r.timestamp;";

        $red_flags = mysqli_query($conBDInt, $slctqry)
                         or die("Ñ foi possível executar a query que identifica as 'red_flags': " . mysqli_error($conBDInt)
                               . $nl . $nl
                               . $slctqry);

        if (mysqli_num_rows($red_flags) > 0) {
            $events = mysqli_fetch_all($red_flags, MYSQLI_ASSOC);

            $reportTO = "";

            foreach ($manageTO as $TO) { $email->AddCC($TO); }
            foreach ($manageCC as $CC) { $email->AddCC($CC); }

            $email->Subject = date("Y/M_d") . ': eventos relevantes';
            $header = "";
            // TODO: passar texto p/ parâmetro na wiser.Py
            $footer = "<span style='font-size: small;'>
                           <strong>Nota:</strong> O <em>Sentinel_F</em> é um agente automatizado de monitorização, que analisa os registos (logs) produzidos durante a realização das provas na WISEflow. Através dessa análise contínua dos eventos registados pelos sistemas envolvidos, é possível&nbsp;identificar e sinalizar ocorrências potencialmente relevantes, entre as quais:
                           <ul>
                               <li>aumentos súbitos de caracteres digitados, que podem corresponder a operações de colagem de texto ou outros comportamentos que justifiquem uma análise adicional;</li>
                               <li>acessos à PlataformAbERTA durante a realização da prova, cabendo ao docente verificar se esses acessos são compatíveis com as regras definidas para a avaliação.</li>
                           </ul>
                           Importa sublinhar que o <em>Sentinel_F</em> não determina, nem comprova a existência de fraude académica. O agente limita-se a identificar eventos potencialmente relevantes com base nos dados registados pelos sistemas institucionais, cabendo aos docentes a respectiva análise contextual e a tomada de decisões. <strong><u>Trata-se, portanto, de uma ferramenta de apoio à supervisão humana e não de um mecanismo automatizado de decisão</u></strong>.</span>";

            foreach ($events as $event) {
                if ($reportTO !== $event['t_email']) {
                    if ($reportTO !== "") {
                        $CSV_file = export_2_CSV($conBDInt, $mode, $reportTO);
                        $email->addAttachment($CSV_file);

                        $html_table .= "</tbody></table>";

                        $email->AddAddress($reportTO);
                        $email->Body = $header . build_email_wrapper($html_table) . $footer;
                        if ($email->Send()) {
                            printf("INFO: Síntese enviada" . $nl);

                            sleep(5);

                            if (file_exists($CSV_file)) {
                                echo exec('del '. $CSV_file);

                                if (!file_exists($CSV_file)) {
                                    printf("INFO: Ficheiro eliminado" . $nl);

                                } else {
                                    printf("ALERTA: Ficheiro NAO eliminado" . $nl);

                                }

                            } else {
                                printf("INFO: Ficheiro inexistente" . $nl);

                            }

                        } else {
                            printf("ALERTA: Mensagem NAO enviada | erro: " . $email->ErrorInfo . $nl);

                        }

                        $email->clearAddresses();
                        $email->clearAttachments();

                    }

                    $reportTO = $event['t_email'];
                    // TODO: passar texto p/ parâmetro na wiser.Py
                    $header = "Caro(a) Professor(a) <strong>" . $event['t_name'] . "</strong>,
                               <br><br>
                               Foram detectados eventos potencialmente relevantes, na(s) prova(s) elencada(s) infra:";

                    $html_table = '';
                    $html_table .= "<table border='0' cellspacing='0' cellpadding='0' style='" . $email_styles['table'] . "'>";
                    $html_table .= "<thead>
                                        <tr>
                                            <th style='" . $email_styles['th'] . "'>fluxo</th>
                                            <th style='" . $email_styles['th'] . "'>estudante</th>
                                            <th style='" . $email_styles['th'] . "'>turma</th>
                                            <th style='" . $email_styles['th'] . "'>evento</th>
                                            <th style='" . $email_styles['th'] . "'>ocorrências(N)</th>
                                        </tr>
                                    </thead><tbody>";

                    $row_index = 0;

                }

                $row_style = ($row_index % 2 == 0) ? $email_styles['row_even'] : $email_styles['row_odd'];
                $html_table .= "<tr style='" . $row_style . "'>
                                    <td style='" . $email_styles['td_primary'] . "'>
                                        <a style='" . $email_styles['link'] . "'
                                           href='https://europe.wiseflow.net/manager/display.php?id=" . htmlspecialchars($event['flowid']) . "'>"
                                          . htmlspecialchars($event['subtitle']) . "
                                        </a>
                                    </td>
                                    <td style='" . $email_styles['td'] . "'>" . htmlspecialchars($event['std_num']) . "</td>
                                    <td style='" . $email_styles['td'] . "'>" . htmlspecialchars($event['turma']) . "</td>
                                    <td style='" . $email_styles['td_accent'] . "'>" . htmlspecialchars($event['evento']) . "</td>
                                    <td style='" . $email_styles['td_center'] . "'>" . htmlspecialchars($event['T']) . "</td>
                                </tr>";
                $row_index++;
          
            }

            // enviar último relatório parcial
            if ($reportTO !== "") {
                $CSV_file = export_2_CSV($conBDInt, $mode, $reportTO);
                $email->addAttachment($CSV_file);

                $html_table .= "</tbody></table>";

                $email->AddAddress($reportTO);
                $email->Body = $header . build_email_wrapper($html_table) . $footer;
                if ($email->Send()) {
                    printf("INFO: Síntese enviada" . $nl);

                    sleep(5);

                    if (file_exists($CSV_file)) {
                        echo exec('del '. $CSV_file);

                        if (!file_exists($CSV_file)) {
                            printf("INFO: Ficheiro eliminado" . $nl);

                        } else {
                            printf("ALERTA: Ficheiro NAO eliminado" . $nl);

                        }

                    } else {
                        printf("INFO: Ficheiro inexistente" . $nl);

                    }

                } else {
                    printf("ALERTA: Mensagem NAO enviada | erro: " . $email->ErrorInfo . $nl);

                }

            }

            printf("Relatórios parcelares enviados" . $nl);

            $email->clearAllRecipients();
            $email->clearAttachments();

            unset($events);

        } else {
            printf("Sem eventos relevantes para relatório" . $nl);

        }

        // enviar relatórios de gestão
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
            $html_table .= "<table border='0' cellspacing='0' cellpadding='0' style='" . $email_styles['table'] . "'>";

            $row_index = 0;

            $events = [];

            if (mysqli_num_rows($report) > 0) {
                $events = mysqli_fetch_all($report, MYSQLI_ASSOC);

                $html_table .= "<thead>
                                    <tr>
                                        <th style='" . $email_styles['section_title'] . "' colspan='2'>eventos reportados</th>
                                    </tr>
                                    <tr>
                                        <th style='" . $email_styles['th'] . "'>tipo</th>
                                        <th style='" . $email_styles['th_center'] . "'>número</th>
                                    </tr>
                                </thead><tbody>";

                foreach ($events as $event) {
                    $row_style = ($row_index % 2 == 0) ? $email_styles['row_even'] : $email_styles['row_odd'];
                    $html_table .= "<tr style='" . $row_style . "'>
                                        <td style='" . $email_styles['td_accent'] . "'>" . htmlspecialchars($event['type']) . "</td>
                                        <td style='" . $email_styles['td_center'] . "'>" . htmlspecialchars($event['N']) . "</td>
                                    </tr>";
                    $row_index++;

                }

                $html_table .= "</tbody>";

            }
            
            $events = [];

            if (mysqli_num_rows($empty) > 0) {
                $events = mysqli_fetch_all($empty, MYSQLI_ASSOC);

                $html_table .= "<thead>
                                    <tr>
                                        <th style='" . $email_styles['section_title'] . "' colspan='2'>fluxos S/ participação</th>
                                    </tr>
                                    <tr>
                                        <th style='" . $email_styles['th'] . "'>subtítulo</th>
                                        <th style='" . $email_styles['th'] . "'>título</th>
                                    </tr>
                                </thead><tbody>";

                foreach ($events as $event) {
                    $row_style = ($row_index % 2 == 0) ? $email_styles['row_even'] : $email_styles['row_odd'];
                    $html_table .= "<tr style='" . $row_style . "'>
                                        <td style='" . $email_styles['td_primary'] . "'>
                                            <a style='" . $email_styles['link'] . "' href='https://europe.wiseflow.net/manager/display.php?id="
                                                        . htmlspecialchars($event['flowid']) . "'>"
                                                        . htmlspecialchars($event['subtitle']) . "
                                            </a>
                                        </td>
                                        <td style='" . $email_styles['td_primary'] . "'>
                                            <a style='" . $email_styles['link'] . "' href='https://europe.wiseflow.net/manager/display.php?id="
                                                        . htmlspecialchars($event['flowid']) . "'>"
                                                        . htmlspecialchars($event['title']) . "
                                            </a>
                                        </td>
                                    </tr>";
                    $row_index++;

                }

            }

            $html_table .= "</tbody></table>";

            foreach ($reportTO as $TO) { $email->AddAddress($TO); }

            $email->Subject = date("Y/M_d") . ': eventos relevantes';
            $email->Body = build_email_wrapper($html_table);
            $email->send();
        
            printf("Relatório de gestão enviado" . $nl);

            unset($events);

        } else {
            printf("Sem eventos relevantes para relatório" . $nl);

        }

        // eliminar eventos obsoletos (mais de 1 ano)
        $purge_old_events = "DELETE FROM wiseflow.sentinelf_events
                             WHERE timestamp < NOW() - INTERVAL 180 DAY";

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
