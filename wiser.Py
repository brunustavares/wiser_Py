#
# wiser.Py
# Python app for integrated data and indicators management
# related to the tests carried out in WISEflow and Moodle.
# (developed for UAb - Universidade Aberta)
#
# @package    wiser.Py
# @category   app
# @author     Bruno Tavares <brunustavares@gmail.com>
# @link       https://www.linkedin.com/in/brunomastavares/
# @copyright  Copyright (C) 2024-present Bruno Tavares
# @license    GNU General Public License v3 or later
#             https://www.gnu.org/licenses/gpl-3.0.html
# @version    2026022706
# @date       2024-02-20
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU General Public License for more details.
#
# You should have received a copy of the GNU General Public License
# along with this program. If not, see <https://www.gnu.org/licenses/>.
#

from fileinput import filename
import sys
import os
from urllib import response
import mysql.connector
import requests
import json
import plotly.graph_objs as go
import csv
import datetime
import smtplib, ssl
import random
import string
import base64
from collections import defaultdict
from zoneinfo import ZoneInfo
from functools import wraps
from werkzeug.security import check_password_hash, generate_password_hash
from werkzeug.middleware.proxy_fix import ProxyFix
from flask import Flask, redirect, render_template, request, session
from flask_session import Session
from waitress import serve
from endpoints import call_endpoint
from PDFgenerator import generate_CoverSheet
from PDFgenerator import merge_files
from AI_module import ask_AI
from email.mime.text import MIMEText
from email.mime.multipart import MIMEMultipart
from jinja2 import Template

# templates
LOGIN_TPLT = "login.html"
MENU_TPLT = "menu.html"
SYS_MENU_TPLT = "sys_menu.html"
WF_FULL_REPORT_TPLT = "wf_full_report.html"
MDL_FULL_REPORT_TPLT = "mdl_full_report.html"
USER_REPORT_TPLT = "user_report.html"
USER_GRAPH_TPLT = "user_graph.html"
USER_BIO_TPLT = "user_bio.html"
WF_STATS_TPLT = "wf_stats.html"
MDL_STATS_TPLT = "mdl_stats.html"
QUERYN_TPLT = "queryn.html"
AIDA_TPLT = "aida.html"
SENTINEL_F_TPLT = "sentinel_f.html"
SENTINELF_EVENTS_TPLT = "sentinelf_events_2check.html"
SENTINELF_LOG_TPLT = "sentinelf_log.html"
SENTINELF_SETTINGS_TPLT = "sentinelf_settings.html"
TOOLS_TPLT = "tools.html"
CAL_TPLT = "calendar.html"
WF_TPLT = "check_wf_file.html"
MAIL_SETTINGS_TPLT = "mail_settings.html"
SOS_TPLT = "sos.html"
RESET_SYNC_TPLT = "reset_sync.html"
MANAGE_FLOW_TPLT = "manage_flow.html"
NOREPLY_TPLT = "no_reply.html"
API_ENDPTS_TPLT = "endpoints.html"
MANAGE_USR_TPLT = "manage_users.html"
LOG_TPLT = "activity_log.html"
SETPWD_TPLT = "set_pwd.html"
ERROR_TPLT = "error.html"
SETUP_TPLT = "setup.html"

# ficheiros de configuração
auth_lib_bdint = os.path.join(os.path.dirname(os.path.abspath(__file__)), 'wiseflow', 'auth_lib_bdint.php')

# obter parâmetros de configuração a partir do ficheiro
with open(auth_lib_bdint, "r") as wf_cfg_file:
    wf_cfg = wf_cfg_file.readlines()
    wf_cfg_file.close()

    # carregar variáveis
    for row in wf_cfg:
        if "//" not in row[:5]:
            if "$host =" in row:
                dbhost = row[row.find("'") + 1:row.rfind("'")]
            
            elif "$port =" in row:
                dbport = row[row.find("'") + 1:row.rfind("'")]

            elif "$usr  =" in row:
                dbuser = row[row.find("'") + 1:row.rfind("'")]

            elif "$pwd  =" in row:
                dbpass = row[row.find("'") + 1:row.rfind("'")]

            elif "$db   =" in row:
                dbname = row[row.find("'") + 1:row.rfind("'")]

            elif "$base_url =" in row:
                wf_base_url = row[row.find("'") + 1:row.rfind("'")]

            elif "$client_id     =" in row:
                wf_client_id = row[row.find("'") + 1:row.rfind("'")]

            elif "$client_secret =" in row:
                wf_client_secret = row[row.find("'") + 1:row.rfind("'")]

            elif "$grant_type    =" in row:
                wf_grant_type = row[row.find("'") + 1:row.rfind("'")]

            elif "$mdl_wsURL =" in row:
                mdl_ws_base_url = row[row.find("'") + 1:row.rfind("'")]
            
            elif "$aida_token =" in row:
                aida_token = row[row.find("'") + 1:row.rfind("'")]

            elif "$aida_base  =" in row:
                aida_base = row[row.find("'") + 1:row.rfind("'")]

# gerar password padrão
def_pwd = generate_password_hash("12345678")


# gerar/atribuir/registar nova password
def set_password(usr, setpwd, log=None, pwd=None):
    if pwd is None:
        length = 13
        character_sets = {
            'uppercase': string.ascii_uppercase,
            'lowercase': string.ascii_lowercase,
            'digits': string.digits,
            'special': string.punctuation
        }

        characters  = character_sets['uppercase']
        characters += character_sets['lowercase']
        characters += character_sets['digits']
        characters += character_sets['special']
    
        pwd = ''.join(random.choice(characters) for _ in range(length))

    wf_db = connect_db()
    wf_db_cursor = wf_db.cursor()

    wf_db_cursor.execute("UPDATE users \
                          SET hash = %s, setpwd = %s \
                          WHERE username = %s;",
                         (generate_password_hash(pwd), setpwd, usr, ))
    wf_db.commit()

    if setpwd == 1:
        wf_db_cursor.execute("SELECT username, email \
                              FROM wiseflow.users \
                              WHERE username = %s;",
                             (usr, ))
        usr_info = wf_db_cursor.fetchone()

        wf_db_cursor.execute("SELECT server, port, username, password, mailbox \
                              FROM wiseflow.mail_server \
                              WHERE purpose = 'usr_pwd';")
        mail_server = wf_db_cursor.fetchone()

        server = mail_server[0]

        if mail_server[1] is not None:
            port = mail_server[1]
        else:
            port = None

        if mail_server[2] is not None:
            username = mail_server[2]
        else:
            username = None

        if mail_server[3] is not None:
            password = mail_server[3]
        else:
            password = None

        sender = mail_server[4]
        dest = usr_info[1]
        bcc = []

        subject = "wiser.Py: dados de acesso"
        body = "<p>URL: http://mdlws.uab.pt:5000 (<span style='background-color: yellow'>nota</span>: para acesso fora da rede interna, requer a <a href='https://portal.uab.pt/si/ligacao-vpn/'>ligação VPN</a> activa)</p> \
                <p>usr: <b><span style='background-color: lightblue'>" + usr + "</span></b></p> \
                <p>pwd: <b><span style='background-color: lightblue'>" + pwd + "</span></b> (a alterar no próximo acesso)</p>"
        
        if log is not None:
            mail_log = [log[0], ("usr_" + usr), log[1]]
        else:
            mail_log = None

        send_mail(server, sender, subject, body, dest, bcc, mail_log, port, username, password)

    if log is not None:
        write_activity_log(log[0], "user", usr, log[1])

    wf_db_cursor.close()
    wf_db.close()


# ligação à base de dados
def connect_db(db=dbname):
    return mysql.connector.connect(host=dbhost, port=dbport, user=dbuser, password=dbpass, database=db)


# obtenção do token de autenticação no WiseFlow
def get_wf_token():
    url = wf_base_url + "oauth2/token"

    headers = {
               'Accept': 'application/json',
               'Content-Type': 'application/x-www-form-urlencoded'
              }
    payload = 'client_id=' + wf_client_id + '&client_secret=' + wf_client_secret + '&grant_type=' + wf_grant_type

    response = requests.request("POST", url, headers=headers, data=payload)

    wf_token = json.loads(response.text)
    wf_token['expires_in'] += int(datetime.datetime.now().timestamp())

    return wf_token


# assegura que o sistema está configurado
# FIXME: garantir que toda a base de dados está criada
def setup_required():    
    wf_db = connect_db()
    wf_db_cursor = wf_db.cursor()

    wf_db_cursor.execute("SELECT count(*) \
                          FROM information_schema.tables \
                          WHERE table_schema = 'wiseflow' \
                              AND table_name = 'users'")
    check = wf_db_cursor.fetchone()[0]

    if check == 1:
        wf_db_cursor.execute("SELECT count(*) \
                              FROM users \
                              WHERE username = 'admin'")
        check = wf_db_cursor.fetchone()[0]

        wf_db_cursor.close()
        wf_db.close()

        if check == 1:
            return False

    wf_db_cursor.close()
    wf_db.close()

    return True


# assegura que o utilizador está autenticado
def login_required(f):
    @wraps(f)

    def token(*args, **kwargs):
        if session.get("user_id") is None:
            return redirect("/login")
        
        return f(*args, **kwargs)

    return token


# verifica se é obrigatória a alteração da password
@login_required
def set_pwd(usrid):
    wf_db = connect_db()
    wf_db_cursor = wf_db.cursor()

    wf_db_cursor.execute("SELECT setpwd \
                          FROM users \
                          WHERE id = %s;",
                         (usrid, ))
    setpwd = wf_db_cursor.fetchone()[0]

    wf_db_cursor.close()
    wf_db.close()

    return None if setpwd == 0 else 1


