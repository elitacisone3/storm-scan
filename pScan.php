<?php
/*
 * pScan
 * Copyright (C) 2025 by EPTO
 * 
 * This is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 * 
 * This source code is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 * 
 * You should have received a copy of the GNU General Public License
 * along with this source code; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */
 
require 'inc/globals.php';
require 'inc/settings.php';
require 'inc/function.php';
require 'inc/plugin.php';
require 'inc/calculator.php';
require 'inc/symbol.php';
require 'inc/projMap.php';
require 'inc/output.php';
require 'inc/help.php';

initProc();
initSymbols();

if (!isset($OPT['q'])) outPres();

$data = getAllData();

if (isset($OPT['mesi'])) {

    $byMonth = getByMonthData($data);
    if (!isset($OPT['dati'])) outMonthly($byMonth);
    $data['byMonth'] = $byMonth;
    if (!isset($OPT['G'])) {
        outSummary($data);
        if (isset($OPT['map'])) outLegend($data);
    }

} else {

    if (!isset($OPT['dati'])) outDaily($data);
    if (!isset($OPT['G'])) {
        outSummary($data);
        if (isset($OPT['map'])) outLegend($data);
    }

}

if (isset($OPT['salva'])) {
    $raw = json_encode($data,JSON_PRETTY_PRINT);
    if (pathinfo($OPT['salva'],PATHINFO_EXTENSION) == 'gz') $raw = gzencode($raw);
    file_put_contents($OPT['salva'],$raw);
}

dispatch();
