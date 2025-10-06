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

import mysql.connector

# ligação à base de dados
def connect_db(dbhost, dbport, dbuser, dbpass, dbname):
    return mysql.connector.connect(host=dbhost, port=dbport, user=dbuser, password=dbpass, database=dbname)


# construção das queries à base de dados
def build_query(student=None, date=None, bio=None, stats=None):
    if student is not None and bio is not None:
        query = "SELECT * \
                 FROM wiseflow.students \
                 WHERE std_num = %s;"

    elif stats is None:
        query = "SELECT flw_std.firstname, \
                        flw_std.lastname, \
                        flw_std.std_num, \
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
                        flw_ass.grade, \
                        flw_ass.dtgrd \
                 FROM wiseflow.flows_assess flw_ass \
                     INNER JOIN wiseflow.flows flw ON flw.flowid = flw_ass.flowid \
                     INNER JOIN wiseflow.flows_templates flw_tmpl ON flw_tmpl.id = flw.template \
                     INNER JOIN wiseflow.students flw_std ON flw_std.stdid = flw_ass.stdid \
                 WHERE (dtass IS NOT NULL \
                     AND dtass <> '') "
            
        if student is not None:
            query += " AND std_num = %s "

        if date is not None:
            query += " AND DATE(dtass) = %s "

        if bio is not None:
            query += " AND ((flw_ass.fr_avgM * 1) < %s \
                    AND flw_tmpl.flowtype_name <> 'FLOWassign') "

        query += "ORDER BY flw_std.std_num ASC, flw_ass.dtass ASC, flw.subtitle ASC;"

    elif stats == 1:
        query = "SELECT lectyear \
                 FROM courses \
                 GROUP BY lectyear \
                 ORDER BY lectyear DESC;"
        
    elif stats == 2:
        query = "SELECT `flow` AS tipo, \
                        MAX(CASE WHEN `tipo` = 'efolioG' THEN `T` END) AS `efolioG: T`, \
                        MAX(CASE WHEN `tipo` = 'efolioG' THEN `media` END) AS `efolioG: nota`, \
                        MAX(CASE WHEN `tipo` = 'efolioG' THEN ROUND(((`media` / 12) * 100), 2) END) AS `efolioG: %`, \
                        MAX(CASE WHEN `tipo` = 'efolioG' THEN ROUND((`aprov` * 100), 2) END) AS `efolioG: %aprov`, \
                        MAX(CASE WHEN `tipo` = 'efolioG' THEN IF(`flow` <> 'FLOWassign', FORMAT(`bio`, 2), 'NA') END) AS `efolioG: %bio`, \
                        MAX(CASE WHEN `tipo` = 'exame' THEN `T` END) AS `efolioG: T`, \
                        MAX(CASE WHEN `tipo` = 'exame' THEN `media` END) AS `exame: nota`, \
                        MAX(CASE WHEN `tipo` = 'exame' THEN ROUND(((`media` / 20) * 100), 2) END) AS `exame: %`, \
                        MAX(CASE WHEN `tipo` = 'exame' THEN ROUND((`aprov` * 100), 2) END) AS `exame: %aprov`, \
                        MAX(CASE WHEN `tipo` = 'exame' THEN IF(`flow` <> 'FLOWassign', FORMAT(`bio`, 2), 'NA') END) AS `exame: %bio` \
                 FROM ( \
                       SELECT IF((`flw`.`evaltype` = 'E'), 'efolioG', 'exame') AS `tipo`, \
                              `flw_tmp`.`flowtype_name` AS `flow`, \
                              COUNT(`flw_ass`.`dtass`) AS 'T', \
                              ROUND((SUM(`flw_ass`.`grade`) / COUNT(`flw_ass`.`grade`)), 2) AS `media`, \
                              `aprov` AS `aprov`, \
                              ROUND((SUM(`flw_ass`.`fr_avgM`) / COUNT(`flw_ass`.`fr_avgM`)), 2) AS `bio` \
                       FROM `flows_assess` `flw_ass` \
                           INNER JOIN `flows` `flw` ON `flw`.`flowid` = `flw_ass`.`flowid` \
                           INNER JOIN `flows_templates` `flw_tmp` ON `flw_tmp`.`id` = `flw`.`template` \
                           INNER JOIN ( \
                                       SELECT  `flw`.`lectyear`, \
                                               `flw_tmp`.`flowtype_name`, \
                                               `flw`.`evaltype`, \
                                               COUNT(`flw_ass`.`dtass`) AS 'T' \
                                       FROM `flows_assess` `flw_ass` \
                                           INNER JOIN `flows` `flw` ON `flw`.`flowid` = `flw_ass`.`flowid` \
                                           INNER JOIN `flows_templates` `flw_tmp` ON `flw_tmp`.`id` = `flw`.`template` \
                                       WHERE `dtass` IS NOT NULL \
                                       GROUP BY `flw`.`lectyear` , `flw_tmp`.`flowtype_name` , `flw`.`evaltype` \
                                      ) AS total ON (`total`.`flowtype_name` = `flw_tmp`.`flowtype_name` AND `total`.`evaltype` = `flw`.`evaltype` AND `total`.`lectyear` = `flw`.`lectyear`) \
                           INNER JOIN ( \
                                       SELECT  `aprov`.`lectyear`, \
                                               `aprov`.`flowtype_name`, \
                                               `aprov`.`evaltype`, \
                                               (`aprov`.`pos` / `grades`.`grds`) AS `aprov` \
                                       FROM ( \
                                             SELECT `flw`.`lectyear`, \
                                                    `flw_tmp`.`flowtype_name`, \
                                                    `flw`.`evaltype`, \
                                                    COUNT(`flw_ass`.`grade`) AS `pos` \
                                             FROM `flows_assess` `flw_ass` \
                                                 INNER JOIN `flows` `flw` ON `flw`.`flowid` = `flw_ass`.`flowid` \
                                                 INNER JOIN `flows_templates` `flw_tmp` ON `flw_tmp`.`id` = `flw`.`template` \
                                             WHERE `flw_ass`.`grade` >= (IF((`flw`.`evaltype` = 'E'), 5.5, 9.5)) \
                                             GROUP BY `flw`.`lectyear` , `flw_tmp`.`flowtype_name` , `flw`.`evaltype` \
                                            ) AS `aprov` \
                                           INNER JOIN ( \
                                                       SELECT  `tmp`.`lectyear`, \
                                                               `tmp`.`flowtype_name`, \
                                                               `tmp`.`evaltype`, \
                                                               `tmp`.`grds` \
                                                       FROM ( \
                                                             SELECT `flw`.`lectyear`, \
                                                                    `flw_tmp`.`flowtype_name`, \
                                                                    `flw`.`evaltype`, \
                                                                    COUNT(`flw_ass`.`grade`) AS `grds` \
                                                             FROM `flows_assess` `flw_ass` \
                                                                 INNER JOIN `flows` `flw` ON `flw`.`flowid` = `flw_ass`.`flowid` \
                                                                 INNER JOIN `flows_templates` `flw_tmp` ON `flw_tmp`.`id` = `flw`.`template` \
                                                             WHERE `flw_ass`.`grade` >= 0 \
                                                             GROUP BY `flw`.`lectyear` , `flw_tmp`.`flowtype_name` , `flw`.`evaltype` \
                                                            ) AS `tmp` \
                                                      ) AS `grades` ON (`grades`.`flowtype_name` = `aprov`.`flowtype_name` AND `grades`.`evaltype` = `aprov`.`evaltype` AND `grades`.`lectyear` = `aprov`.`lectyear`) \
                                      ) AS `aprov` ON (`aprov`.`flowtype_name` = `flw_tmp`.`flowtype_name` AND `aprov`.`evaltype` = `flw`.`evaltype` AND `aprov`.`lectyear` = `flw`.`lectyear`) \
                       WHERE `flw`.`lectyear` = %s \
                       GROUP BY `flw`.`evaltype` , `flw_tmp`.`flowtype_name` \
                      ) AS `rawdata` \
                 GROUP BY `flow` \
                 ORDER BY `flow`;"

    return query