# construção das queries à base de dados
def build_query(sys=None, lect_year=None, student=None, date=None, bio=None, status=None, stats=None, users=None):
    if sys == "wf":
        if student is not None and bio is not None:
            query = "SELECT * \
                     FROM wiseflow.students \
                     WHERE std_num = %s;"

        elif users is not None:
            query = "SELECT id, \
                            username, \
                            admin, \
                            flowman, \
                            bioman, \
                            statsman, \
                            toolsusr, \
                            sosusr, \
                            FROM_UNIXTIME(firstlogin), \
                            FROM_UNIXTIME(lastlogin), \
                            IF((T_usage/60) > 60, CONCAT(ROUND(T_usage/60/60, 0), 'hr'), CONCAT(ROUND(T_usage/60, 0), 'min')) \
                     FROM wiseflow.users \
                     ORDER BY username ASC;"

        elif stats is None:
            query = "SELECT DISTINCT flw_std.firstname, \
                                     flw_std.lastname, \
                                     flw_std.std_num, \
                                     flw_std.stdid, \
                                     /* flw_std.email, */ \
                                     flw_std.xtrT AS 'NEE_time', \
                                     flw_std.status AS 'NEE_status', \
                                     flw.title, \
                                     flw.subtitle, \
                                     flw.flowid, \
                                     flw_tmpl.flowtype_name AS 'flow_type', \
                                     flw.dtfrom, \
                                     flw.dtto, \
                                     flw_ass.dtass AS 'dtpart', \
                                     flw_ass.fr_avgM * 1 AS 'biomark', \
                                     flw_ass.status AS 'flow_status', \
                                     flw_ass.grade, \
                                     flw_ass.dtgrd \
                     FROM wiseflow.flows_assess flw_ass \
                         INNER JOIN wiseflow.flows flw ON flw.flowid = flw_ass.flowid \
                         INNER JOIN wiseflow.flows_templates flw_tmpl ON flw_tmpl.id = flw.template \
                         INNER JOIN wiseflow.students flw_std ON flw_std.stdid = flw_ass.stdid \
                     WHERE (flw_ass.dtass IS NOT NULL \
                         AND flw_ass.dtass <> '') "
                
            if lect_year is not None:
                query += " AND (DATE(flw_ass.dtass) >= '20" + lect_year[4:] + "-01-01' \
                               AND DATE(flw_ass.dtass) <= '20" + lect_year[4:] + "-12-31') "

            if student is not None:
                query += " AND flw_std.std_num = %s "

            if date is not None:
                query += " AND flw_ass.dtass BETWEEN %s AND %s "

            if bio is not None:
                query += " AND ((flw_ass.fr_avgM * 1) < %s \
                               AND flw_tmpl.flowtype_name <> 'FLOWassign') "

            if status is not None:
                if status == 0 or status == 4:
                    query += " AND flw_ass.status = " + str(status) + " "

                else:
                    query += " AND (flw_ass.status = 2 \
                                   OR flw_ass.status = 3 \
                                   OR flw_ass.status = 5) "

            query += "ORDER BY flw_std.std_num ASC, flw_ass.dtass ASC, flw.subtitle ASC;"

        elif stats == 1:
            query = "SELECT lectyear \
                     FROM courses \
                     GROUP BY lectyear \
                     ORDER BY lectyear DESC;"
            
        elif stats == 2:
            query = "SELECT `semestre` AS semestre, \
                            `flow` AS tipo, \
                            MAX(CASE WHEN `tipo` = 'efolioG' THEN `T` END) AS `efolioG: T`, \
                            MAX(CASE WHEN `tipo` = 'efolioG' THEN `media` END) AS `efolioG: nota`, \
                            MAX(CASE WHEN `tipo` = 'efolioG' THEN ROUND(((`media` / 12) * 100), 2) END) AS `efolioG: %`, \
                            MAX(CASE WHEN `tipo` = 'efolioG' THEN ROUND((`aprov` * 100), 2) END) AS `efolioG: %aprov`, \
                            MAX(CASE WHEN `tipo` = 'efolioG' THEN IF(`flow` <> 'FLOWassign', FORMAT(`bio`, 2), 'NA') END) AS `efolioG: %bio`, \
                            MAX(CASE WHEN `tipo` = 'efolioG' THEN SEC_TO_TIME(ROUND((`dur` / `T`), 0)) END) AS `efolioG: avgT`, \
                            MAX(CASE WHEN `tipo` = 'exame' THEN `T` END) AS `exame: T`, \
                            MAX(CASE WHEN `tipo` = 'exame' THEN `media` END) AS `exame: nota`, \
                            MAX(CASE WHEN `tipo` = 'exame' THEN ROUND(((`media` / 20) * 100), 2) END) AS `exame: %`, \
                            MAX(CASE WHEN `tipo` = 'exame' THEN ROUND((`aprov` * 100), 2) END) AS `exame: %aprov`, \
                            MAX(CASE WHEN `tipo` = 'exame' THEN IF(`flow` <> 'FLOWassign', FORMAT(`bio`, 2), 'NA') END) AS `exame: %bio`, \
                            MAX(CASE WHEN `tipo` = 'exame' THEN SEC_TO_TIME(ROUND((`dur` / `T`), 0)) END) AS `exame: avgT` \
                     FROM ( \
                           SELECT `crs`.`semester` AS 'semestre', \
								  IF((`flw`.`evaltype` = 'E'), 'efolioG', 'exame') AS `tipo`, \
                                  `flw_tmp`.`flowtype_name` AS `flow`, \
                                  COUNT(`flw_ass`.`dtass`) AS `T`, \
                                  ROUND((SUM(`flw_ass`.`grade`) / COUNT(`flw_ass`.`grade`)), 2) AS `media`, \
                                  `aprov` AS `aprov`, \
                                  ROUND((SUM(`flw_ass`.`fr_avgM`) / COUNT(`flw_ass`.`fr_avgM`)), 2) AS `bio`, \
								  SUM(IF(HOUR(`flw_ass`.`dtass`) < 14, TIME_TO_SEC(TIME(`flw_ass`.`dtass`)) - TIME_TO_SEC(TIME('10:00:00')), TIME_TO_SEC(TIME(`flw_ass`.`dtass`)) - TIME_TO_SEC(TIME('15:00:00')))) AS `dur` \
                           FROM `flows_assess` `flw_ass` \
                               INNER JOIN `flows` `flw` ON `flw`.`flowid` = `flw_ass`.`flowid` \
                               INNER JOIN `courses` `crs` ON `crs`.`id` = `flw`.`courseid` \
							   INNER JOIN `flows_templates` `flw_tmp` ON `flw_tmp`.`id` = `flw`.`template` \
                               INNER JOIN ( \
                                           SELECT `flw`.`lectyear`, \
                                                  `crs`.`semester`, \
												  `flw_tmp`.`flowtype_name`, \
                                                  `flw`.`evaltype`, \
                                                  COUNT(`flw_ass`.`dtass`) AS `T` \
					                       FROM `flows_assess` `flw_ass` \
                                               INNER JOIN `flows` `flw` ON `flw`.`flowid` = `flw_ass`.`flowid` \
                                               INNER JOIN `courses` `crs` ON `crs`.`id` = `flw`.`courseid` \
											   INNER JOIN `flows_templates` `flw_tmp` ON `flw_tmp`.`id` = `flw`.`template` \
                                           WHERE `dtass` IS NOT NULL \
                                           GROUP BY `flw`.`lectyear` , `crs`.`semester`, `flw_tmp`.`flowtype_name` , `flw`.`evaltype` \
                                          ) AS total ON (`total`.`flowtype_name` = `flw_tmp`.`flowtype_name` AND `total`.`evaltype` = `flw`.`evaltype` AND `total`.`lectyear` = `flw`.`lectyear` AND `total`.`semester` = `crs`.`semester`) \
		                                       INNER JOIN ( \
                                                           SELECT `aprov`.`lectyear`, \
                                                                  `aprov`.`semester`, \
                                                                  `aprov`.`flowtype_name`, \
                                                                  `aprov`.`evaltype`, \
                                                                  (`aprov`.`pos` / `grades`.`grds`) AS `aprov` \
                                                           FROM ( \
                                                                 SELECT `flw`.`lectyear`, \
                                                                        `crs`.`semester`, \
																		`flw_tmp`.`flowtype_name`, \
                                                                        `flw`.`evaltype`, \
                                                                        COUNT(`flw_ass`.`grade`) AS `pos` \
                                                                 FROM `flows_assess` `flw_ass` \
                                                                     INNER JOIN `flows` `flw` ON `flw`.`flowid` = `flw_ass`.`flowid` \
                                                                     INNER JOIN `courses` `crs` ON `crs`.`id` = `flw`.`courseid` \
																	 INNER JOIN `flows_templates` `flw_tmp` ON `flw_tmp`.`id` = `flw`.`template` \
                                                                 WHERE `flw_ass`.`grade` >= (IF((`flw`.`evaltype` = 'E'), 5.5, 9.5)) \
                                                                 GROUP BY `flw`.`lectyear` , `crs`.`semester`, `flw_tmp`.`flowtype_name` , `flw`.`evaltype` \
                                                                ) AS `aprov` \
                                                               INNER JOIN ( \
                                                                           SELECT `tmp`.`lectyear`, \
																				  `tmp`.`semester`, \
																				  `tmp`.`flowtype_name`, \
                                                                                  `tmp`.`evaltype`, \
                                                                                  `tmp`.`grds` \
                                                                           FROM ( \
                                                                                 SELECT `flw`.`lectyear`, \
                                                                                        `crs`.`semester`, \
																						`flw_tmp`.`flowtype_name`, \
                                                                                        `flw`.`evaltype`, \
                                                                                        COUNT(`flw_ass`.`grade`) AS `grds` \
                                                                                 FROM `flows_assess` `flw_ass` \
                                                                                     INNER JOIN `flows` `flw` ON `flw`.`flowid` = `flw_ass`.`flowid` \
                                                                                     INNER JOIN `courses` `crs` ON `crs`.`id` = `flw`.`courseid` \
                                                                                     INNER JOIN `flows_templates` `flw_tmp` ON `flw_tmp`.`id` = `flw`.`template` \
                                                                                 WHERE `flw_ass`.`grade` >= 0 \
                                                                                 GROUP BY `flw`.`lectyear`, `crs`.`semester`, `flw_tmp`.`flowtype_name`, `flw`.`evaltype` \
                                                                                ) AS `tmp` \
                                                                          ) AS `grades` ON (`grades`.`flowtype_name` = `aprov`.`flowtype_name` AND `grades`.`evaltype` = `aprov`.`evaltype` AND `grades`.`lectyear` = `aprov`.`lectyear` AND `grades`.`semester` = `aprov`.`semester`) \
                                                          ) AS `aprov` ON (`aprov`.`flowtype_name` = `flw_tmp`.`flowtype_name` AND `aprov`.`evaltype` = `flw`.`evaltype` AND `aprov`.`lectyear` = `flw`.`lectyear` AND `aprov`.`semester` = `crs`.`semester`) \
                           WHERE `flw`.`lectyear` = %s \
                           GROUP BY `crs`.`semester`, `flw`.`evaltype` , `flw_tmp`.`flowtype_name` \
                          ) AS `rawdata` \
                     GROUP BY `semestre`, `flow` \
                     ORDER BY `semestre`, `flow`;"
            
        elif stats == 3:
            query = "SELECT flows.UC_cod, \
                            flows.UC_name, \
                            flows.semestre, \
                            flows.prova, \
                            modo_ava.T_insc, \
                            flows.T_subs, \
                            flows.T_pos, \
                            flows.perc_media, \
                            flows.dur_media, \
                            flows.tipologia \
                     FROM ( \
                           SELECT lectyear AS al, \
                                  semester AS semestre, \
								  CASE \
                                       WHEN subtitle LIKE '%_E_' THEN 'efolioG' \
                                       ELSE 'exame' \
                                  END AS prova, \
                                  LEFT(subtitle, 5) AS UC_cod, \
                                  CASE \
                                       WHEN title LIKE 'efolioG - %' THEN REPLACE(title, 'efolioG - ', '') \
                                       WHEN title LIKE 'exame - %' THEN REPLACE(title, 'exame - ', '') \
                                       ELSE title \
                                  END AS UC_name, \
                                  COUNT(*) AS T_subs, \
                                  CASE \
                                       WHEN grade IS NOT NULL THEN \
                                           SUM(CASE WHEN subtitle LIKE '%_E_' AND grade >= 6 THEN 1 ELSE 0 END) + \
                                           SUM(CASE WHEN subtitle LIKE '%_X_' AND grade >= 10 THEN 1 ELSE 0 END) \
                                       ELSE NULL \
                                  END AS T_pos, \
                                  ROUND(CASE \
					                         WHEN grade IS NOT NULL THEN \
                                                 (AVG(CASE WHEN subtitle LIKE '%_E_' THEN grade / 12 ELSE 0 END) + \
                                                  AVG(CASE WHEN subtitle LIKE '%_X_' THEN grade / 20 ELSE 0 END)) \
                                             ELSE NULL \
                                        END * 100, 2 \
                                       ) AS perc_media, \
                                  DATE_FORMAT(SEC_TO_TIME(AVG(TIME_TO_SEC(dur))), '%H:%i:%S') AS dur_media, \
                                  flowtype_name AS tipologia \
                           FROM ( \
                                 SELECT  f.lectyear, \
										 c.semester, \
										 f.subtitle, \
                                         f.title, \
                                         fa.dtass, \
                                         fa.grade, \
                                         TIMEDIFF(fa.dtass, f.dtfrom) AS dur, \
                                         ft.flowtype_name \
                                 FROM wiseflow.flows_assess AS fa \
                                     INNER JOIN wiseflow.flows AS f ON f.flowid = fa.flowid \
                                     INNER JOIN wiseflow.courses AS c ON c.id = f.courseid \
                                     INNER JOIN wiseflow.flows_templates AS ft ON ft.id = f.template \
                                 WHERE f.lectyear = %s \
                                     AND fa.dtass IS NOT NULL \
                                ) AS raw_data \
                           GROUP BY UC_cod, prova \
                          ) AS flows \
                         LEFT JOIN ( \
                                    SELECT CD_LECTIVO AS al, \
										   CD_DURACAO AS UC_sem, \
										   SUBSTR(CD_DISCIP, 1, 5) AS UC_cod, \
                                           IF(MODO_AVA_N IS NOT NULL, IF(MODO_AVA_N = 'C', 'efolioG', 'exame'), IF(MODO_AVA = 'C', 'efolioG', 'exame')) AS aval, \
                                           COUNT(*) AS T_insc \
                                    FROM lead.alunos_inscricoes \
                                    WHERE CD_LECTIVO = %s \
                                        AND TURMA_MOODLE LIKE \"\___\___\" \
                                    GROUP BY UC_cod, aval \
                                   ) AS modo_ava ON (modo_ava.al = flows.al AND modo_ava.aval = flows.prova AND modo_ava.UC_cod = flows.UC_cod) \
                     ORDER BY flows.UC_cod, flows.prova;"

    elif sys == "mdl":
        if stats is None:
            query = "SELECT vw_pees.estudante_Nome, \
                            vw_pees.estudante_Num, \
                            vw_pees.UC_nome, \
                            vw_pees.UC_codigo, \
                            vw_pees.UC_ID, \
                            vw_pees.PEE, \
                            vw_pees.data_entrega, \
                            vw_pees.nota, \
                            vw_pees.data_nota \
                     FROM plataformaberta.vw_pees \
                     WHERE vw_pees.data_entrega IS NOT NULL "
                
            if lect_year is not None:
                query += " AND SUBSTR(UC_codigo, 7, 2) = " + lect_year[2:4] + " "

            if student is not None:
                query += " AND vw_pees.estudante_Num = %s "

            if date is not None:
                query += " AND vw_pees.data_entrega BETWEEN %s AND %s " 

            query += "ORDER BY vw_pees.estudante_Num ASC, vw_pees.data_entrega ASC, vw_pees.UC_codigo ASC;"

        elif stats == 1:
            query = "SELECT vw_pees.ano_lectivo AS lectyear \
                     FROM plataformaberta.vw_pees \
                     GROUP BY lectyear \
                     ORDER BY lectyear DESC;"

        elif stats == 2:
            query = "SELECT `prova` AS `prova`, \
	                        MAX(CASE WHEN `tipo` = 'efolioG' THEN `T` END) AS `efolioG: T`, \
                            MAX(CASE WHEN `tipo` = 'efolioG' THEN `media` END) AS `efolioG: nota`, \
                            MAX(CASE WHEN `tipo` = 'efolioG' THEN ROUND(((`media` / 12) * 100), 2) END) AS `efolioG: %`, \
                            MAX(CASE WHEN `tipo` = 'efolioG' THEN ROUND((`aprov` * 100), 2) END) AS `efolioG: %aprov`, \
                            MAX(CASE WHEN `tipo` = 'efolioG' THEN SEC_TO_TIME(ROUND((`dur` / `T`), 0)) END) AS `efolioG: avgT`, \
                            MAX(CASE WHEN `tipo` = 'exame' THEN `T` END) AS `exame: T`, \
                            MAX(CASE WHEN `tipo` = 'exame' THEN `media` END) AS `exame: nota`, \
                            MAX(CASE WHEN `tipo` = 'exame' THEN ROUND(((`media` / 20) * 100), 2) END) AS `exame: %`, \
                            MAX(CASE WHEN `tipo` = 'exame' THEN ROUND((`aprov` * 100), 2) END) AS `exame: %aprov`, \
                            MAX(CASE WHEN `tipo` = 'exame' THEN SEC_TO_TIME(ROUND((`dur` / `T`), 0)) END) AS `exame: avgT` \
                     FROM ( \
                           SELECT `vw_pees`.`tipo` AS `tipo`, \
                                  `vw_pees`.`prova` AS `prova`, \
                                  COUNT(`vw_pees`.`data_entrega`) AS 'T', \
                                  ROUND((SUM(`vw_pees`.`nota`) / COUNT(`vw_pees`.`nota`)), 2) AS `media`, \
                                  `aprov` AS `aprov`, \
                                  SUM(IF(HOUR(`vw_pees`.`data_entrega`) < 14, TIME_TO_SEC(TIME(`vw_pees`.`data_entrega`)) - TIME_TO_SEC(TIME('10:00:00')), TIME_TO_SEC(TIME(`vw_pees`.`data_entrega`)) - TIME_TO_SEC(TIME('15:00:00')))) AS `dur` \
                           FROM `vw_pees` `vw_pees` \
                               INNER JOIN ( \
                                           SELECT `vw_pees`.`ano_lectivo`, \
                                                  `vw_pees`.`prova`, \
                                                  `vw_pees`.`tipo`, \
                                                  COUNT(`vw_pees`.`data_entrega`) AS 'T' \
                                           FROM `vw_pees` `vw_pees` \
                                           WHERE `data_entrega` IS NOT NULL \
                                           GROUP BY `vw_pees`.`ano_lectivo` , `vw_pees`.`tipo` , `vw_pees`.`prova` \
                                          ) AS `total` ON (`total`.`prova` = `vw_pees`.`prova` AND `total`.`tipo` = `vw_pees`.`tipo` AND `total`.`ano_lectivo` = `vw_pees`.`ano_lectivo`) \
                                               INNER JOIN ( \
                                                           SELECT `aprov`.`ano_lectivo`, \
                                                                  `aprov`.`prova`, \
                                                                  `aprov`.`tipo`, \
                                                                  (`aprov`.`pos` / `grades`.`nota`) AS `aprov` \
                                                           FROM ( \
                                                                 SELECT `vw_pees`.`ano_lectivo`, \
                                                                        `vw_pees`.`prova`, \
                                                                        `vw_pees`.`tipo`, \
                                                                        COUNT(`vw_pees`.`nota`) AS `pos` \
                                                                 FROM `vw_pees` `vw_pees` \
                                                                 WHERE `vw_pees`.`nota` >= (IF((`vw_pees`.`tipo` = 'efolioG'), 5.5, 9.5)) \
                                                                 GROUP BY `vw_pees`.`ano_lectivo` , `vw_pees`.`prova` , `vw_pees`.`tipo` \
                                                                ) AS `aprov` \
                                                               INNER JOIN ( \
                                                                           SELECT `tmp`.`ano_lectivo`, \
                                                                                  `tmp`.`prova`, \
                                                                                  `tmp`.`tipo`, \
                                                                                  `tmp`.`nota` \
                                                                           FROM ( \
                                                                                 SELECT `vw_pees`.`ano_lectivo`, \
                                                                                        `vw_pees`.`prova`, \
                                                                                        `vw_pees`.`tipo`, \
                                                                                        COUNT(`vw_pees`.`nota`) AS `nota` \
                                                                                 FROM `vw_pees` `vw_pees` \
                                                                                 WHERE `vw_pees`.`nota` >= 0 \
                                                                                 GROUP BY `vw_pees`.`ano_lectivo` , `vw_pees`.`prova` , `vw_pees`.`tipo` \
                                                                                ) AS `tmp` \
                                                                          ) AS `grades` ON (`grades`.`prova` = `aprov`.`prova` AND `grades`.`tipo` = `aprov`.`tipo` AND `grades`.`ano_lectivo` = `aprov`.`ano_lectivo`) \
					                                      ) AS `aprov` ON (`aprov`.`prova` = `vw_pees`.`prova` AND `aprov`.`tipo` = `vw_pees`.`tipo` AND `aprov`.`ano_lectivo` = `vw_pees`.`ano_lectivo`) \
		                   WHERE `vw_pees`.`ano_lectivo` = %s \
                           GROUP BY `vw_pees`.`tipo` , `vw_pees`.`prova` \
	                      ) AS `rawdata` \
                     GROUP BY `prova` \
                     ORDER BY `prova`;"

    return query


# envio de e-mails
def send_mail(server, sender, subject, body, dest=None, bcc=[], log=None, port=None, username=None, password=None):
    message = MIMEMultipart()
    message["From"] = sender
    message["To"] = dest
    message["Subject"] = subject
    message.attach(MIMEText(body, "html"))
    
    if dest:
        recipients = [dest] + bcc
        
    else:
        recipients = bcc

    ssl_ctxt = ssl.create_default_context()
    with smtplib.SMTP(server, port) as server:
        if port == 587:
            server.starttls(context=ssl_ctxt)
            
        if username is not None and password is not None:
            server.login(username, password)

        server.sendmail(sender, recipients, message.as_string())
        server.quit()

    if log is not None:
        write_mail_log(log[0], log[1], log[2])


# exportação de dados para CSV
def export_to_csv(result, fields, filename, dictionary=False):
    with open(filename, 'w', newline='') as file:
        writer = csv.DictWriter(file, fieldnames=fields, delimiter=";")
        writer.writeheader()

        if not dictionary:
            data = []

            for row in result:
                dictionary = {}

                for i, col in enumerate(fields):
                    dictionary[col] = row[i]

                data.append(dictionary)

            for row in data:
                writer.writerow(row)

        else:
            writer.writerows(result)

    file = {"filename": filename, "url": "https://mdlws.uab.pt/wiser_py/" + filename}

    return file


# gestão dos dados biométricos no WISEflow
def manage_user_bio(userid, method, pic=None):
    # renovar o token se não estiver definido ou tiver expirado
    try:
        if session["wf_token"]["expires_in"] >= (int(datetime.datetime.now().timestamp()) - 180):
            session["wf_token"] = get_wf_token()
    
    except KeyError:
        session["wf_token"] = get_wf_token()

    url = wf_base_url + "users/" + userid + "/facial-recognition/reference-image"

    headers = {
               'Accept': 'application/json',
               'Authorization': session["wf_token"]['token_type'] + ' ' + session["wf_token"]['access_token']
              }
    payload = {}
    
    if pic is None:
        files={}

    else:
        tmpfile = os.path.join("tmp", pic.filename)
        pic.save(tmpfile)

        files=[('image', (pic.filename, open(tmpfile, 'rb') , pic.mimetype))]

        pic.close()
    
    return requests.request(method, url, headers=headers, data=payload, files=files)


# recolha dos ficheiros submetidos pelo estudante no fluxo
def get_wf_subs(stdid, stdnum, stdname, flowid, flowtitle, flowsubtitle, flowtype, flowscale, flowseason, flowteacher, dtfrom, dtto):
    # renovar o token se não estiver definido ou tiver expirado
    try:
        if session["wf_token"]["expires_in"] >= (int(datetime.datetime.now().timestamp()) - 180):
            session["wf_token"] = get_wf_token()
    
    except KeyError:
        session["wf_token"] = get_wf_token()

    parts_url = wf_base_url + "flows/" + flowid + "/participants"

    headers = {
               'Accept': 'application/json',
               'Content-Type': 'application/json',
               'Authorization': session["wf_token"]['token_type'] + ' ' + session["wf_token"]['access_token']
              }

    participants = []
    offset = 0
    limit = 100
    repeat = True

    while repeat:
        offseturl = parts_url + "?offset=" + str(offset) + "&limit=" + str(limit)

        newparts = requests.request("GET", offseturl, headers=headers).json().get('data')

        if newparts:
            participants += newparts

            if len(newparts) >= limit:
                offset += limit

            else:
                repeat = False

        else:
            repeat = False

    for participant in participants:
        if participant["userId"] == int(stdid):
            partid = str(participant["participantId"])
            subid = str(participant["submissionId"])
            break

    assess_url = wf_base_url + "flow/" + flowid + "/assessments/" + partid

    headers = {
               'Accept': 'application/json',
               'Content-Type': 'application/json',
               'Authorization': session["wf_token"]['token_type'] + ' ' + session["wf_token"]['access_token']
              }

    result = requests.request("GET", assess_url, headers=headers).json().get('data')
    if result:
        stdgrade = [result['grade']]
        
    else:
        stdgrade = '(por classificar)'

    subs_url = wf_base_url + "flows/" + flowid + "/submissions/" + subid + "/files"

    headers = {
               'Accept': 'application/json',
               'Content-Type': 'application/json',
               'Authorization': session["wf_token"]['token_type'] + ' ' + session["wf_token"]['access_token']
              }

    sub_files = requests.request("GET", subs_url, headers=headers).json().get('data')

    sub_files_sorted = sorted(
        sub_files,
        key=lambda f: f["createdAt"]
    )

    files = []
    output_path = "tmp/usrID" + str(session["user_id"]) + "_" + stdid + "-" + flowid + "-"

    for file in sub_files_sorted:
        file_url = file["link"]
        file_name = file["fileName"]
        file_path = output_path + file_name

        files.append(file_path)

        response = requests.get(file_url, stream=True)
        response.raise_for_status()

        with open(file_path, "wb") as f:
            for chunk in response.iter_content(chunk_size=8192):
                if chunk:
                    f.write(chunk)

    CoverSheet = output_path + "CoverSheet.pdf"

    generate_CoverSheet(
        CoverSheet,
        "WISEflow",
        "Europa/Lisboa",
        flowtitle,
        flowsubtitle,
        flowseason,
        dtfrom,
        dtto,
        flowscale,
        flowtype,
        flowteacher,
        stdname,
        stdnum,
        stdgrade
    )
    
    files.insert(0, CoverSheet)

    filename = output_path + "prova.pdf"

    merge_files(files, filename)
    
    file = {"filename": filename, "url": "https://mdlws.uab.pt/wiser_py/" + filename}

    return file


