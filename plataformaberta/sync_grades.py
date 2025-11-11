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
# @version    2025082111
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

import os
import requests
import mysql.connector

auth_lib_mdl = os.path.dirname(os.path.abspath(__file__)) + '\\auth_lib_mdl.php'

with open(auth_lib_mdl, "r") as wf_cfg_file:
    wf_cfg = wf_cfg_file.readlines()
    wf_cfg_file.close()

    # carregar variáveis a partir do ficheiro
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

            elif "$mdl_ws_base_url =" in row:
                mdl_ws_base_url = row[row.find("'") + 1:row.rfind("'")]

            elif "$mdl_token =" in row:
                token = row[row.find("'") + 1:row.rfind("'")]

            elif "$mdl_sync_grades =" in row:
                endpoint = row[row.find("'") + 1:row.rfind("'")]

format = 'json'

url = mdl_ws_base_url + '?wstoken=' + token + '&wsfunction=' + endpoint + '&moodlewsrestformat=' + format

response = requests.get(url)
data = response.json()

if len(data) > 0:
    wf_db = mysql.connector.connect(host=dbhost, port=dbport, user=dbuser, password=dbpass, database=dbname)
    wf_db_cursor = wf_db.cursor()

    for row in data:
        wf_db_cursor.execute("INSERT INTO grades(UC_ID, PEE, epoca, estudante_Num, nota, data_nota) \
                              VALUES(%s, %s, %s, %s, %s, %s) \
                              ON DUPLICATE KEY UPDATE \
                                  UC_ID = VALUES(UC_ID), \
                                  PEE = VALUES(PEE), \
                                  epoca = VALUES(epoca), \
                                  estudante_Num = VALUES(estudante_Num), \
                                  nota = VALUES(nota), \
                                  data_nota = VALUES(data_nota);",
                              (row['ucid'], row['pee'], row['season'], row['stdnum'], row['grade'], row['gradedate'],))
        wf_db.commit()

    print (str(len(data)) + " notas da PlataformAbERTA importadas/actualizadas")

    wf_db_cursor.close()
    wf_db.close()

else:
    print("Sem notas da PlataformAbERTA para importar/actualizar")
