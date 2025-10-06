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
# @copyright  Copyright (C) 2024-2025 Bruno Tavares
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

import subprocess
import os

php_exec = 'C:\php\php7\php.exe'
py_exec  = 'C:\Program Files\Python311\python.exe'

def call_endpoint(endpoint):
    if endpoint == 'sync_stdts' or \
       endpoint == 'sync_flows' or \
       endpoint == 'sync_parts' or \
       endpoint == 'sync_stats':
        php_file = os.path.dirname(os.path.abspath(__file__)) + '/wiseflow/' + endpoint + '.php'

        proc = '"' + php_exec + '" ' + php_file
    
    elif endpoint == 'sync_subs' or \
         endpoint == 'sync_grades':
        py_file = os.path.dirname(os.path.abspath(__file__)) + '/plataformaberta/' + endpoint + '.py'

        proc = '"' + py_exec + '" ' + py_file

    proc = subprocess.Popen(proc, shell=True, stdout=subprocess.PIPE)

    script_response = proc.stdout.read().decode('utf-8').splitlines()

    return script_response