# recolha dos eventos do estudante no fluxo
def get_wf_events(stdid, flowid, dtfrom, dtto):
    # renovar o token se não estiver definido ou tiver expirado
    try:
        if session["wf_token"]["expires_in"] >= (int(datetime.datetime.now().timestamp()) - 180):
            session["wf_token"] = get_wf_token()
    
    except KeyError:
        session["wf_token"] = get_wf_token()

    url = wf_base_url + "participation-events"

    headers = {
               'Accept': 'application/json',
               'Content-Type': 'application/json',
               'Authorization': session["wf_token"]['token_type'] + ' ' + session["wf_token"]['access_token']
              }

    events = []
    offset = 0
    limit = 1000
    repeat = True

    while repeat:
        payload = json.dumps({
                              "flowId": int(flowid),
                              "userId": int(stdid),
                            #   "types": [
                            #             "FACIAL_RECOGNITION",
                            #             "PAPER_HANDED_IN"
                            #   ],
                              "timestamp": {
                                            "from": int(dtfrom),
                                            "to": int(dtto)
                                           },
                              "pagination": {
                                             "limit": limit,
                                             "offset": offset
                                            }
        })

        newevents = requests.request("POST", url, headers=headers, data=payload).json().get('data')

        if newevents:
            events += newevents

            if len(newevents) >= limit:
                offset += limit

            else:
                repeat = False

        else:
            repeat = False

    return events


# remoção dos avaliadores no WISEflow, em caso de anulação
def remove_part_assessors(flowid, partid):
    # renovar o token se não estiver definido ou tiver expirado
    try:
        if session["wf_token"]["expires_in"] >= (int(datetime.datetime.now().timestamp()) - 180):
            session["wf_token"] = get_wf_token()
    
    except KeyError:
        session["wf_token"] = get_wf_token()

    # obter lista de avaliadores
    url = wf_base_url + "flows/" + flowid + "/assessors"

    headers = {
               'Accept': 'application/json',
               'Content-Type': 'application/json',
               'Authorization': session["wf_token"]['token_type'] + ' ' + session["wf_token"]['access_token']
              }
    payload = {}

    assessors = requests.request("GET", url, headers=headers, data=payload).json().get('data')

    # por cada avaliador, obter lista de participantes
    found = False
    for assessor in assessors:
        url_ass = url + "/" + str(assessor['assessorId']) + "/allocations"

        headers = {
                   'Accept': 'application/json',
                   'Content-Type': 'application/json',
                   'Authorization': session["wf_token"]['token_type'] + ' ' + session["wf_token"]['access_token']
                  }
        payload = {}

        alloc_ass = requests.request("GET", url_ass, headers=headers, data=payload).json()

        if int(partid) in alloc_ass['data']['participantIds']:
            found = True
            break

    if found:
        # remover avaliador do participante
        url_ass += "/participants/" + partid

        headers = {
                   'Accept': '*/*',
                   'Content-Type': 'application/json',
                   'Authorization': session["wf_token"]['token_type'] + ' ' + session["wf_token"]['access_token']
                  }
        payload = {}

        requests.request("DELETE", url_ass, headers=headers, data=payload)

    # obter lista de revisores
    url = wf_base_url + "flows/" + flowid + "/reviewers"

    headers = {
               'Accept': 'application/json',
               'Content-Type': 'application/json',
               'Authorization': session["wf_token"]['token_type'] + ' ' + session["wf_token"]['access_token']
              }
    payload = {}

    reviewers = requests.request("GET", url, headers=headers, data=payload).json().get('data')

    # por cada revisor, obter lista de participantes
    found = False
    for reviewer in reviewers:
        url_rev = url + "/" + str(reviewer['reviewerId']) + "/allocations"

        headers = {
                   'Accept': 'application/json',
                   'Content-Type': 'application/json',
                   'Authorization': session["wf_token"]['token_type'] + ' ' + session["wf_token"]['access_token']
                  }
        payload = {}

        alloc_rev = requests.request("GET", url_rev, headers=headers, data=payload).json()

        if int(partid) in alloc_rev ['data']['participantIds']:
            found = True
            break

    if found:
        # remover revisor do participante
        url_rev += "/participants/" + partid

        headers = {
                   'Accept': '*/*',
                   'Content-Type': 'application/json',
                   'Authorization': session["wf_token"]['token_type'] + ' ' + session["wf_token"]['access_token']
                  }
        payload = {}

        requests.request("DELETE", url_rev, headers=headers, data=payload)


# extensão do tempo de um flow no WISEflow
def extend_flow_time(flowid, xtra):
    # renovar o token se não estiver definido ou tiver expirado
    try:
        if session["wf_token"]["expires_in"] >= (int(datetime.datetime.now().timestamp()) - 180):
            session["wf_token"] = get_wf_token()
    
    except KeyError:
        session["wf_token"] = get_wf_token()

    parts_url = wf_base_url + "flows/" + flowid + "/participants"
    subs_url = wf_base_url + "flow/" + flowid + "/submissions"
    dates_url = wf_base_url + "flows/" + flowid + "/dates"

    headers = {
               'Accept': 'application/json',
               'Content-Type': 'application/json',
               'Authorization': session["wf_token"]['token_type'] + ' ' + session["wf_token"]['access_token']
              }
    payload = {}

    parts = []
    offset = 0
    limit = 100
    repeat = True

    while repeat:
        offseturl = parts_url + "?offset=" + str(offset) + "&limit=" + str(limit)

        newparts = requests.request("GET", offseturl, headers=headers, data=payload).json().get('data')

        if newparts:
            parts += newparts

            if len(newparts) >= limit:
                offset += limit

            else:
                repeat = False

        else:
            repeat = False

    subs = requests.request("GET", subs_url, headers=headers, data=payload).json()

    dates = requests.request("GET", dates_url, headers=headers, data=payload).json().get('data')['participation']

    dtfrom = int(dates['start'])
    dtto = int(dates['end']) + (int(xtra) * 60)

    parts_list = []

    if parts:
        for part in parts:
            partid = part['participantId']
            partsubid = part['submissionId']

            for sub in subs:
                if sub['id'] == partsubid and sub['handedIn'] == False:
                    time_url = parts_url + "/" + str(partid) + "/dates"

                    payload = json.dumps({
                                          "participation": {
                                                            "start": dtfrom,
                                                            "end": dtto
                                                           }
                                         })

                    result = requests.request("PATCH", time_url, headers=headers, data=payload)

                    if result.status_code == 200:
                        parts_list.append(partid)

        if len(parts_list) > 0:
            return parts_list
        
        else:
            return None

    else:
        return None


# remoção de todos participantes de um flow no WISEflow
def remove_flow_parts(flowid):
    # renovar o token se não estiver definido ou tiver expirado
    try:
        if session["wf_token"]["expires_in"] >= (int(datetime.datetime.now().timestamp()) - 180):
            session["wf_token"] = get_wf_token()
    
    except KeyError:
        session["wf_token"] = get_wf_token()

    url = wf_base_url + "flows/" + flowid + "/participants"

    headers = {
               'Accept': 'application/json',
               'Content-Type': 'application/json',
               'Authorization': session["wf_token"]['token_type'] + ' ' + session["wf_token"]['access_token']
              }
    payload = {}

    i = 0
    j = 0
    repeat = True

    while repeat:
        parts = requests.request("GET", url, headers=headers, data=payload).json().get('data')

        if parts:
            i = len(parts)

            for part in parts:
                partid = part['participantId']

                url_part = url + "/" + str(partid)

                result = requests.request("DELETE", url_part, headers=headers, data=payload)

                if result.status_code == 200:
                    j += 1

            wf_db = connect_db()
            wf_db_cursor = wf_db.cursor()

            wf_db_cursor.execute("UPDATE wiseflow.flows_assess \
                                  SET partid = NULL, dtreg = NULL \
                                  WHERE flowid = %s;",
                                (flowid, ))
            wf_db.commit()

            wf_db_cursor.close()
            wf_db.close()

        else:
            repeat = False

    return i, j


# FIXME: carregamento do ficheiro CSV
# 1. impedir repetição de linhas
# 2. permitir alteração de linhas (datas)
# 3. aceder à AIDA com pacotes de múltiplas UCs
def fill_csv(csv_file):
    # carregamento das linhas
    rows = csv.reader(csv_file.stream.read().decode("utf-8").splitlines(), delimiter=";")

    # verifica se o separador de campos é ";"
    if all(len(row) == 1 for row in rows):
        return None, None, None, "ERRO: ficheiro CSV sem delimitador \";\""

    # templates das queries
    # para obtenção dos dados
    read_query_template = """
                             SELECT *
                             FROM (
                                   SELECT DISTINCT {lectyear} AS lectyear,
                                                   CONCAT(IF('{evaltype}' = 'E', 'efolioG - ', 'exame - '), ainsc.ds_discip) AS title,
                                                   CONCAT(ainsc.cd_discip, '_', SUBSTR({lectyear}, 3, 2), '_', UPPER('{evaltype}'), UPPER('{season}')) AS subtitle,
                                                   UPPER('{evaltype}') AS evaltype,
                                                   UPPER('{season}') AS season,
                                                   wftplt.flowtype_name AS template,
                                                   '{dtfrom}' AS dtfrom,
                                                   DATE_ADD('{dtfrom}', INTERVAL {dur} MINUTE) AS dtto,
                                                   {dur} AS dur
                                   FROM wiseflow.courses wfcrs
                                       INNER JOIN lead.alunos_inscricoes ainsc ON (ainsc.cd_discip = wfcrs.course AND ainsc.cd_lectivo = wfcrs.lectyear)
                                       INNER JOIN wiseflow.flows_templates wftplt ON LOWER(wftplt.flowtype_name) = LOWER('{type}')
                                   WHERE wfcrs.course = '{course}'
                                       AND wfcrs.lectyear = {lectyear}
                                  ) AS flows
                             ORDER BY subtitle ASC
                             LIMIT 1;
                          """

    # para inserção dos dados
    insert_query_template = """
                               SELECT *
                               FROM (
                                     SELECT DISTINCT wfcrs.id AS courseid,
                                                     {lectyear} AS lectyear,
                                                     UPPER('{evaltype}') AS evaltype,
                                                     UPPER('{season}') AS season,
                                                     CONCAT(IF('{evaltype}' = 'E', 'efolioG - ', 'exame - '), ainsc.ds_discip) AS title,
                                                     CONCAT(ainsc.cd_discip, '_', SUBSTR({lectyear}, 3, 2), '_', UPPER('{evaltype}'), UPPER('{season}')) AS subtitle,
                                                     NULL AS flowid,
                                                     wftplt.id AS template,
                                                     '{dtfrom}' AS dtfrom,
                                                     DATE_ADD('{dtfrom}', INTERVAL {dur} MINUTE) AS dtto,
                                                     {dur} AS dur,
                                                     '0' AS log,
                                                     '0' AS to_eval,
                                                     '0' AS assign,
                                                     '1' AS author
                                     FROM wiseflow.courses wfcrs
                                         INNER JOIN lead.alunos_inscricoes ainsc ON (ainsc.cd_discip = wfcrs.course AND ainsc.cd_lectivo = wfcrs.lectyear)
                                         INNER JOIN wiseflow.flows_templates wftplt ON LOWER(wftplt.flowtype_name) = LOWER('{type}')
                                     WHERE wfcrs.course = '{course}'
                                         AND wfcrs.lectyear = {lectyear}
                                    ) AS flows
                               ORDER BY subtitle ASC
                               LIMIT 1;
                            """

    table_queries = []
    commit_queries = []

    # para cada linha do ficheiro CSV
    for row in rows:
        # carregamento das variáveis
        lectyear, course, evaltype, season, dtfrom, dur = row

        # geração do endpoint da AIDA, para consulta do tipo de fluxo
        aida_endpoint = mdl_ws_base_url + \
                       '?wstoken=' + aida_token + \
                       '&wsfunction=' + aida_base + 'ucs_flows_tipo' + \
                       '&moodlewsrestformat=json' + \
                       '&ano_lectivo=' + lectyear + \
                       '&lista_ucs=' + base64.b64encode(course.encode("utf-8")).decode("utf-8")

        flows = requests.get(aida_endpoint).json()

        if flows:
            # para cada linha obtida
            for flow in flows:
                # em caso de correspondência do tipo de prova
                if flow['flow'] == evaltype:
                    type = flow['type']

        else:
            type = 'FLOWlock'

        # preenchimento das listas de queries
        # a executar na consulta
        table_queries.append(
                             read_query_template.format(
                                                        lectyear=lectyear,
                                                        course=course,
                                                        evaltype=evaltype,
                                                        season=season,
                                                        type=type,
                                                        dtfrom=dtfrom,
                                                        dur=dur
                                                       )
                            )

        # a executar na inserção
        commit_queries.append(
                              insert_query_template.format(
                                                           lectyear=lectyear,
                                                           course=course,
                                                           evaltype=evaltype,
                                                           season=season,
                                                           type=type,
                                                           dtfrom=dtfrom,
                                                           dur=dur
                                                          )
                             )

    loaded_values = []

    wf_db = connect_db()
    wf_db_cursor = wf_db.cursor(buffered=True)

    # para cada linha das listas das queries, preencher novas listas com os dados completos
    for query in table_queries:
        wf_db_cursor.execute(query)

        if wf_db_cursor.rowcount > 0:
            loaded_values.append(dict(zip(wf_db_cursor.column_names, wf_db_cursor.fetchone())))

    loaded_fields = list(wf_db_cursor.column_names)

    wf_db_cursor.close()
    wf_db.close()

    # verifica se o resultado é nulo
    if len(loaded_values) == 0:
        return None, None, None, "ERRO: UCs s/ dados p/ o ano lectivo identificado"

    return loaded_fields, loaded_values, commit_queries, None


# verificação do ficheiro WF
def wf_info(wf_file):
    timestamp = None
    flowtype = None
    
    stdid = None
    student = None

    flowid = None
    flow = None

    error = None

    # carregar linhas do ficheiro
    wf_file_content = wf_file.readlines()

    wf_db = connect_db()
    wf_db_cursor = wf_db.cursor(buffered=True)

    # ler linhas do ficheiro
    for row in wf_file_content:
        line = str(row)

        if "<timestamp>" in line: # FLOWlock
            flowtype = "FLOWlock"

            timestamp = int(line[line.find("<timestamp>") + 11:line.rfind("</timestamp>")])

            # antecipar linha seguinte
            line = str(wf_file_content[wf_file_content.index(row) + 1])

            submission = int(line[line.find("<pid>") + 5:line.rfind("</pid>")])

            wf_db_cursor.execute("SELECT flw.flowid \
                                  FROM wiseflow.flows flw \
                                      INNER JOIN wiseflow.flows_templates flw_tp ON flw_tp.id = flw.template \
                                  WHERE %s BETWEEN UNIX_TIMESTAMP(flw.dtfrom) AND UNIX_TIMESTAMP(flw.dtto) \
                                      AND flowtype_name = 'FLOWlock';",
                                 (timestamp, ))
            flows = wf_db_cursor.fetchall()

            # renovar o token se não estiver definido ou tiver expirado
            try:
                if session["wf_token"]["expires_in"] >= (int(datetime.datetime.now().timestamp()) - 180):
                    session["wf_token"] = get_wf_token()
            
            except KeyError:
                session["wf_token"] = get_wf_token()

            found = False

            # obter dados dos flows
            for flow in flows:
                # por cada flow, obter lista de participantes
                url = wf_base_url + "flows/" + flow[0] + "/participants?offset="

                offset = 0
                offseturl = url + str(offset)

                headers = {
                           'Accept': 'application/json',
                           'Content-Type': 'application/json',
                           'Authorization': session["wf_token"]['token_type'] + ' ' + session["wf_token"]['access_token']
                          }
                payload = {}

                participants = requests.request("GET", offseturl, headers=headers, data=payload).json().get('data')
                
                while participants:
                    # por cada participante, obter respectivos dados
                    for participant in participants:
                        if participant['submissionId'] == submission:
                            stdid = participant['user']['id']
                            flowid = flow[0]

                            found = True

                            break

                    if found:
                        break

                    else:
                        offset += len(participants)
                        offseturl = url + str(offset)

                        participants = requests.request("GET", offseturl, headers=headers, data=payload).json().get('data')

                if found:
                    break

            break

        elif "\"timestamp\"" in line: # FLOWmulti
            flowtype = "FLOWmulti"

            timestamp = int(line[line.find("\"timestamp\":") + 12:line.rfind(",\"participantId\"")])
            stdid = int(line[line.find("\"participantId\":") + 16:line.rfind(",\"flowId\"")])
            flowid = int(line[line.find("\"flowId\":") + 9:line.rfind(",\"responses\"")])

            break

    if stdid and flowid:
        wf_db_cursor.execute("SELECT CONCAT(std_num, ' | ', CONCAT(firstname, ' ', lastname)) AS student \
                              FROM wiseflow.students \
                              WHERE stdid = %s;",
                             (stdid, ))
        result = wf_db_cursor.fetchone()

        if result:
            student = result[0]

        wf_db_cursor.execute("SELECT CONCAT(subtitle, ' | ', title) AS flow \
                              FROM wiseflow.flows \
                              WHERE flowid = %s;",
                             (flowid, ))
        result = wf_db_cursor.fetchone()

        if result:
            flow = result[0]

    if not student or not flow:
        error = "(dados inválidos)"

    wf_db_cursor.close()
    wf_db.close()

    return datetime.datetime.fromtimestamp(timestamp, timezone).strftime('%Y-%m-%d %H:%M:%S'), flowtype, student, flow, error


