<?php
/**
 * auth parameters for moodle
 * (developed for UAb - Universidade Aberta)
 *
 * @package    auth_lib_mdl
 * @category   php_config
 * @author     Bruno Tavares <brunustavares@gmail.com>
 * @link       https://www.linkedin.com/in/brunomastavares/
 * @copyright  Copyright (C) 2022-2025 Bruno Tavares
 * @license    GNU General Public License v3 or later
 *             https://www.gnu.org/licenses/gpl-3.0.html
 * @version    2023031005
 * @date       2022-10-27
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

define('GARBAGE', 'amp%3B');

// variáveis globais
    global $host;
    global $port;
    global $usr;
    global $pwd;
    global $db;
    global $mdl_token;
    global $mdl_sync_subs;
    global $mdl_sync_grades;

// base de dados intermédia - BDInt
    $host = '<hidden-host>';
    $port = '<hidden-port>';
    $db   = '<hidden-db>';
    $usr  = '<hidden-usr>';
    $pwd  = '<hidden-pwd>';

// Moodle
    // parte do URL comum a todas as APIs
        $mdl_ws_base_url = 'https://<hidden-url>/';

    // credenciais de acesso
        $mdl_token = '<hidden-token>';

    // endpoints
        $mdl_sync_subs = '<hidden-endpoint>';
        $mdl_sync_grades = '<hidden-endpoint>';