# registo de actividade na app
def write_activity_log(action, target, criteria = None, userid = None, sessiontime = None, affecteduser = None):
    wf_db = connect_db()
    wf_db_cursor = wf_db.cursor()

    wf_db_cursor.execute("INSERT INTO log(action, target, criteria, userid, affecteduser, timestamp) \
                          VALUES(%s, %s, %s, %s, %s, %s);", 
                         (action, target, criteria, userid, affecteduser, int(datetime.datetime.now().timestamp()), ))
    wf_db.commit()

    wf_db_cursor.execute("UPDATE users \
                          SET T_usage = %s \
                          WHERE id = %s;",
                         (sessiontime, userid, ))
    wf_db.commit()

    wf_db_cursor.close()
    wf_db.close()


# registo de notificação por email
def write_mail_log(statusid, affecteduser, userid):
    wf_db = connect_db()
    wf_db_cursor = wf_db.cursor()

    wf_db_cursor.execute("INSERT INTO mail_log(statusid, affecteduser, userid, timestamp) \
                          VALUES(%s, %s, %s, %s);", 
                         (statusid, affecteduser, userid, int(datetime.datetime.now().timestamp()), ))
    wf_db.commit()

    wf_db_cursor.close()
    wf_db.close()


# expurgo de logs c/ antiguidade superior a 1 ano
def purge_logs():
    wf_db = connect_db()
    wf_db_cursor = wf_db.cursor()

    wf_db_cursor.execute("DELETE \
                          FROM wiseflow.log \
                          WHERE timestamp < UNIX_TIMESTAMP(NOW() - INTERVAL 1 YEAR);")
    wf_db.commit()

    wf_db_cursor.execute("DELETE \
                          FROM wiseflow.mail_log \
                          WHERE timestamp < UNIX_TIMESTAMP(NOW() - INTERVAL 1 YEAR);")
    wf_db.commit()

    wf_db_cursor.close()
    wf_db.close()


# mensagem de erro
def error_msg(message, code=400, back=None):
    def escape(s):
        
        # limpar caracteres especiais
        for old, new in [
            ("-", "--"),
            (" ", "-"),
            ("_", "__"),
            ("?", "~q"),
            ("%", "~p"),
            ("#", "~h"),
            ("/", "~s"),
            ('"', "''"),
        ]:
            s = s.replace(old, new)

        return s

    return render_template(ERROR_TPLT, top=code, bottom=escape(message), back=back), code


# limpar caracteres especiais
def sanitize(string):
    def replace_dirty_chars(string, dirty_chars, replacement):
        found_idxs = [idx for idx, char in enumerate(string.lower()) if char in dirty_chars]

        for idx in found_idxs:
            if string[idx].isupper():
                string = string.replace(string[idx], replacement.upper())

            else:
                string = string.replace(string[idx], replacement.lower())

        return string

    string = replace_dirty_chars(string, ['á', 'à', 'ã', 'â', 'ä', 'ª'], 'a')
    string = replace_dirty_chars(string, ['é', 'è', 'ê'], 'e')
    string = replace_dirty_chars(string, ['í', 'ï'], 'i')
    string = replace_dirty_chars(string, ['ó', 'ò', 'õ', 'ô', 'ö', 'º'], 'o')
    string = replace_dirty_chars(string, ['ú', 'ü'], 'u')
    string = replace_dirty_chars(string, ['ç'], 'c')
    string = replace_dirty_chars(string, ['ñ'], 'n')
    string = string.encode('ascii', 'ignore').decode('ascii', 'ignore')

    return string


# configuração da aplicação
app = Flask(__name__)

BUILD = "2026022706"  # YYYYMMDDHH

# SECRET_KEY
app.secret_key = "RaK7ng8-UCKWZnW6"

# configuração de sessão
app.config["SESSION_TYPE"] = "filesystem"
app.config["SESSION_PERMANENT"] = False
app.config["SESSION_USE_SIGNER"] = True
app.config["SESSION_FILE_DIR"] = os.path.join(os.getcwd(), "flask_session")
os.makedirs(app.config["SESSION_FILE_DIR"], exist_ok=True)

# cookies de sessão
# app.config["SESSION_COOKIE_PATH"] = "/wiser_py"
# app.config["SESSION_COOKIE_SECURE"] = True
app.config["SESSION_COOKIE_SAMESITE"] = "Lax"

Session(app)

# proxy + subpath
# app.config.update(
#     APPLICATION_ROOT="/wiser_py",
#     PREFERRED_URL_SCHEME="https"
# )
# app.wsgi_app = ProxyFix(app.wsgi_app, x_proto=1, x_host=1, x_prefix=1)


# assegura que as respostas não ficam em cache
@app.after_request
def after_request(response):
    response.headers["Cache-Control"] = "no-cache, no-store, must-revalidate"
    response.headers["Expires"] = 0
    response.headers["Pragma"] = "no-cache"

    return response


@app.context_processor
def inject_build():
    return dict(build=BUILD)


# setup inicial
# FIXME: garantir que toda a base de dados está criada
@app.route("/setup", methods=["GET", "POST"])
def setup():
    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        if request.form.get("setup") == "go":
            wf_db = connect_db()
            wf_db_cursor = wf_db.cursor()

            # criar tabela de utilizadores
            wf_db_cursor.execute("CREATE TABLE IF NOT EXISTS `users` ( \
                                      `id` int NOT NULL AUTO_INCREMENT, \
                                      `username` varchar(50) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL, \
                                      `hash` varchar(250) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL, \
                                      `setpwd` tinyint NOT NULL DEFAULT '1', \
                                      `admin` tinyint NOT NULL DEFAULT '0', \
                                      `flowman` tinyint NOT NULL DEFAULT '0', \
                                      `bioman` tinyint NOT NULL DEFAULT '0', \
                                      `statsman` tinyint NOT NULL DEFAULT '0', \
                                      `toolsusr` tinyint NOT NULL DEFAULT '0', \
                                      `sosusr` tinyint NOT NULL DEFAULT '0', \
                                      `firstlogin` bigint DEFAULT NULL, \
                                      `lastlogin` bigint DEFAULT NULL, \
                                      `T_usage` bigint DEFAULT '0', \
                                  PRIMARY KEY (`id`) \
                                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;")
            wf_db.commit()

            # criar administrador principal
            wf_db_cursor.execute("INSERT INTO users(username, email, hash, admin) \
                                  VALUES(%s, %s, %s, %s);",
                                 ("admin", "btavares.uab@gmail.com", def_pwd, 1, ))
            wf_db.commit()

            wf_db_cursor.close()
            wf_db.close()

            return redirect("/")

        # caso contrário, redireccionar para fora do sistema
        return redirect("https://www.google.com/")

    # caso contrário, redireccionar para a página de setup
    return render_template(SETUP_TPLT)


# autenticação
@app.route("/login", methods=["GET", "POST"])
def login():
    # eliminar a sessão do utilizador
    session.clear()

    if setup_required():
        return redirect("/setup")

    wf_db = connect_db()
    wf_db_cursor = wf_db.cursor()

    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        # se o utilizador não submeter username, retornar mensagem de erro
        if not request.form.get("username"):
            return error_msg("username necessário", 400, '/login')

        # se o utilizador não submeter password, retornar mensagem de erro
        elif not request.form.get("password"):
            return error_msg("password necessária", 400, '/login')

        # verificar se o username existe
        wf_db_cursor.execute("SELECT * \
                              FROM wiseflow.users \
                              WHERE username = %s;",
                             (request.form.get("username"), ))
        user = wf_db_cursor.fetchall()

        # assegurar que o username existe e a password está correta
        if len(user) != 1 or not check_password_hash(user[0][3], request.form.get("password")):
            return error_msg("username ou password inválidos", 401, '/login')

        # guardar sessão
        session["user_id"] = user[0][0]
        session["username"] = user[0][1]
        session["T_usage"] = user[0][13]
        session["time"] = int(datetime.datetime.now().timestamp())

        # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
        if set_pwd(session["user_id"]):
            return redirect("/change_pwd")
        
        if user[0][11] is None:
            wf_db_cursor.execute("UPDATE users \
                                  SET firstlogin = %s \
                                  WHERE id = %s;",
                                 (session["time"], session["user_id"], ))
            wf_db.commit()

        wf_db_cursor.execute("UPDATE users \
                              SET lastlogin = %s \
                              WHERE id = %s;",
                             (session["time"], session["user_id"], ))
        wf_db.commit()

        wf_db_cursor.close()
        wf_db.close()

        write_activity_log("login", "user", None, session["user_id"], session["T_usage"])

        # caso contrário, redireccionar para a página principal
        return redirect("/")

    # caso contrário, redireccionar para a página de autenticação
    return render_template(LOGIN_TPLT)


# encerrar sessão
@app.route("/logout")
def logout():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))

    write_activity_log("logout", "user", None, session["user_id"], session["T_usage"])

    # eliminar a sessão do utilizador
    session.clear()

    # redireccionar para a página principal
    return redirect("/")


# verifica se o utilizador é admin
@login_required
def is_admin(usrid):
    wf_db = connect_db()
    wf_db_cursor = wf_db.cursor()

    wf_db_cursor.execute("SELECT admin \
                          FROM users \
                          WHERE id = %s;",
                         (usrid, ))
    admin_flag = wf_db_cursor.fetchone()[0]

    wf_db_cursor.close()
    wf_db.close()

    return None if admin_flag == 0 else 1


# verifica se o utilizador é gestor de fluxos
@login_required
def is_flowman(usrid):
    wf_db = connect_db()
    wf_db_cursor = wf_db.cursor()

    wf_db_cursor.execute("SELECT flowman \
                          FROM users \
                          WHERE id = %s;",
                         (usrid, ))
    flowman_flag = wf_db_cursor.fetchone()[0]

    wf_db_cursor.close()
    wf_db.close()

    return None if flowman_flag == 0 else 1


# verifica se o utilizador é gestor de dados biométricos
@login_required
def is_bioman(usrid):
    wf_db = connect_db()
    wf_db_cursor = wf_db.cursor()

    wf_db_cursor.execute("SELECT bioman \
                          FROM users \
                          WHERE id = %s;",
                         (usrid, ))
    bioman_flag = wf_db_cursor.fetchone()[0]

    wf_db_cursor.close()
    wf_db.close()

    return None if bioman_flag == 0 else 1


# verifica se o utilizador é gestor de indicadores estatísticos
@login_required
def is_statsman(usrid):
    wf_db = connect_db()
    wf_db_cursor = wf_db.cursor()

    wf_db_cursor.execute("SELECT statsman \
                          FROM users \
                          WHERE id = %s;",
                         (usrid, ))
    statsman_flag = wf_db_cursor.fetchone()[0]

    wf_db_cursor.close()
    wf_db.close()

    return None if statsman_flag == 0 else 1


# verifica se o utilizador é utilizador de utilitários
@login_required
def is_toolsusr(usrid):
    wf_db = connect_db()
    wf_db_cursor = wf_db.cursor()

    wf_db_cursor.execute("SELECT toolsusr \
                          FROM users \
                          WHERE id = %s;",
                         (usrid, ))
    toolsusr_flag = wf_db_cursor.fetchone()[0]

    wf_db_cursor.close()
    wf_db.close()

    return None if toolsusr_flag == 0 else 1


# verifica se o utilizador é utilizador de SOS
@login_required
def is_sosusr(usrid):
    wf_db = connect_db()
    wf_db_cursor = wf_db.cursor()

    wf_db_cursor.execute("SELECT sosusr \
                          FROM users \
                          WHERE id = %s;",
                         (usrid, ))
    sosusr_flag = wf_db_cursor.fetchone()[0]

    wf_db_cursor.close()
    wf_db.close()

    return None if sosusr_flag == 0 else 1


# menu Sentinel_F
@app.route("/sentinel_f")
@login_required
def sentinel_f():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    # caso o utilizador não tenha privilégios de SOSusr ou de admin, redireccionar para a página principal
    if is_sosusr(session["user_id"]) is None and is_admin(session["user_id"]) is None:
        return redirect("/")

    write_activity_log("list", "Sentinel_F menu", None, session["user_id"], session["T_usage"])

    # caso contrário, redireccionar para a página de selecção
    return render_template(SENTINEL_F_TPLT,
                           admin=is_admin(session["user_id"]),
                           sosusr=is_sosusr(session["user_id"]))


# menu de eventos Sentinel_F
@app.route("/sentinelf_events_2check", methods=["GET", "POST"])
@login_required
def sentinelf_events_2check():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    # caso o utilizador não tenha privilégios de admin, redireccionar para a página principal
    if is_admin(session["user_id"]) is None:
        return redirect("/")

    def clean_results():
        session["result"] = None

    wf_db = connect_db()
    wf_db_cursor = wf_db.cursor()

    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        # registar novo evento
        if request.form.get("new") is not None:
            # se o utilizador não submeter evento e payload, retornar mensagem de erro
            if not request.form.get("evt_type") or \
                not request.form.get("payload"):
                return error_msg("tipo de evento e payload necessários", 400, '/sentinelf_events_2check')

            evt_type = request.form.get("evt_type").upper()
            payload = request.form.get("payload")
            if payload == None or payload.strip() == "":
                payload = None

            # verificar se o binómio tipo de evento + payload já existe
            wf_db_cursor.execute("SELECT * \
                                  FROM wiseflow.sentinelf_event_types \
                                  WHERE type = %s \
                                      AND payload = %s;",
                                 (evt_type, payload, ))
            evt_pl = wf_db_cursor.fetchall()

            if len(evt_pl) != 0:
                # caso exista, retornar mensagem de erro
                return error_msg("o binómio tipo de evento + payload já existe", 400, '/sentinelf_events_2check')

            # adicionar o novo tipo de evento à base de dados
            wf_db_cursor.execute("INSERT INTO sentinelf_event_types(type, payload, report) \
                                  VALUES(%s, %s, %s);",
                                 (evt_type, payload, 1, ))
            wf_db.commit()

            write_activity_log("create", "Sentinel_F event_type", evt_type, session["user_id"], session["T_usage"])

            clean_results()

        # eliminação de evento
        elif request.form.get("del") is not None:
            wf_db_cursor.execute("DELETE \
                                  FROM sentinelf_event_types \
                                  WHERE type = %s",
                                 (request.form.get("del"), ))
            wf_db.commit()

            write_activity_log("delete", "Sentinel_F event_type", request.form.get("del"), session["user_id"], session["T_usage"])

            clean_results()

        # definição das configurações do evento
        else:
            i = 0

            for row in session["result"]:
                row_id = row[0]
                evt_type = request.form.get(f"type{row_id}")
                payload = request.form.get(f"payload{row_id}")
                if payload == None or payload.strip() == "":
                    payload = None
                report = 1 if f"report{row_id}" in request.form else 0

                if evt_type is not None:
                    wf_db_cursor.execute("UPDATE wiseflow.sentinelf_event_types \
                                          SET type=%s, payload=%s, report=%s \
                                          WHERE id=%s",
                                         (evt_type, payload, report, row_id, ))
                    wf_db.commit()

                    i += wf_db_cursor.rowcount

            if i > 0:
                write_activity_log("update", "Sentinel_F events", None, session["user_id"], session["T_usage"])

            clean_results()

    # caso contrário, redireccionar para a lista de eventos
    wf_db_cursor.execute("SELECT * \
                          FROM wiseflow.sentinelf_event_types \
                          ORDER BY type, payload;")
    session["result"] = wf_db_cursor.fetchall()

    wf_db_cursor.close()
    wf_db.close()

    write_activity_log("list", "Sentinel_F events", None, session["user_id"], session["T_usage"])

    return render_template(SENTINELF_EVENTS_TPLT, is_admin=is_admin(session["user_id"]), event_types=session["result"])


# reports log Sentinel_F
@app.route("/sentinelf_reported", methods=["GET", "POST"])
@login_required
def sentinelf_reported():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())
    info = "reported"

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    # caso o utilizador não tenha privilégios de SOSusr ou de admin, redireccionar para a página principal
    if is_sosusr(session["user_id"]) is None and is_admin(session["user_id"]) is None:
        return redirect("/")

    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        wf_db = connect_db()
        wf_db_cursor = wf_db.cursor()

        if request.form.get("events") == 'by_date':
            date = None
            if request.form.get("date") != '':
                date = request.form.get("date")
                start = date + " 00:00:00"
                end = date + " 23:59:59"
                criteria = "WHERE timestamp BETWEEN '"   + start + "' AND '" + end + "'"
                key = "data: " + date

            else:
                criteria = ""
                key = "data: todas"

            query = "SELECT flw.subtitle, \
                            flw.flowid, \
                            std.std_num, \
                            evts.timestamp, \
                            evts.type, \
                            evts.payload, \
                            evts.report \
                     FROM wiseflow.sentinelf_reported evts \
                         INNER JOIN wiseflow.flows flw ON flw.flowid = evts.flowid \
                         INNER JOIN wiseflow.students std ON std.stdid = evts.stdid " \
                   + criteria + " \
                     ORDER BY evts.timestamp ASC;"

            wf_db_cursor.execute(query)
            result = wf_db_cursor.fetchall()

            write_activity_log("list", "Sentinel_F reported", key, session["user_id"], session["T_usage"])

            return render_template(SENTINELF_LOG_TPLT, is_admin=is_admin(session["user_id"]), info=info, log=result, key=key)

        elif request.form.get("events") == 'by_student':
            if not request.form.get("student"):
                return error_msg("número de estudante necessário", 400, '/sentinelf_reported')

            student = request.form.get("student")
            key = ("std_" + str(student))

            query = "SELECT flw_std.firstname, \
                            flw_std.lastname, \
                            flw_std.std_num, \
                            flw_std.stdid, \
                            flw.title, \
                            flw.subtitle, \
                            flw.flowid, \
                            flw_tmpl.flowtype_name AS 'flow_type', \
                            flw.dtfrom, \
                            flw.dtto, \
                            flw_ass.dtass AS 'dtpart', \
                            flw_ass.status AS 'flow_status', \
                            rep.type, \
                            COUNT(rep.type) AS 'N' \
                     FROM wiseflow.sentinelf_reported rep \
                         INNER JOIN wiseflow.students flw_std ON flw_std.stdid = rep.stdid \
                         INNER JOIN wiseflow.flows_assess flw_ass ON (flw_ass.stdid = flw_std.stdid AND flw_ass.stdid = rep.stdid) \
                         INNER JOIN wiseflow.flows flw ON (flw.flowid = flw_ass.flowid AND flw.flowid = rep.flowid) \
                         INNER JOIN wiseflow.flows_templates flw_tmpl ON flw_tmpl.id = flw.template \
                     WHERE flw_std.std_num = '"   + student + "' \
                     GROUP BY flw.flowid, rep.type \
                     ORDER BY flw.dtfrom, rep.type;"

            wf_db_cursor.execute(query)
            result = wf_db_cursor.fetchall()

            if len(result) == 0:
                return error_msg("estudante não encontrado", 404, '/sentinelf_reported')

            # base do link directo para o flow de origem
            flw_base_url = "https://europe.wiseflow.net/manager/display.php?id="

            # inicialização do gráfico
            graph = go.Figure()

            EVENT_COLORS = {
                'ASSIGNMENT_ACCESSED':  'rgb(255,255,0)',
                'CHARACTERS_TYPED':  'rgb(83,127,190)',
                'FACIAL_RECOGNITION':  'rgb(255,182,193)',
                'FLOW_OPENED':  'rgb(255,140,0)',
                'HEARTBEAT':  'rgb(144,238,144)',
                'INACTIVITY':  'rgb(240,128,128)',
                'INDIVIDUAL_DEADLINE':  'rgb(186,85,211)',
                'LOG':  'rgb(70,130,180)',
                'PAPER_HANDED_IN':  'rgb(255,215,0)',
                'PAPER_SAVED':  'rgb(60,179,113)',
                'PAPER_WITHDRAWN': 'rgb(220,20,60)',
                'PARTICIPANT_PRESENCE': 'rgb(0,191,255)',
                'PARTICIPATION_PAUSE': 'rgb(255,99,71)',
                'PLATAFORMABERTA_ACCESS': 'rgb(255,0,0)',
                'PROCTOR_PASSWORD_CORRECT': 'rgb(154,205,50)',
                'PROGRESS': 'rgb(199,21,133)',
            }

            flows = {}
            data_by_type = defaultdict(list)

            for row in result:
                flow_id = row[6]
                event_type = str(row[12])
                count = row[13]

                # etiqueta do flow
                if flow_id not in flows:
                    flows[flow_id] = (
                        f'<a href="' + flw_base_url + str(row[6]) + '" target="_blank">'
                        + str(row[5])
                        + '<br>('
                        + str(row[7])
                        + '<br>'
                        + str(datetime.datetime.strptime(row[8], "%Y-%m-%d %H:%M:%S").date())
                        + ')</a>'
                    )

                data_by_type[event_type].append((flow_id, count))

            flow_ids = list(flows.keys())
            flow_labels = [flows[fid] for fid in flow_ids]

            for event_type, values in data_by_type.items():
                y = [0] * len(flow_ids)

                for fid, count in values:
                    y[flow_ids.index(fid)] = count

                graph.add_trace(
                    go.Bar(
                        name=f'{event_type}',
                        x=flow_labels,
                        y=y,
                        marker_color=EVENT_COLORS.get(event_type, 'rgb(128,128,128)')
                    )
                )

            graph.update_layout(
                title=str(result[0][2]) + " | " + str(result[0][0]) + " " + str(result[0][1]),
                xaxis_title='flows p/ data',
                yaxis_title='T_ocorrências',
                barmode='group',
                bargap=0.15,
                bargroupgap=0.05,
                showlegend=True,
                legend_title='eventos'
            )

            # guardar o gráfico na sessão e num ficheiro temporário
            session["tmp_graph_file"] = "tmp/usrID" + str(session["user_id"]) + "_output_file_name.html"
            graph.write_html(session["tmp_graph_file"])

            write_activity_log("list", "Sentinel_F graph", key, session["user_id"], session["T_usage"])

            return render_template(USER_GRAPH_TPLT, graph="https://mdlws.uab.pt/wiser_py/" + session["tmp_graph_file"], user_monitor="True")

        wf_db_cursor.close()
        wf_db.close()

    # caso contrário, redireccionar para a página de selecção
    write_activity_log("list", "Sentinel_F reported", None, session["user_id"], session["T_usage"])

    return render_template(SENTINELF_LOG_TPLT, is_admin=is_admin(session["user_id"]), info=info, search="True")


# full log Sentinel_F
@app.route("/sentinelf_log", methods=["GET", "POST"])
@login_required
def sentinelf_log():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())
    info = "full"

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    # caso o utilizador não tenha privilégios de SOSusr ou de admin, redireccionar para a página principal
    if is_sosusr(session["user_id"]) is None and is_admin(session["user_id"]) is None:
        return redirect("/")

    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        date = None
        if request.form.get("date") != '':
            date = request.form.get("date")
            start = date + " 00:00:00"
            end = date + " 23:59:59"
            key = "data: " + date

        else:
            return error_msg("data necessária", 400, '/sentinelf_log')

        query = "SELECT flw.subtitle, \
                        flw.flowid, \
                        std.std_num, \
                        evts.timestamp, \
                        evts.type, \
                        evts.payload, \
                        evts.report \
                 FROM wiseflow.sentinelf_events evts \
                     INNER JOIN wiseflow.flows flw ON flw.flowid = evts.flowid \
                     INNER JOIN wiseflow.students std ON std.stdid = evts.stdid \
                 WHERE timestamp BETWEEN '"   + start + "' AND '" + end + "' \
                 ORDER BY evts.timestamp ASC;"

        wf_db = connect_db()
        wf_db_cursor = wf_db.cursor()

        wf_db_cursor.execute(query)
        result = wf_db_cursor.fetchall()

        wf_db_cursor.close()
        wf_db.close()

        write_activity_log("list", "Sentinel_F log", key, session["user_id"], session["T_usage"])

        return render_template(SENTINELF_LOG_TPLT, is_admin=is_admin(session["user_id"]), info=info, log=result, key=key)

    # caso contrário, redireccionar para a página de selecção
    write_activity_log("list", "Sentinel_F log", None, session["user_id"], session["T_usage"])

    return render_template(SENTINELF_LOG_TPLT, is_admin=is_admin(session["user_id"]), info=info, search="True")


# menu de configurações Sentinel_F
@app.route("/sentinelf_settings", methods=["GET", "POST"])
@login_required
def sentinelf_settings():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    # caso o utilizador não tenha privilégios de admin, redireccionar para a página principal
    if is_admin(session["user_id"]) is None:
        return redirect("/")

    def clean_results():
        session["result"] = None

    wf_db = connect_db()
    wf_db_cursor = wf_db.cursor()

    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        # definição das configurações
        for row in session["result"]:
            row_id = row[0]
            mailbox = request.form.get(f"mailbox{row_id}")

            if mailbox is not None:
                wf_db_cursor.execute("UPDATE wiseflow.sentinelf_settings \
                                      SET value=%s \
                                      WHERE id=%s",
                                     (mailbox, row_id, ))
                wf_db.commit()

        if wf_db_cursor.rowcount > 0:
            write_activity_log("update", "Sentinel_F settings", None, session["user_id"], session["T_usage"])

        clean_results()

    # caso contrário, redireccionar para a página de selecção
    wf_db_cursor.execute("SELECT * \
                          FROM wiseflow.sentinelf_settings \
                          WHERE setting = 'lastrun';")
    lastrun = wf_db_cursor.fetchone()[2]

    wf_db_cursor.execute("SELECT * \
                          FROM wiseflow.sentinelf_settings \
                          WHERE setting <> 'lastrun';")
    session["result"] = wf_db_cursor.fetchall()

    wf_db_cursor.close()
    wf_db.close()

    write_activity_log("list", "Sentinel_F settings", None, session["user_id"], session["T_usage"])

    return render_template(SENTINELF_SETTINGS_TPLT, is_admin=is_admin(session["user_id"]), settings=session["result"], lastrun=lastrun)


# menu utilitários
@app.route("/tools")
@login_required
def tools():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    # caso o utilizador não tenha privilégios de toolsusr ou admin, redireccionar para a página principal
    if is_toolsusr(session["user_id"]) is None and is_admin(session["user_id"]) is None:
        return redirect("/")

    write_activity_log("list", "tools", None, session["user_id"], session["T_usage"])

    # carregar opções de utilizador, em funçao dos privilégios
    return render_template(TOOLS_TPLT,
                           admin=is_admin(session["user_id"]),
                           flowman=is_flowman(session["user_id"]),
                           sosusr=is_sosusr(session["user_id"])
                          )


# módulo p/ carregamento do calendário lectivo
@app.route("/calendar", methods=["GET", "POST"])
@login_required
def calendar():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    # caso o utilizador não tenha privilégios de toolsusr, flowman ou admin, redireccionar para a página principal
    if is_toolsusr(session["user_id"]) is None and is_flowman(session["user_id"]) is None and is_admin(session["user_id"]) is None:
        return redirect("/")

    def clean_results():
        session["filename"] = None
        session["result"] = None

    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        if request.form.get("commit") is not None and session["result"] is not None:
            wf_db = connect_db()
            wf_db_cursor = wf_db.cursor()

            for query in session["result"]:
                # TODO: viabilizar actualização condicional | se template for o mesmo, actualizar; se for diferente, apagar anterior e inserir novo
                insert = "INSERT INTO wiseflow.flows (courseid, lectyear, evaltype, season, title, subtitle, flowid, template, dtfrom, dtto, dur, log, to_eval, assign, author) " + \
                          query

                wf_db_cursor.execute(insert)
                wf_db.commit()

            wf_db_cursor.close()
            wf_db.close()

            write_activity_log("commit", "cal_csv", session["filename"], session["user_id"], session["T_usage"])

            clean_results()
            
            return render_template(CAL_TPLT, info="INFO: dados inseridos com sucesso")

        else:
            if "csv" not in request.files:
                return error_msg("ficheiro necessário", 400, '/calendar')

            file = request.files["csv"]
            session["filename"] = file.filename

            table_header, table_data, session["result"], error = fill_csv(file)

            write_activity_log("load", "cal_csv", session["filename"], session["user_id"], session["T_usage"])

            return render_template(CAL_TPLT, table_header=table_header, table_data=table_data, commit_data=session["result"], error=error)

    write_activity_log("get", "cal_csv", None, session["user_id"], session["T_usage"])

    # caso contrário, redireccionar para a página de selecção
    return render_template(CAL_TPLT)


# verificar ficheiro
@app.route("/check_wf_file", methods=["GET", "POST"])
@login_required
def check_wf_file():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    # caso o utilizador não tenha privilégios de toolsusr ou admin, redireccionar para a página principal
    if is_toolsusr(session["user_id"]) is None and is_admin(session["user_id"]) is None:
        return redirect("/")

    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        if "wf" not in request.files:
            return error_msg("ficheiro necessário", 400, '/check_wf_file')

        file = request.files["wf"]

        timestamp, flowtype, student, flow, error = wf_info(file)

        write_activity_log("check", "wf_file", file.filename, session["user_id"], session["T_usage"])

        return render_template(WF_TPLT, file=file, timestamp=timestamp, flowtype=flowtype, student=student, flow=flow, error=error)

    write_activity_log("upload", "wf_file", None, session["user_id"], session["T_usage"])

    # caso contrário, redireccionar para a página de selecção
    return render_template(WF_TPLT)


# configurar notificações
@app.route("/mail_settings", methods=["GET", "POST"])
@login_required
def mail_settings():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    # caso o utilizador não tenha privilégios de toolsusr, flowman ou admin, redireccionar para a página principal
    if is_toolsusr(session["user_id"]) is None and is_flowman(session["user_id"]) is None and is_admin(session["user_id"]) is None:
        return redirect("/")

    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        wf_db = connect_db()
        wf_db_cursor = wf_db.cursor()

        if request.form.get("mail_conf") == "tools":
            if request.form.get("save_server") is not None:
                if request.form.get("port") != "":
                    port= request.form["port"]

                else:
                    port = None

                if request.form.get("username") != "":
                    username= request.form["username"]

                else:
                    username = None

                if request.form.get("password") != "":
                    password= request.form["password"]

                else:
                    password = None

                if request.form.get("mail_server_id") is not None \
                    and request.form.get("mail_server_id") != "":
                    # editar entrada
                    wf_db_cursor.execute("UPDATE wiseflow.mail_server \
                                          SET server = %s, port = %s, username = %s, password = %s, mailbox = %s \
                                          WHERE id = %s;",
                                         (request.form.get("server"), port, username, password, request.form.get("mailbox"), request.form.get("mail_server_id"), ))
                    wf_db.commit()

                else:
                    # adicionar o novo servidor à base de dados
                    wf_db_cursor.execute("INSERT INTO wiseflow.mail_server(purpose, server, port, username, password, mailbox) \
                                          VALUES('std_notify', %s, %s, %s, %s, %s) \
                                          ON DUPLICATE KEY UPDATE \
                                              server = VALUES(server), \
                                              port = VALUES(port), \
                                              username = VALUES(username), \
                                              password = VALUES(password), \
                                              mailbox = VALUES(mailbox);",
                                         (request.form.get("server"), port, username, password, request.form.get("mailbox"), ))
                    wf_db.commit()

                write_activity_log("set", "mail_settings", 'mail_server', session["user_id"], session["T_usage"])

            if request.form.get("sect") is not None and \
                request.form.get("sect") == "save_template":
                if request.form.get("op") == "new":
                    # registar nova entrada
                    wf_db_cursor.execute("INSERT INTO wiseflow.mail_templates(status, body) \
                                          VALUES(%s, %s) \
                                          ON DUPLICATE KEY UPDATE \
                                              body = VALUES(body);",
                                         (request.form.get("selection"), request.form.get("text"), ))
                    wf_db.commit()

                    write_activity_log("new", "mail_template", request.form.get("selinfo"), session["user_id"], session["T_usage"])

                elif request.form.get("op") == "updt":
                    # actualizar entrada
                    wf_db_cursor.execute("UPDATE wiseflow.mail_templates \
                                          SET status = %s, body = %s \
                                          WHERE status = %s;",
                                         (request.form.get("selection"), request.form.get("text"), request.form.get("rec"), ))
                    wf_db.commit()

                    write_activity_log("updt", "mail_template", request.form.get("selinfo"), session["user_id"], session["T_usage"])

                elif request.form.get("op") == "del":
                    # eliminar entrada
                    wf_db_cursor.execute("DELETE \
                                          FROM wiseflow.mail_templates \
                                          WHERE status = %s;",
                                         (request.form.get("rec"), ))
                    wf_db.commit()

                    write_activity_log("del", "mail_template", request.form.get("selinfo"), session["user_id"], session["T_usage"])

            wf_db_cursor.execute("SELECT * \
                                  FROM wiseflow.mail_server \
                                  WHERE purpose = 'std_notify';")
            mail_server = wf_db_cursor.fetchone()

            wf_db_cursor.execute("SELECT @counter:=IF(@counter IS NULL, 0, @counter) + 1 AS nMsg, \
                                         sta.id, \
                                         sta.status, \
                                         msg.body \
                                  FROM wiseflow.mail_templates msg \
                                      INNER JOIN wiseflow.flows_assess_status sta ON sta.id = msg.status;")
            mail_templates = wf_db_cursor.fetchall()

            wf_db_cursor.execute("SELECT sta.id, sta.status \
                                  FROM wiseflow.flows_assess_status sta \
                                      LEFT JOIN wiseflow.mail_templates msg ON msg.status = sta.id \
                                  WHERE msg.id IS NULL;")
            null_flw_assess_status = wf_db_cursor.fetchall()

            return render_template(MAIL_SETTINGS_TPLT, mail_server=mail_server, tools="True", status=null_flw_assess_status, templates=mail_templates)

        elif request.form.get("mail_conf") == "users":
            if request.form.get("save_server") is not None:
                if request.form.get("port") != "":
                    port= request.form["port"]

                else:
                    port = None

                if request.form.get("username") != "":
                    username= request.form["username"]

                else:
                    username = None

                if request.form.get("password") != "":
                    password= request.form["password"]

                else:
                    password = None

                if request.form.get("mail_server_id") is not None \
                    and request.form.get("mail_server_id") != "":
                    # editar entrada
                    wf_db_cursor.execute("UPDATE wiseflow.mail_server \
                                          SET server = %s, port = %s, username = %s, password = %s, mailbox = %s \
                                          WHERE id = %s;",
                                         (request.form.get("server"), port, username, password, request.form.get("mailbox"), request.form.get("mail_server_id"), ))
                    wf_db.commit()

                else:
                    # adicionar o novo servidor à base de dados
                    wf_db_cursor.execute("INSERT INTO wiseflow.mail_server(purpose, server, port, username, password, mailbox) \
                                          VALUES('usr_pwd', %s, %s, %s, %s, %s) \
                                          ON DUPLICATE KEY UPDATE \
                                              server = VALUES(server), \
                                              port = VALUES(port), \
                                              username = VALUES(username), \
                                              password = VALUES(password), \
                                              mailbox = VALUES(mailbox);",
                                         (request.form.get("server"), port, username, password, request.form.get("mailbox"), ))
                    wf_db.commit()

                write_activity_log("set", "mail_settings", 'mail_server', session["user_id"], session["T_usage"])

            wf_db_cursor.execute("SELECT * \
                                  FROM wiseflow.mail_server \
                                  WHERE purpose = 'usr_pwd';")
            mail_server = wf_db_cursor.fetchone()

            return render_template(MAIL_SETTINGS_TPLT, mail_server=mail_server, users="True")

        wf_db_cursor.close()
        wf_db.close()

        write_activity_log("list", "mail_settings", None, session["user_id"], session["T_usage"])

    return redirect("/")


# módulo SOS
@app.route("/sos")
@login_required
def sos():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    # caso o utilizador não tenha privilégios de SOSusr ou admin, redireccionar para a página principal
    if is_sosusr(session["user_id"]) is None and is_admin(session["user_id"]) is None:
        return redirect("/")

    write_activity_log("list", "sos", None, session["user_id"], session["T_usage"])

    # carregar opções de utilizador, em funçao dos privilégios
    return render_template(SOS_TPLT,
                           admin=is_admin(session["user_id"]),
                           flowman=is_flowman(session["user_id"])
                          )


# TODO: criar módulo para desbloqueio unitário no SiGES
# módulo reset sync BDInt->SiGES
@app.route("/reset_sync", methods=["GET", "POST"])
@login_required
def reset_sync():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    # caso o utilizador não tenha privilégios de SOSusr ou admin, redireccionar para a página principal
    if is_sosusr(session["user_id"]) is None and is_admin(session["user_id"]) is None:
        return redirect("/")
    
    # obter os anos lectivos disponíveis na base de dados
    wf_db = connect_db()
    wf_db_cursor = wf_db.cursor(buffered=True)

    wf_db_cursor.execute(build_query('wf', None, None, None, None, None, 1, None))
    # wf_db.commit()

    lect_year = wf_db_cursor.fetchall()

    wf_db_cursor.close()
    wf_db.close()

    key = ""

    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        # se o utilizador não submeter o ano lectivo, retornar mensagem de erro
        if request.form.get("lect_year") == "ano_lectivo":
            return error_msg("ano lectivo necessário", 400, '/reset_sync')

        key += request.form.get("lect_year")

        # se o utilizador não submeter lista de UCs, retornar mensagem de erro
        if request.form.get("lista_ucs").strip() == "":
            return error_msg("lista de UCs necessária", 400, '/reset_sync')

        ucs_list = [m.strip() for m in request.form.get("lista_ucs", "").split(",") if m.strip()]
        ucs_placeholders = ','.join(['%s'] * len(ucs_list))

        key += " | UCs: " + ",".join(ucs_list)

        query = f"""UPDATE lead.alunos_inscricoes \
                    SET STATUS_AVALIA = 'M' \
                    WHERE CD_DISCIP IN ({ucs_placeholders}) \
                        AND CD_LECTIVO = %s
                        AND STATUS_AVALIA = 'A'"""
        params = ucs_list + [request.form.get("lect_year")]

        if request.form.get("lista_stds").strip() != "":
            stds_list = [m.strip() for m in request.form.get("lista_stds", "").split(",") if m.strip()]
            stds_placeholders = ','.join(['%s'] * len(stds_list))

            key += " | stds: " + ",".join(stds_list)

            query += f" AND CD_ALUNO IN ({stds_placeholders})"

            params += stds_list

        wf_db = connect_db()
        wf_db_cursor = wf_db.cursor(buffered=True)

        wf_db_cursor.execute(query, params)
        wf_db.commit()

        grds = wf_db_cursor.rowcount

        wf_db_cursor.close()
        wf_db.close()

        info = "foi reiniciada a sincronização de " + str(grds) + " notas das UCs " + str(ucs_list) + " no ano lectivo " + request.form.get("lect_year")

        write_activity_log("reset", "sync", key, session["user_id"], session["T_usage"])

        return render_template(RESET_SYNC_TPLT, lect_year=lect_year, info=info)

    write_activity_log("get", "sync", None, session["user_id"], session["T_usage"])

    # caso contrário, carregar página de pesquisa
    return render_template(RESET_SYNC_TPLT, lect_year=lect_year)


# gestão de flows: extensão de tempo e remoção de participantes
@app.route("/manage_flow", methods=["GET", "POST"])
@login_required
def manage_flow():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    # caso o utilizador não tenha privilégios de SOSusr ou admin, redireccionar para a página principal
    if is_sosusr(session["user_id"]) is None and is_admin(session["user_id"]) is None:
        return redirect("/")

    def clean_results():
        session["key"] = None
        session["result"] = None
        session["fields"] = None

    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        if request.form.get("export") is not None and session["result"] is not None:
            csv_file = export_to_csv(session["result"], session["fields"], ("tmp/usrID" + str(session["user_id"]) + "_" + session["key"] + "_xtraTime.csv"))

            write_activity_log("export", "flow_xtraTime", session["key"], session["user_id"], session["T_usage"])

            clean_results()
            
            return render_template(MANAGE_FLOW_TPLT, download="True", file_url=csv_file["url"], file_name=csv_file["filename"])

        else:
            # se o utilizador não submeter o flowid, retornar mensagem de erro
            if not request.form.get("flowid"):
                return error_msg("flowid necessário", 400, '/manage_flow')
            
            flowid = request.form.get("flowid")

            if request.form.get("function") == "no_function":
                return error_msg("função necessária", 400, '/manage_flow')

            if request.form.get("function") == "extend_flow":
                if not request.form.get("extra_time"):
                    return error_msg("tempo adicional necessário", 400, '/manage_flow')
                
                xtra = request.form.get("extra_time")

                # executar o endpoint e exibir o resultado
                parts_list = extend_flow_time(flowid, xtra)
                parts_placeholders = ','.join(['%s'] * len(parts_list))

                if parts_list:
                    info = "foram adicionados " + str(xtra) + " minutos aos participantes do flow " + flowid

                    wf_db = connect_db()
                    wf_db_cursor = wf_db.cursor()

                    query = f"""SELECT s.firstname, s.lastname, s.std_num \
                                FROM wiseflow.flows_assess fa \
                                    INNER JOIN wiseflow.students s ON s.stdid = fa.stdid \
                                WHERE partid IN ({parts_placeholders}) \
                                    AND flowid = %s;"""
                    params = parts_list + [flowid]
                    wf_db_cursor.execute(query, params)
                    
                    session["key"] = "flowID_" + str(flowid)
                    session["result"] = wf_db_cursor.fetchall()
                    session["fields"] = wf_db_cursor.column_names

                    wf_db_cursor.close()
                    wf_db.close()

                    write_activity_log("apply", "flow_xtraTime", session["key"], session["user_id"], session["T_usage"])

                else:
                    info = "não foi possível estender o tempo do flow " + flowid

                return render_template(MANAGE_FLOW_TPLT, info=info, result=session["result"])
            
            elif request.form.get("function") == "purge_flow":
                # executar o endpoint e exibir o resultado
                i, j = remove_flow_parts(flowid)

                if i > 0:
                    info = "foram removidos " + str(j) + " participantes, de um total de " + str(i) + ", do flow " + flowid

                    write_activity_log("purge", "flow_participants", ("flowID_" + str(flowid)), session["user_id"], session["T_usage"])

                else:
                    info = "não foram encontrados participantes para remover do flow " + flowid

                return render_template(MANAGE_FLOW_TPLT, info=info, result=None)

    write_activity_log("get", "manage_flow", None, session["user_id"], session["T_usage"])

    # caso contrário, redireccionar para a página de selecção
    return render_template(MANAGE_FLOW_TPLT)


# envio de e-mails BCC e noreply
@app.route("/no_reply", methods=["GET", "POST"])
@login_required
def no_reply():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    # caso o utilizador não tenha privilégios de SOSusr ou admin, redireccionar para a página principal
    if (is_sosusr(session["user_id"]) is None or (is_sosusr(session["user_id"]) and is_flowman(session["user_id"]) is None)) and is_admin(session["user_id"]) is None:
        return redirect("/")

    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        if (request.form.get("mailboxes") is None or request.form.get("mailboxes") == "") \
            or (request.form.get("subject") is None or request.form.get("subject") == "") \
            or (request.form.get("raw_message") is None or request.form.get("raw_message")) == "":
            return error_msg("e-mail incompleto", 400, '/no_reply')

        wf_db = connect_db()
        wf_db_cursor = wf_db.cursor()

        wf_db_cursor.execute("SELECT server, port, username, password, mailbox \
                              FROM wiseflow.mail_server \
                              WHERE purpose = 'std_notify';")
        mail_server = wf_db_cursor.fetchone()

        server = mail_server[0]

        if mail_server[1] is not None:
            port = mail_server[1]
        else:
            port = None

        if mail_server[2] is not None:
            username = mail_server[2]
        else:
            username = None

        if mail_server[3] is not None:
            password = mail_server[3]
        else:
            password = None

        sender = mail_server[4]
        manager = 'sle@uab.pt'
        bcc = []

        # mailboxes = request.form.get("mailboxes").split(";")
        mailboxes = [m.strip() for m in request.form.get("mailboxes", "").split(";") if m.strip()]
        subject = request.form.get("subject")
        message = request.form.get("html_message")

        send_mail(server, sender, subject, message, None, mailboxes, None, port, username, password)

        mailboxes_placeholders = ','.join(['%s'] * len(mailboxes))

        query = f"""SELECT s.firstname, s.lastname, s.std_num \
                    FROM wiseflow.students s \
                    WHERE email IN ({mailboxes_placeholders});"""
        wf_db_cursor.execute(query, mailboxes)
        
        students = wf_db_cursor.fetchall()
        headers = wf_db_cursor.column_names

        wf_db_cursor.close()
        wf_db.close()

        template = Template("""
                               <style>
                                   table.teal-table {
                                                     border-collapse: collapse;
                                                     width: 100%;
                                                     font-family: Arial, sans-serif;
                                                     box-shadow: 0 4px 12px rgba(0,0,0,0.08);
                                                     border-radius: 8px;
                                                     overflow: hidden;
                                                    }
                                   table.teal-table thead {
                                                           background-color: #38B2AC;
                                                           color: white;
                                                          }
                                   table.teal-table th,
                                   table.teal-table td {
                                                        padding: 12px;
                                                        text-align: left;
                                                        color: #234E52;
                                                       }
                                   table.teal-table tbody tr:nth-child(odd) {
                                                                             background-color: #F0FDFA;
                                                                            }
                                   table.teal-table tbody tr:nth-child(even) {
                                                                              background-color: #E6FFFA;
                                                                             }
                                   table.teal-table tbody tr:hover {
                                                                    background-color: #B2F5EA;
                                                                   }
                               </style>
                               <table class="teal-table">
                                   <thead>
                                       <tr>
                                           {% for header in headers %}
                                               <th>{{ header }}</th>
                                           {% endfor %}
                                       </tr>
                                   </thead>
                                   <tbody>
                                       {% for row in rows %}
                                           <tr>
                                               {% for cell in row %}
                                                   <td>{{ cell }}</td>
                                               {% endfor %}
                                           </tr>
                                       {% endfor %}
                                   </tbody>
                               </table>
                           """)
        students_table = template.render(headers=headers, rows=students)

        message = "destinatários: " + str(len(students)) + "<br><br>" + students_table + "<br><hr>" + message

        send_mail(server, sender, subject, message, manager, bcc, None, port, username, password)

        # executar o endpoint e exibir o resultado
        write_activity_log("sent", "no_reply", None, session["user_id"], session["T_usage"])
        
        return render_template(NOREPLY_TPLT, info="e-mail enviado com sucesso")

    write_activity_log("start", "no_reply", None, session["user_id"], session["T_usage"])

    # caso contrário, redireccionar para a página de selecção
    return render_template(NOREPLY_TPLT)


# executar endpoints
# TODO: viabilizar suspensão da execução automática
@app.route("/endpoints", methods=["GET", "POST"])
@login_required
def endpoints():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    # caso o utilizador não tenha privilégios de SOSusr ou admin, redireccionar para a página principal
    if is_sosusr(session["user_id"]) is None and is_admin(session["user_id"]) is None:
        return redirect("/")

    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        # executar o endpoint e exibir o resultado
        write_activity_log("run", "endpoints", request.form.get("endpoint"), session["user_id"], session["T_usage"])
        
        return render_template(API_ENDPTS_TPLT, endpoint=request.form.get("endpoint"), response=call_endpoint(request.form.get("endpoint")))

    write_activity_log("list", "endpoints", None, session["user_id"], session["T_usage"])

    # caso contrário, redireccionar para a página de selecção
    return render_template(API_ENDPTS_TPLT)


# gerir utilizadores
@app.route("/manage_users", methods=["GET", "POST"])
@login_required
def manage_users():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    # caso o utilizador não tenha privilégios de admin, redireccionar para a página principal
    if is_admin(session["user_id"]) is None:
        return redirect("/")

    wf_db = connect_db()
    wf_db_cursor = wf_db.cursor()

    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        # registar novo utilizador
        if request.form.get("new") is not None:
            # se o utilizador não submeter username e e-mail, retornar mensagem de erro
            if not request.form.get("username") or \
                not request.form.get("mailbox"):
                return error_msg("username e e-mail necessários", 400, '/manage_users')

            # se a password e a confirmação não coincidirem, retornar mensagem de erro
            # elif request.form.get("password") != request.form.get("confirmation"):
            #     return error_msg("a confirmação da password não corresponde", 400, '/manage_users')

            usr = request.form.get("username")
            email = request.form.get("mailbox")

            # verificar se o username já existe
            wf_db_cursor.execute("SELECT * \
                                  FROM wiseflow.users \
                                  WHERE username = %s;",
                                 (usr, ))
            user = wf_db_cursor.fetchall()

            if len(user) != 0:
                # caso exista, retornar mensagem de erro
                return error_msg("o username já existe", 400, '/manage_users')

            # verificar se o e-mail já existe
            wf_db_cursor.execute("SELECT * \
                                  FROM wiseflow.users \
                                  WHERE email = %s;",
                                 (email, ))
            user = wf_db_cursor.fetchall()

            if len(user) != 0:
                # caso exista, retornar mensagem de erro
                return error_msg("o endereço de e-mail já existe", 400, '/manage_users')

            # adicionar o novo utilizador à base de dados
            wf_db_cursor.execute("INSERT INTO users(username, email, hash) \
                                  VALUES(%s, %s, %s);",
                                 (usr, email, def_pwd, ))
            wf_db.commit()

            log = ["-1", session["user_id"]]

            set_password(usr, 1, log)

            write_activity_log("create", "user", usr, session["user_id"], session["T_usage"])

        # reset da password do utilizador
        elif request.form.get("rst") is not None:
            log = ["-1", session["user_id"]]

            set_password(request.form.get("rst"), 1, log)

        # eliminação do utilizador
        elif request.form.get("del") is not None:
            wf_db_cursor.execute("DELETE \
                                  FROM users \
                                  WHERE username = %s",
                                 (request.form.get("del"), ))
            wf_db.commit()

            write_activity_log("delete", "user", request.form.get("del"), session["user_id"], session["T_usage"], request.form.get("del"))

        # definição das permissões do utilizador
        else:
            if request.form.get("admin"):
                wf_db_cursor.execute("UPDATE users \
                                      SET admin = %s \
                                      WHERE id = %s;",
                                     (request.form.get("admin"), request.form.get("usrid"), ))
                wf_db.commit()

            if request.form.get("flowman"):
                wf_db_cursor.execute("UPDATE users \
                                      SET flowman = %s \
                                      WHERE id = %s;",
                                     (request.form.get("flowman"), request.form.get("usrid"), ))                    
                wf_db.commit()

            if request.form.get("bioman"):
                wf_db_cursor.execute("UPDATE users \
                                      SET bioman = %s \
                                      WHERE id = %s;",
                                     (request.form.get("bioman"), request.form.get("usrid"), ))
                wf_db.commit()

            if request.form.get("statsman"):
                wf_db_cursor.execute("UPDATE users \
                                      SET statsman = %s \
                                      WHERE id = %s;",
                                     (request.form.get("statsman"), request.form.get("usrid"), ))
                wf_db.commit()

            if request.form.get("toolsusr"):
                wf_db_cursor.execute("UPDATE users \
                                      SET toolsusr = %s \
                                      WHERE id = %s;",
                                     (request.form.get("toolsusr"), request.form.get("usrid"), ))
                wf_db.commit()

            if request.form.get("sosusr"):
                wf_db_cursor.execute("UPDATE users \
                                      SET sosusr = %s \
                                      WHERE id = %s;",
                                     (request.form.get("sosusr"), request.form.get("usrid"), ))
                wf_db.commit()

            write_activity_log("set_permissions", "user", None, session["user_id"], session["T_usage"], request.form.get("usrid"))

    # caso contrário, redireccionar para a lista de utilizadores
    wf_db_cursor.execute(build_query('wf', None, None, None, None, None, None, 1))
    users = wf_db_cursor.fetchall()

    wf_db_cursor.close()
    wf_db.close()

    write_activity_log("list", "user", None, session["user_id"], session["T_usage"])

    return render_template(MANAGE_USR_TPLT, users=users, userid=session["user_id"])


# consulta de actividade na app
@app.route("/activity_log", methods=["GET", "POST"])
@login_required
def read_activity_log():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    # caso o utilizador não tenha privilégios de admin, redireccionar para a página principal
    if is_admin(session["user_id"]) is None:
        return redirect("/")

    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        wf_db = connect_db()
        wf_db_cursor = wf_db.cursor()

        if request.form.get("sys_activity"):
            # obter o registo de actividade na app
            wf_db_cursor.execute("SELECT IF(log.action <> -1, log.action, 'pwd_set') AS action, \
                                         log.target, \
                                         log.criteria, \
                                         affecteduser, \
                                         act_usr.username AS user, \
                                         FROM_UNIXTIME(log.timestamp) AS timestamp \
                                  FROM wiseflow.log \
                                      LEFT JOIN wiseflow.students stds ON stds.std_num = log.affecteduser \
                                      LEFT JOIN wiseflow.users aff_usr ON aff_usr.id = log.affecteduser \
                                      LEFT JOIN wiseflow.users act_usr ON act_usr.id = log.userid \
                                  ORDER BY log.timestamp DESC;") 
            log = wf_db_cursor.fetchall()

            write_activity_log("list", "sys_log", None, session["user_id"], session["T_usage"])

            return render_template(LOG_TPLT, log=log, sys_activity="True")

        elif request.form.get("mail_activity"):
            # obter o registo de actividade na app
            wf_db_cursor.execute("SELECT IF(mail_log.statusid >= 0, sta.status, 'pwd_set') AS status, \
	                                     affecteduser, \
                                         act_usr.username AS user, \
                                         FROM_UNIXTIME(mail_log.timestamp) AS timestamp \
                                  FROM wiseflow.mail_log \
                                      LEFT JOIN wiseflow.users aff_usr ON aff_usr.id = mail_log.affecteduser \
                                      LEFT JOIN wiseflow.users act_usr ON act_usr.id = mail_log.userid \
                                      LEFT JOIN wiseflow.flows_assess_status sta ON sta.id = mail_log.statusid \
                                  ORDER BY mail_log.timestamp DESC;") 
            log = wf_db_cursor.fetchall()

            write_activity_log("list", "mail_log", None, session["user_id"], session["T_usage"])

            return render_template(LOG_TPLT, log=log, mail_activity="True")

        wf_db_cursor.close()
        wf_db.close()

    return redirect("/")


# alterar password
@app.route("/change_pwd", methods=["GET", "POST"])
@login_required
def change_pwd():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        # se o utilizador não submeter password, retornar mensagem de erro
        if not request.form.get("password"):
            return error_msg("nova password necessária", 400, '/change_pwd')

        # se a password e a confirmação não coincidirem, retornar mensagem de erro
        elif request.form.get("password") != request.form.get("confirmation"):
            return error_msg("a confirmação da password não corresponde", 400, '/change_pwd')
        
        log = ["-1", session["user_id"]]

        set_password(session["username"], 0, log, request.form.get("password"))

        write_activity_log("pwd_chg", "user", None, session["user_id"], session["T_usage"])

        # eliminar a sessão do utilizador e forçar o logout
        session.clear()

        return redirect("/")

    # caso contrário, redireccionar para a página de alteração de password
    return render_template(SETPWD_TPLT, change="True")


# reiniciar password
@app.route("/reset_pwd", methods=["GET", "POST"])
def reset_pwd():
    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        # se o utilizador não submeter o username, retornar mensagem de erro
        if not request.form.get("username"):
            return error_msg("username necessário", 400, '/reset_pwd')
        
        usr = request.form.get("username")

        wf_db = connect_db()
        wf_db_cursor = wf_db.cursor()

        # verificar se o username existe
        wf_db_cursor.execute("SELECT * \
                              FROM wiseflow.users \
                              WHERE username = %s;",
                             (usr, ))
        user = wf_db_cursor.fetchall()

        wf_db_cursor.close()
        wf_db.close()

        if len(user) == 0:
            # caso não exista, retornar mensagem de erro
            return error_msg("o username não existe", 400, '/reset_pwd')

        set_password(usr, 1)

        # eliminar a sessão do utilizador e forçar o logout
        session.clear()

        return redirect("/")

    # caso contrário, redireccionar para a página de alteração de password
    return render_template(SETPWD_TPLT, reset="True")


# menu principal
@app.route("/")
@login_required
def menu():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")
    
    write_activity_log("list", "menu", None, session["user_id"], session["T_usage"])

    # carregar opções de utilizador, em funçao dos privilégios
    return render_template(MENU_TPLT,
                           admin=is_admin(session["user_id"]),
                           flowman=is_bioman(session["user_id"]),
                           bioman=is_bioman(session["user_id"]),
                           statsman=is_statsman(session["user_id"]),
                           toolsusr=is_toolsusr(session["user_id"]),
                           sosusr=is_sosusr(session["user_id"])
                          )


# menu de escolha do sistema
@app.route("/sys_menu", methods=["GET", "POST"])
@login_required
def sys_menu():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")
    
    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        write_activity_log("list", "sys_menu", None, session["user_id"], session["T_usage"])

        # carregar opções de utilizador, em funçao do sistema escolhido
        if request.form.get("show_data") == "full_report":
            return render_template(SYS_MENU_TPLT, full_report="True")
        
        elif request.form.get("show_data") == "stats":
            return render_template(SYS_MENU_TPLT, stats="True")

    return redirect("/")


# histórico completo no WISEflow
# TODO: ligar a histórico do Sentinel_F
# TODO: criar módulo imagens do dia
@app.route("/wf_full_report", methods=["GET", "POST"])
@login_required
def wf_full_report():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    def clean_results():
        session["key"] = None
        session["query"] = None
        session["result"] = None
        session["qryargs"] = ()
        session["fields"] = None

    wf_db = connect_db()
    wf_db_cursor = wf_db.cursor(buffered=True)

    # obter os anos lectivos disponíveis na base de dados
    wf_db_cursor.execute(build_query('wf', None, None, None, None, None, 1, None))
    # wf_db.commit()

    lect_year_list = wf_db_cursor.fetchall()

    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        if request.form.get("set_status") is not None:
            if request.form.get("set_status") == "null":
                value = None

            else:
                value = request.form.get("set_status")

            flowid = request.form.get("flowid")
            std_num = request.form.get("std_num")

            wf_db_cursor.execute("UPDATE flows_assess \
                                      INNER JOIN students ON students.stdid = flows_assess.stdid \
                                  SET flows_assess.status = %s \
                                  WHERE flows_assess.flowid = %s \
                                      AND students.std_num = %s",
                                  (value, flowid, std_num, ))
            
            wf_db.commit()

            if value == '2' or value == '3' or value == '5':
                wf_db_cursor.execute("SELECT flw_ass.partid \
                                      FROM wiseflow.flows_assess flw_ass \
                                          INNER JOIN wiseflow.students stds ON stds.stdid = flw_ass.stdid \
                                      WHERE flw_ass.flowid = %s \
                                          AND stds.std_num = %s;",
                                     (flowid, std_num, ))
                partid = str(wf_db_cursor.fetchone()[0])

                remove_part_assessors(flowid, partid)

            write_activity_log("set_status", "wf_report", ("flowID_" + str(flowid)), session["user_id"], session["T_usage"], ("std_" + str(std_num)))

            if request.form.get("notify") == "True":
                wf_db_cursor.execute("SELECT msg.status as statusid, flw_sta.status, msg.body \
                                      FROM wiseflow.mail_templates msg \
                                          INNER JOIN wiseflow.flows_assess_status flw_sta ON flw_sta.id = msg.status \
                                      WHERE msg.status = %s;",
                                     (value, ))
                msg_template = wf_db_cursor.fetchone()

                if msg_template is not None:
                    wf_db_cursor.execute("SELECT flw.subtitle, flw.title, DATE(flw.dtfrom) AS date, t.email \
                                          FROM wiseflow.flows AS flw \
	                                          INNER JOIN wiseflow.vw_teacher_2wiseflow AS t ON t.cd_discip = SUBSTR(subtitle, 1, 5) \
                                          WHERE flowid = %s \
                                          GROUP BY flw.subtitle;",
                                         (flowid, ))
                    flow_info = wf_db_cursor.fetchone()

                    wf_db_cursor.execute("SELECT std_num, CONCAT(firstname, ' ', lastname) AS std_name, email \
                                          FROM wiseflow.students \
                                          WHERE std_num = %s;",
                                         (std_num, ))
                    std_info = wf_db_cursor.fetchone()

                    wf_db_cursor.execute("SELECT server, port, username, password, mailbox \
                                          FROM wiseflow.mail_server \
                                          WHERE purpose = 'std_notify';")
                    mail_server = wf_db_cursor.fetchone()

                    server = mail_server[0]

                    if mail_server[1] is not None:
                        port = mail_server[1]
                    else:
                        port = None

                    if mail_server[2] is not None:
                        username = mail_server[2]
                    else:
                        username = None

                    if mail_server[3] is not None:
                        password = mail_server[3]
                    else:
                        password = None

                    sender = mail_server[4]
                    dest = std_info[2]
                    bcc_manager = 'sle@uab.pt'

                    if "anulação" in msg_template[1]:
                        # bcc = [flow_info[3], sender]
                        bcc = [flow_info[3], bcc_manager]
                        subject = "anulação"

                    elif "advertência" in msg_template[1]:
                        # bcc = [sender]
                        bcc = [bcc_manager]
                        subject = "advertência"

                    else:
                        subject = "presencial"

                    subject += ": " + flow_info[1] + " (" + flow_info[0] + ")"

                    body = msg_template[2]
                    if "{{flow_title}}" in body:
                        body = body.replace("{{flow_title}}", flow_info[1])

                    if "{{flow_subtitle}}" in body:
                        body = body.replace("{{flow_subtitle}}", flow_info[0])

                    if "{{estudante}}" in body:
                        body = body.replace("{{estudante}}", (std_info[1] + " (" + str(std_info[0]) + ")"))

                    if "{{UC}}" in body:
                        body = body.replace("{{UC}}", flow_info[1][flow_info[1].index("-") + 2:])

                    if "{{timestamp}}" in body:
                        body = body.replace("{{timestamp}}", str(flow_info[2]))

                    log = [msg_template[0], ("std_" + str(std_num)), session["user_id"]]

                    send_mail(server, sender, subject, body, dest, bcc, log, port, username, password)

        elif request.form.get("export") is not None and session["result"] is not None:
            csv_file = export_to_csv(session["result"], session["fields"], ("tmp/usrID" + str(session["user_id"]) + "_wf_full_report.csv"))

            wf_db_cursor.close()
            wf_db.close()

            write_activity_log("export", "wf_report", session["key"], session["user_id"], session["T_usage"])

            clean_results()

            return render_template(WF_FULL_REPORT_TPLT, download="True", file_url=csv_file["url"], file_name=csv_file["filename"], lect_year_list=lect_year_list)
        
        else:
            clean_results()

            # obter os dados completos na base de dados
            date = None
            if request.form.get("date") != '':
                date = request.form.get("date")
                start = date + " 00:00:00"
                end = date + " 23:59:59"
                session["qryargs"] = (start, end, )
                session["key"] = "data: " + date

            else:
                session["key"] = "data: todas"

            bio = None
            bio_val = [0, 90, 80, 70, 60, 50, 40, 30, 20, 10]
            if request.form.get("get_bio") != 'biometria média':
                bio = str(bio_val[int(request.form.get("get_bio"))])
                session["qryargs"] += (bio, )
                session["key"] += " | biometria: <" + bio + "%"

            else:
                session["key"] += " | biometria: todas"

            status = None
            if request.form.get("get_status") != 'estado':
                if request.form.get("get_status") == "presenciais":
                    status = 4

                elif request.form.get("get_status") == "advertências":
                    status = 0

                else:
                    status = 1

                # session["qryargs"] += (status, )
                session["key"] += " | estado: " + request.form.get("get_status")

            else:
                session["key"] += " | estado: todos"

            lect_year = None
            if request.form.get("lect_year") != "ano lectivo":
                lect_year = request.form.get("lect_year")
                session["key"] += " | ano lectivo: " + lect_year

            else:
                session["key"] += " | ano lectivo: todos"

            session["query"] = build_query('wf', lect_year, None, date, bio, status, None, None)

        wf_db_cursor.execute(session["query"], session["qryargs"])
        # wf_db.commit()

        session["result"] = wf_db_cursor.fetchall()
        session["fields"] = wf_db_cursor.column_names

        wf_db_cursor.close()
        wf_db.close()

        write_activity_log("list", "wf_report", session["key"], session["user_id"], session["T_usage"])

        if len(session["result"]) > 0:
            show_bio = False
            if is_bioman(session["user_id"]) or is_admin(session["user_id"]):
                show_bio = True

            show_act = False
            if is_flowman(session["user_id"]) or is_admin(session["user_id"]):
                show_act = True

            return render_template(WF_FULL_REPORT_TPLT, report=session["result"], key=session["key"], show_act=show_act, show_bio=show_bio, lect_year_list=lect_year_list)
        
        else:
            clean_results()
            return render_template(WF_FULL_REPORT_TPLT, report=None)

    wf_db_cursor.close()
    wf_db.close()

    write_activity_log("list", "wf_report", None, session["user_id"], session["T_usage"])

    clean_results()

    # caso contrário, carregar página de pesquisa limpa
    return render_template(WF_FULL_REPORT_TPLT, search="True", lect_year_list=lect_year_list)


# histórico completo no Moodle
@app.route("/mdl_full_report", methods=["GET", "POST"])
@login_required
def mdl_full_report():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    def clean_results():
        session["key"] = None
        session["query"] = None
        session["result"] = None
        session["qryargs"] = ()
        session["fields"] = None

    mdl_db = connect_db('plataformaberta')
    mdl_db_cursor = mdl_db.cursor(buffered=True)

    # obter os anos lectivos disponíveis na base de dados
    mdl_db_cursor.execute(build_query('mdl', None, None, None, None, None, 1, None))
    # mdl_db.commit()

    lect_year_list = mdl_db_cursor.fetchall()
    
    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        if request.form.get("export") is not None and session["result"] is not None:
            csv_file = export_to_csv(session["result"], session["fields"], ("tmp/usrID" + str(session["user_id"]) + "_mdl_full_report.csv"))

            mdl_db_cursor.close()
            mdl_db.close()

            write_activity_log("export", "mdl_report", session["key"], session["user_id"], session["T_usage"])

            clean_results()

            return render_template(MDL_FULL_REPORT_TPLT, download="True", file_url=csv_file["url"], file_name=csv_file["filename"], lect_year_list=lect_year_list)
        
        else:
            clean_results()

            # obter os dados completos na base de dados
            date = None
            if request.form.get("date") != '':
                date = request.form.get("date")
                start = date + " 00:00:00"
                end = date + " 23:59:59"
                session["qryargs"] = (start, end, )
                session["key"] = "data: " + date

            else:
                session["key"] = "data: todas"

            student = None
            if request.form.get("student"):
                student = str(request.form.get("student"))
                session["qryargs"] += (student, )
                session["key"] += " | estudante: " + student

            else:
                session["key"] += " | estudante: todos"

            lect_year = None
            if request.form.get("lect_year") != "ano lectivo":
                lect_year = request.form.get("lect_year")
                session["key"] += " | ano lectivo: " + lect_year

            else:
                session["key"] += " | ano lectivo: todos"

            session["query"] = build_query('mdl', lect_year, student, date, None, None, None, None)

        mdl_db_cursor.execute(session["query"], session["qryargs"])
        # mdl_db.commit()

        session["result"] = mdl_db_cursor.fetchall()
        session["fields"] = mdl_db_cursor.column_names

        mdl_db_cursor.close()
        mdl_db.close()

        write_activity_log("list", "mdl_report", session["key"], session["user_id"], session["T_usage"])

        if len(session["result"]) > 0:
            return render_template(MDL_FULL_REPORT_TPLT, report=session["result"], key=session["key"], lect_year_list=lect_year_list)
        
        else:
            clean_results()
            return render_template(MDL_FULL_REPORT_TPLT, report=None)

    mdl_db_cursor.close()
    mdl_db.close()

    write_activity_log("list", "mdl_report", None, session["user_id"], session["T_usage"])

    clean_results()
    
    # caso contrário, carregar página de pesquisa limpa
    return render_template(MDL_FULL_REPORT_TPLT, search="True", lect_year_list=lect_year_list)


# histórico de um estudante específico
@app.route("/user_report", methods=["GET", "POST"])
@login_required
def user_report():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        # se o utilizador não submeter o número de estudante, retornar mensagem de erro
        if not request.form.get("student"):
            return error_msg("número de estudante necessário", 400, '/user_report')

        # obter os dados do estudante na base de dados
        wf_db = connect_db()
        wf_db_cursor = wf_db.cursor(buffered=True)

        wf_db_cursor.execute(build_query('wf', None, 1, None, None, None, None, None), (request.form.get("student"),))
        # wf_db.commit()

        result = wf_db_cursor.fetchall()

        if len(result) == 0:
            return error_msg("estudante não encontrado", 404, '/user_report')

        if request.form.get("report") == "report_all":
            # base do link directo para o flow de origem
            flw_base_url = "https://europe.wiseflow.net/manager/display.php?id="

            # inicialização do gráfico
            graph = go.Figure()

            # criação de um gráfico de barras com as informações do estudante
            graph.add_trace(
                go.Bar(
                       x=[
                          (
                           f'<a href="' + flw_base_url + str(row[8]) + '" target="_blank">'
                           + str(row[7])
                           + '<br>('
                           + str(row[9])
                           + '<br>'
                           + str(datetime.datetime.strptime(row[12], "%Y-%m-%d %H:%M:%S").date())
                           + ')</a>'
                          )
                          for row in result
                         ],
                       y=[
                          row[13]
                          for row in result
                         ],
                       marker_color=[
                                     'rgb(255,255,0)' if row[14] == '0'
                                else 'rgb(255,182,193)' if row[14] == '2'
                                else 'rgb(255,140,0)' if row[14] == '3'
                                else 'rgb(240,128,128)' if row[14] == '5'
                                else 'rgb(144,238,144)' if row[14] == '4'
                                else 'rgb(83,127,190)'
                                     for row in result
                                    ]
                )
            )

            # actualizar os dados do layout do gráfico
            graph.update_layout(
                title=str(result[0][2]) + " | " + str(result[0][0]) + " " + str(result[0][1]),
                xaxis=dict(title='flows p/ data'),
                yaxis=dict(title='média biométrica', dtick=5)
            )

            # exibir o gráfico
            # graph.show()

            # guardar o gráfico na sessão e num ficheiro temporário
            session["tmp_graph_file"] = "tmp/usrID" + str(session["user_id"]) + "_output_file_name.html"
            graph.write_html(session["tmp_graph_file"])

            write_activity_log("list", "graph", ("std_" + str(request.form.get("student"))), session["user_id"], session["T_usage"])

            return render_template(USER_GRAPH_TPLT, graph="https://mdlws.uab.pt/wiser_py/" + session["tmp_graph_file"], user_history="True")

        elif request.form.get("report") == "report_single":
            if not request.form.get("flowid"):
                return error_msg("flowid necessário", 400, '/user_report')

            flowid = None
            for row in result:
                if str(row[8]) == str(request.form.get("flowid")):
                    stdid = str(row[3])
                    stdnum = str(row[2])
                    stdname = str(row[0]) + " " + str(row[1])
                    flowid = str(row[8])
                    flowtitle = str(row[6])
                    flowsubtitle = str(row[7])
                    flowtype = str(row[9])
                    dtfrom = str(row[10])
                    dtto = str(row[11])            

            if flowid is not None:
                if request.form.get("flow_data") == "flow_sub":
                    wf_db_cursor.execute("SELECT f.flowid, \
                                                 f.evaltype, \
                                                 f.season, \
                                                 CONCAT(t.firstname, ' ', t.lastname) AS tName \
                                          FROM wiseflow.flows f \
                                              INNER JOIN wiseflow.vw_teacher_2wiseflow ft ON (ft.cd_discip = SUBSTR(f.subtitle, 1, 5) \
                                                                                              AND SUBSTR(ft.lectyear, 3, 2) = SUBSTR(f.subtitle, 7, 2)) \
                                              INNER JOIN wiseflow.teachers t ON t.username = ft.username \
                                          WHERE f.flowid = %s \
                                          GROUP BY flowid;",
                                        (flowid, ))
                    flow_info = wf_db_cursor.fetchone()

                    if flow_info[1] == "E":
                        flowscale = "12 valores"
                    elif flow_info[1] == "X":
                        flowscale = "20 valores"
                    if flow_info[2] == "N":
                        flowseason = "Época Normal"
                    elif flow_info[2] == "R":
                        flowseason = "Época de Recurso"
                    elif flow_info[2] == "E":
                        flowseason = "Época Especial"
                    flowteacher = flow_info[3]
                    
                    file2export = get_wf_subs(stdid, stdnum, stdname, flowid, flowtitle, flowsubtitle, flowtype, flowscale, flowseason, flowteacher, dtfrom, dtto)

                    write_activity_log("download", "flow_submission", ("std_" + str(stdnum) + " | flow_" + str(flowid)), session["user_id"], session["T_usage"])

                    return render_template(USER_REPORT_TPLT, open="True", file_url=file2export["url"], search=True, allow_export=True)

                elif request.form.get("flow_data") == "full_log":
                    from_ts = int(datetime.datetime.strptime(dtfrom, "%Y-%m-%d %H:%M:%S").timestamp()) - 300
                    to_ts = int(datetime.datetime.strptime(dtto, "%Y-%m-%d %H:%M:%S").timestamp()) + 300

                    events = get_wf_events(stdid, flowid, from_ts, to_ts)

                    for event in events:
                        event['timestamp'] = datetime.datetime.fromtimestamp(event['timestamp']).strftime('%Y-%m-%d %H:%M:%S')
                        event['flowId'] = str(row[7])
                        event['userId'] = str(row[2])

                    file2export = export_to_csv(events, (events[0].keys()), ("tmp/usrID" + str(session["user_id"]) + "_" + str(row[7]) + "-log.csv"), True)

                    write_activity_log("export", "FLOWmonitor", ("std_" + str(stdnum) + " | flow_" + str(flowid)), session["user_id"], session["T_usage"])

                    return render_template(USER_REPORT_TPLT, download="True", file_url=file2export["url"], file_name=file2export["filename"])

            else:
                return error_msg("flowid não encontrado para o estudante", 404, '/user_report')

        wf_db_cursor.close()
        wf_db.close()

    write_activity_log("list", "std_report", None, session["user_id"], session["T_usage"])

    allow_export = False
    if is_flowman(session["user_id"]) or is_sosusr(session["user_id"]) or is_admin(session["user_id"]):
        allow_export = True

    # caso contrário, carregar página de pesquisa
    return render_template(USER_REPORT_TPLT, search=True, allow_export=allow_export)


# gestão de dados biométricos
@app.route("/user_bio", methods=["GET", "POST"])
@login_required
def user_bio():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    # caso o utilizador não tenha privilégios de bioman ou admin, redireccionar para a página principal
    if is_bioman(session["user_id"]) is None and is_admin(session["user_id"]) is None:
        return redirect("/")

    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        if request.form.get("student"):
            std_num = request.form.get("student")

        elif request.form.get("delete"):
            std_num = request.form.get("delete")

        elif request.form.get("upload"):
            std_num = request.form.get("upload")

        else:
            std_num = None

        # se o utilizador/formulário não submeter o número de estudante, retornar mensagem de erro
        if not std_num:
            return error_msg("número de estudante necessário", 400, '/user_bio')

        # obter os dados do estudante na base de dados
        wf_db = connect_db()
        wf_db_cursor = wf_db.cursor(buffered=True)

        wf_db_cursor.execute(build_query('wf', None, 1, None, 1, None, None, None), (std_num,))
        # wf_db.commit()

        user = wf_db_cursor.fetchall()

        wf_db_cursor.close()
        wf_db.close()

        if len(user) == 0:
            return error_msg("estudante não encontrado", 404, '/user_bio')

        std_id = str(std_num) + " | " + str(user[0][1]) + " " + str(user[0][2])

        # apagar a imagem de referência do estudante
        if request.form.get("delete"):
            manage_user_bio(str(user[0][5]), "DELETE")

            write_activity_log("delete", "bio", ("std_" + str(std_num)), session["user_id"], session["T_usage"], std_num)

        # carregar a imagem de referência do estudante
        if request.form.get("upload"):
            if "pic" not in request.files:
                return error_msg("ficheiro necessário", 400, '/user_bio')

            manage_user_bio(str(user[0][5]), "POST", request.files['pic'])

            write_activity_log("upload", "bio", ("std_" + str(std_num)), session["user_id"], session["T_usage"], std_num)

        # obter a imagem de referência do estudante    
        response = manage_user_bio(str(user[0][5]), "GET")
        bio = json.loads(response.text)

        write_activity_log("list", "bio", ("std_" + str(std_num)), session["user_id"], session["T_usage"])

        if bio['success'] and "link" in bio['data']:
            return render_template(USER_BIO_TPLT, std_id=std_id, std_num=std_num, pic=bio['data']['link'])
        
        elif not bio['success']:
            return render_template(USER_BIO_TPLT, std_id=std_id, std_num=std_num, pic=None)

        else:
            return render_template(USER_BIO_TPLT, std=None)

    write_activity_log("list", "bio", None, session["user_id"], session["T_usage"])

    # caso contrário, carregar página de pesquisa
    return render_template(USER_BIO_TPLT, search=True)


# indicadores estatísticos do WISEflow
@app.route("/wf_stats", methods=["GET", "POST"])
@login_required
def wf_stats():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    # caso o utilizador não tenha privilégios de statsman ou admin, redireccionar para a página principal
    if is_statsman(session["user_id"]) is None and is_admin(session["user_id"]) is None:
        return redirect("/")

    # obter os anos lectivos disponíveis na base de dados
    wf_db = connect_db()
    wf_db_cursor = wf_db.cursor(buffered=True)

    wf_db_cursor.execute(build_query('wf', None, None, None, None, None, 1, None))
    # wf_db.commit()

    lect_year = wf_db_cursor.fetchall()

    wf_db_cursor.close()
    wf_db.close()

    def clean_results():
        session["key"] = None
        session["result"] = None
        session["fields"] = None

    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        if request.form.get("export") is not None and session["result"] is not None:
            csv_file = export_to_csv(session["result"], session["fields"], ("tmp/usrID" + str(session["user_id"]) + "_wf_stats.csv"))

            write_activity_log("export", "wf_stats", session["key"], session["user_id"], session["T_usage"])

            clean_results()
            
            return render_template(WF_STATS_TPLT, download="True", file_url=csv_file["url"], file_name=csv_file["filename"])
        
        else:
            # se o utilizador não submeter o ano lectivo, retornar mensagem de erro
            if request.form.get("lect_year") == "ano lectivo":
                return error_msg("ano lectivo necessário", 400, '/wf_stats')
            
            # nível de detalhe
            if request.form.get("stats") == "stats_simple":
                stats = 2
                stats_tuple = tuple([request.form.get("lect_year")])
                detail = " (simples)"

            else:
                stats = 3
                stats_tuple = tuple([request.form.get("lect_year"), request.form.get("lect_year")])
                detail = " (detalhe)"

            # obter os dados do ano lectivo na base de dados
            wf_db = connect_db()
            wf_db_cursor = wf_db.cursor(buffered=True)

            wf_db_cursor.execute(build_query('wf', None, None, None, None, None, stats, None), (stats_tuple))
            # wf_db.commit()

            session["key"] = request.form.get("lect_year") + detail
            session["result"] = wf_db_cursor.fetchall()
            session["fields"] = wf_db_cursor.column_names

            wf_db_cursor.close()
            wf_db.close()

            write_activity_log("list", "wf_stats", session["key"], session["user_id"], session["T_usage"])

            return render_template(WF_STATS_TPLT, lect_year=lect_year, sel_year=session["key"], report=session["result"])

    write_activity_log("list", "wf_stats", None, session["user_id"], session["T_usage"])

    # caso contrário, carregar página de pesquisa
    return render_template(WF_STATS_TPLT, lect_year=lect_year, search=True)


# indicadores estatísticos do Moodle
@app.route("/mdl_stats", methods=["GET", "POST"])
@login_required
def mdl_stats():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    # caso o utilizador não tenha privilégios de statsman ou admin, redireccionar para a página principal
    if is_statsman(session["user_id"]) is None and is_admin(session["user_id"]) is None:
        return redirect("/")

    # obter os anos lectivos disponíveis na base de dados
    mdl_db = connect_db('plataformaberta')
    mdl_db_cursor = mdl_db.cursor(buffered=True)

    mdl_db_cursor.execute(build_query('mdl', None, None, None, None, None, 1, None))
    # mdl_db.commit()

    lect_year = mdl_db_cursor.fetchall()

    mdl_db_cursor.close()
    mdl_db.close()

    def clean_results():
        session["key"] = None
        session["result"] = None
        session["fields"] = None

    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        if request.form.get("export") is not None and session["result"] is not None:
            csv_file = export_to_csv(session["result"], session["fields"], ("tmp/usrID" + str(session["user_id"]) + "_mdl_stats.csv"))

            write_activity_log("export", "mdl_stats", session["key"], session["user_id"], session["T_usage"])

            clean_results()
            
            return render_template(MDL_STATS_TPLT, download="True", file_url=csv_file["url"], file_name=csv_file["filename"])
        
        else:
            # se o utilizador não submeter o ano lectivo, retornar mensagem de erro
            if request.form.get("lect_year") == "ano lectivo":
                return error_msg("ano lectivo necessário", 400, '/mdl_stats')

            # obter os dados do ano lectivo na base de dados
            mdl_db = connect_db('plataformaberta')
            mdl_db_cursor = mdl_db.cursor(buffered=True)

            mdl_db_cursor.execute(build_query('mdl', None, None, None, None, None, 2, None), (request.form.get("lect_year"),))
            # mdl_db.commit()

            session["key"] = request.form.get("lect_year")
            session["result"] = mdl_db_cursor.fetchall()
            session["fields"] = mdl_db_cursor.column_names

            mdl_db_cursor.close()
            mdl_db.close()

            write_activity_log("list", "mdl_stats", session["key"], session["user_id"], session["T_usage"])

            return render_template(MDL_STATS_TPLT, lect_year=lect_year, sel_year=session["key"], report=session["result"])

    write_activity_log("list", "mdl_stats", None, session["user_id"], session["T_usage"])

    # caso contrário, carregar página de pesquisa
    return render_template(MDL_STATS_TPLT, lect_year=lect_year, search=True)


# Queryn
@app.route("/queryn", methods=["GET", "POST"])
@login_required
def queryn():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    # caso o utilizador não tenha privilégios de statsman ou admin, redireccionar para a página principal
    if is_statsman(session["user_id"]) is None and is_admin(session["user_id"]) is None:
        return redirect("/")

    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        if request.form.get("prompt").strip() == "":
            return error_msg("prompt vazia", 400, '/queryn')

        response = ask_AI(request.form.get("prompt"))["answer"]

        write_activity_log("ask", "queryn", f"\"{request.form.get('prompt')}\"", session["user_id"], session["T_usage"])

        return render_template(QUERYN_TPLT, response=response)

    write_activity_log("visit", "queryn", None, session["user_id"], session["T_usage"])

    # caso contrário, redireccionar para a página de selecção
    return render_template(QUERYN_TPLT)


# indicadores AIDA
@app.route("/aida", methods=["GET", "POST"])
@login_required
def aida():
    session["T_usage"] += (int(datetime.datetime.now().timestamp() - session["time"]))
    session["time"] = int(datetime.datetime.now().timestamp())

    # caso seja obrigatória a alteração da password, redireccionar para a página de alteração
    if set_pwd(session["user_id"]):
        return redirect("/change_pwd")

    # caso o utilizador não tenha privilégios de statsman ou admin, redireccionar para a página principal
    if is_statsman(session["user_id"]) is None and is_admin(session["user_id"]) is None:
        return redirect("/")

    # obter os anos lectivos disponíveis na base de dados
    mdl_db = connect_db('plataformaberta')
    mdl_db_cursor = mdl_db.cursor(buffered=True)

    mdl_db_cursor.execute(build_query('mdl', None, None, None, None, None, 1, None))
    # mdl_db.commit()

    lect_year = mdl_db_cursor.fetchall()

    first_val = lect_year[0][0]
    first_year = int(first_val[:4])
    second_year = int(first_val[4:])
    next_val = f"{first_year+1}{second_year+1:02d}"

    if datetime.datetime.now().month > 7 and all(val[0] != next_val for val in lect_year):
        lect_year.insert(0, (next_val,))

    mdl_db_cursor.close()
    mdl_db.close()

    def clean_results():
        session["key"] = None
        session["result"] = None
        session["fields"] = None

    # se o utilizador chegar ao formulário via POST
    if request.method == "POST":
        if request.form.get("export") is not None and session["result"] is not None:
            csv_file = export_to_csv(session["result"], (session["result"][0].keys()), ("tmp/usrID" + str(session["user_id"]) + "_aida.csv"), True)

            write_activity_log("export", "aida", session["key"], session["user_id"], session["T_usage"])

            clean_results()
            
            return render_template(AIDA_TPLT, download="True", file_url=csv_file["url"], file_name=csv_file["filename"])
        
        else:
            # se o utilizador não submeter o ano lectivo, retornar mensagem de erro
            if request.form.get("endpoint") == "endpoint":
                return error_msg("endpoint necessário", 400, '/aida')
            
            aida_endpoint = mdl_ws_base_url + '?wstoken=' + aida_token + '&wsfunction=' + aida_base + request.form.get("endpoint") + '&moodlewsrestformat=json'

            session["key"] = request.form.get("endpoint")

            if request.form.get("endpoint") != "estudantes_NEEs":
                if request.form.get("lect_year") == "ano_lectivo":
                    return error_msg("ano lectivo necessário", 400, '/aida')

                aida_endpoint += '&ano_lectivo=' + request.form.get("lect_year")

                session["key"] += " | " + request.form.get("lect_year")

            if request.form.get("endpoint") == "estudantes_avaliacao" \
                or request.form.get("endpoint") == "estudantes_folios" \
                or request.form.get("endpoint") == "estudantes_MAO" \
                or request.form.get("endpoint") == "estudantes_NEEs" \
                or request.form.get("endpoint") == "estudantes_UC_acesso" \
                or request.form.get("endpoint") == "estudantes_UC_actividade" \
                or request.form.get("endpoint") == "estudantes_UC_escolha" \
                or request.form.get("endpoint") == "estudantes_WF_escolha" \
                or request.form.get("endpoint") == "folio_historico_data" \
                or request.form.get("endpoint") == "ucs_flows_tipo":
                if request.form.get("endpoint") == "estudantes_folios" \
                    or request.form.get("endpoint") == "folio_historico_data":
                    if request.form.get("endpoint") == "folio_historico_data":
                        if request.form.get("lista_stds").strip() == "":
                            return error_msg("lista de estudantes necessária", 400, '/aida')

                    if request.form.get("lista_ucs").strip() == "":
                        return error_msg("lista de UCs necessária", 400, '/aida')

                if request.form.get("endpoint") != "ucs_flows_tipo":
                    if request.form.get("lista_stds").strip() != "":
                        aida_endpoint += '&lista_stds=' + base64.b64encode(request.form.get("lista_stds").encode("utf-8")).decode("utf-8")

                    else:
                        aida_endpoint += '&lista_stds='

                if request.form.get("endpoint") != "estudantes_MAO" \
                    and request.form.get("endpoint") != "estudantes_NEEs" \
                    and request.form.get("endpoint") != "estudantes_WF_escolha":
                    if request.form.get("lista_ucs").strip() != "":
                        aida_endpoint += '&lista_ucs=' + base64.b64encode(request.form.get("lista_ucs").encode("utf-8")).decode("utf-8")

                    else:
                        aida_endpoint += '&lista_ucs='

                elif request.form.get("endpoint") == "estudantes_NEEs":
                    if request.form.get("NEE_status") != "status":
                        aida_endpoint += '&status=' + request.form.get("NEE_status")

                        if request.form.get("NEE_status") == "1":
                            session["key"] += " | activo"
                        
                        else:
                            session["key"] += " | inactivo"

                elif request.form.get("endpoint") == "estudantes_WF_escolha":
                    if request.form.get("WF_option") != "option":
                        aida_endpoint += '&flow_inloco=' + request.form.get("WF_option")

                        if request.form.get("WF_option") == "1":
                            session["key"] += " | presencial"
                        
                        else:
                            session["key"] += " | remoto"

            session["result"] = requests.get(aida_endpoint).json()

            session["fields"] = list(session["result"][0].keys()) if session["result"] else []

            write_activity_log("list", "aida", session["key"], session["user_id"], session["T_usage"])

            return render_template(AIDA_TPLT, lect_year=lect_year, key=session["key"], result=session["result"], fields=session["fields"])

    write_activity_log("list", "aida", None, session["user_id"], session["T_usage"])

    # caso contrário, carregar página de pesquisa
    return render_template(AIDA_TPLT, lect_year=lect_year, search=True)


if __name__ == "__main__":
    timezone = ZoneInfo("Europe/Lisbon")

    write_activity_log("start", "app")
    purge_logs()

    # versão de teste
    
        # waitress
    # serve(app, host='0.0.0.0', port=5099)

        # Flask
    # app.run(host="0.0.0.0", port=5099, debug=True)


    # versão de produção

        # waitress
    serve(app, host='0.0.0.0', port=5000)

        # Flask
    # app.run(host="0.0.0.0", port=5000, debug=False)
