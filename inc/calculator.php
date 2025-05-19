<?php

function getIntervalsFromXML($file, $projectData = null) {
    global $SETTINGS;

    $projectId = is_array($projectData) ? $projectData['id'] : 0;
    $upd = filemtime($file);
    $xml = file_get_contents($file);
    if ($xml === false) quit("XML Error: $file");

    pluginFixArray($projectData,'parseXML',true, $xml, $projectData, $file);

    $data = simplify($xml);
    $task = betweenGetAndReplace($xml,'<component name="TaskManager">','</component>');
    if (!$task) quit("No task: $file");

    $interval = [];
    $totSum = 0;

    $tag = betweenGetAndReplace($data,'<changelist ','>');

    if ($tag) {
        $tag = " $tag";
        $tag = betweenGetAndReplace($tag,' id="','"');
    }

    if (!$tag) {
        while($x = betweenGetAndReplace($data,'<component ','/>')) {
            if (stripos($x,'name="ProjectId"') !== false) {
                $tag = betweenGetAndReplace($x,'id="','"');
                if ($tag) break;
            }
        }
    }

    $data = null;

    while($dta = betweenGetAndReplace($task,'<workItem','/>')) {

        $orgDta=$dta;
        $from = betweenGetAndReplace($dta,'from="','"');
        $dur = betweenGetAndReplace($dta,'duration="','"');

        if (!$from or !$dur) {
            tty(5,0,'Attenzione: ');
            tty(4,0,"Errore intervallo tempo su: $file");
            echo "\n";
            tty(8,0," $orgDta");
            echo "\n\n";
            continue;
        }

        $timeStart = intval($from/1000);
        $timeLength = intval($dur/1000);

        $x = pluginCall('parseXMLInterval',false, $timeStart, $timeLength, $projectData);

        if (is_array($x) and count($x) == 2) {
            $timeStart = $x[0];
            $timeLength = $x[1];
        }

        $timeStart+= $SETTINGS['fuso'];

        $totSum+=$timeLength;

        $dayBaseTime = floor($timeStart / 86400) * 86400;
        $dayTCRStart = $timeStart - $dayBaseTime;
        $dayTCREnd = $dayTCRStart + $timeLength;

        if ($dayTCREnd < 86400) {

            $interval[] = [
                'type'  =>  0,
                'pid'   =>  $projectId,
                'start' =>  $timeStart,
                'len'   =>  $timeLength,
                'time'  =>  $timeLength
            ];

            continue;
        }

        $dayMaxTime = 86400 - $dayTCRStart;

        $interval[] = [
            'type'  =>  1,
            'pid'   =>  $projectId,
            'start' =>  $timeStart,
            'len'   =>  $dayMaxTime,
            'time'  =>  $timeLength]
        ;

        $timeLength-=$dayMaxTime;
        $fixDays = 1;

        while($timeLength>0) {

            $isCont = $timeLength > 86400;
            $addTime = $isCont ? 86400 : $timeLength;

            $interval[] = [
                'type'  =>  $isCont ? 2:3,
                'pid'   =>  $projectId,
                'start' =>  $dayBaseTime + $fixDays * 86400,
                'len'   =>  $addTime,
                'time'  =>  0]
            ;

            $timeLength-=$addTime;
            $fixDays++;

            if ($timeLength <0) {
                tty(5,0,"Attenzione: ");
                tty(4,0,"Errore dati lunghezza intervallo: $file");
                echo "\n\n";
            }

        }

    }

    pluginFixArray($projectData,'completeXML', true, $projectData , $xml, $file);

    return [
        'tag'       =>  $tag,
        'time'      =>  $upd,
        'file'      =>  $file,
        'totSum'    =>  $totSum,
        'projectId' =>  $projectId,
        'projData'  =>  $projectData,
        'data'      =>  $interval
    ];

}

function createContext() {
    return [
        'cnOvr'     =>  0,  //  Totale override.
        'cnTim'     =>  0,  //  Totale secondi diretto.
        'cnSec'     =>  0,  //  Totale secondi elaborato.
        'cnItem'    =>  0,  //  Numero elementi letti.
        'cnOvrT'    =>  0,  //  Totale secondi override.
        'cnCel'     =>  0,  //  Numero caselle.
        'cnExt'     =>  0,  //  Numero esclusi.
        'cnExtT'    =>  0,  //  Tempo extra escluso.
        'cnFes'     =>  0,  //  Secondi festivo.
        'cxFes'     =>  0,  //  Cnt fes. day.
        'dxFes'     =>  0,  //  Cnt fes toc.
        'fkFes'     =>  0,  //  Cnt fes rc.
    ];
}

function parseInterval(&$dayArray, &$cox, $interval) {
    global $SETTINGS;
    $usePid = isset($SETTINGS['projArray']);

    if ($interval['len'] < $SETTINGS['minLen']) {
        $cox['cnExtT'] += $interval['len'];
        $cox['cnExt']++;
        return;
    }

    $dayId = floor($interval['start'] / 86400);
    $dayTime = $dayId * 86400;
    $tcrStart = intval(($interval['start'] - $dayTime) / 3600);
    $tcrEnd = $tcrStart + intval($interval['len'] / 3600);

    $minStart = intval(($interval['start'] - $dayTime) / 60);
    $minEnd = $minStart + intval($interval['len'] / 60);

    if ($tcrEnd > 23 or $minEnd > 1440) {
        tty(5,0,'Attenzione: ');
        tty(4,0,"Intervallo troppo lungo: $tcrEnd , $minEnd");
        echo "\n\n";
    }

    $cox['cnItem']++;

    if (!isset($dayArray[$dayId])) {

        $Y = gmdate('Y',$dayTime);
        $m = gmdate('m',$dayTime);
        $d = gmdate('d',$dayTime);
        $wDay = gmdate('N',$dayTime);

        $dayArray[$dayId] = [
            'id'    =>  $dayId,
            'date'  =>  "{$d}/{$m}/{$Y}",
            'monthId'=> ($Y * 12) + ((intval($m) - 1) % 12),
            'month' =>  intval($m),
            'year'  =>  intval($Y),
            'day'   =>  intval($d),
            'wDay'  =>  $wDay,
            'fest'  =>  isFestDay($m,$d,$wDay),

            'cnSec' =>  $interval['len'],       //  Secondi da contare
            'cnTim' =>  $interval['time'],      //  Secondi da contare senza processore
            'curTot'=>  0,                      //  Totole parziale
            'cnCel' =>  0,                      //  Conta caselle

            'map'   =>  array_pad([],24,0),
            'heat'  =>  array_pad([],5,0),
            'pidMap'=>  $usePid ? array_pad([],24,[]) : null,
            'modes' =>  [],
            'cont'  =>  0,
            'sal'   =>  0,
            'cnt'   =>  1,
            'str'   =>  null,
            'min'   =>  null,
            'max'   =>  null,
            'ovr'   =>  0,
            'int'   =>  0

        ];

        $cox['cxFes']++;

    } else {

        $dayArray[$dayId]['ovr']++;
        $dayArray[$dayId]['cnt']++;
        $dayArray[$dayId]['cnSec']+=$interval['len'];
        $dayArray[$dayId]['cnTim']+=$interval['time'];

        $cox['cnOvrT']+=$interval['len'];
        $cox['cnOvr']++;

    }

    $cox['cnSec']+=$interval['len'];
    $cox['cnTim']+=$interval['time'];

    if ($usePid) {
        $pid = isset($interval['pid']) ? $interval['pid'] : 0;
        for ($tcr = $tcrStart; $tcr<=$tcrEnd;$tcr++) {
            if (!in_array($pid,$dayArray[$dayId]['pidMap'][$tcr])) {
                $dayArray[$dayId]['pidMap'][$tcr][] = $pid;
            }
        }
    }

    if (!in_array($interval['type'],$dayArray[$dayId]['modes'])) $dayArray[$dayId]['modes'][] =$interval['type'];
    $dayArray[$dayId]['tot'] = intval($dayArray[$dayId]['cnSec'] / 3600);
    if ($dayArray[$dayId]['min'] === null or $dayArray[$dayId]['min'] > $minStart) $dayArray[$dayId]['min'] = $minStart;
    if ($dayArray[$dayId]['max'] === null or $dayArray[$dayId]['max'] < $minEnd) $dayArray[$dayId]['max'] = $minEnd;

    for ($tcr = $tcrStart; $tcr<=$tcrEnd;$tcr++) {
        $dayArray[$dayId]['map'][$tcr]++;
    }

    $cox['cnCel'] -= $dayArray[$dayId]['cnCel'];
    $dayArray[$dayId]['cnCel']=0;

    for ($i = 0;$i<24;$i++) {
        if ($dayArray[$dayId]['map'][$i]>0) $dayArray[$dayId]['cnCel']++;
    }

    $cox['cnCel'] += $dayArray[$dayId]['cnCel'];

}

function calcAllDays($days, $cox) {
    global $SETTINGS;
    $hSum = 0;
    calcData($days,$SETTINGS);

    $mixSum = array_pad([],5,0);
    $out = [];
    $hasHide = false;
    $fkDay = getConfig('count.maxFestRCMin',15) * 60;
    $totMap = [];
    $cntMap = 0;

    $minDayId = 0;
    $maxDayId = 0;

    foreach ($days as $ptr => $day) {

        pluginFixArray($day,'calcDay',true, $day, $cox);

        if ($day['visible']) {

            if ($minDayId == 0 or $day['id'] < $minDayId) $minDayId = $day['id'];
            if ($maxDayId == 0 or $day['id'] > $maxDayId) $maxDayId = $day['id'];

            $hSum+=abs( $day['cnSec']);
            $day['curTot'] = round($hSum / 3600,2);
            $out[$ptr] = $day;

            if ($day['fest']) {
                $cox['cnFes']+=$day['cnSec'];
                $cox['dxFes']++;
                if ($day['cnSec'] > $fkDay) $cox['fkFes']++;
            }

            if (isset($day['pidMap'])) {
                foreach ($day['pidMap'] as $item) {
                    if (!$item) continue;
                    foreach ($item as $pid) {
                        if (!isset($totMap[$pid])) $totMap[$pid] = 0;
                        $totMap[$pid]++;
                        $cntMap++;
                    }
                }
            }

            for ($i = 0; $i < 5; $i++) {
                $mixSum[$i]+=$day['heat'][$i];
            }

        } else {

            $hasHide = true;

        }
    }

    $data = [
        'cntAll'    =>  count($days),
        'hSum'      =>  round($hSum / 3600 , 2),
        'mix'       =>  $mixSum,
        'hide'      =>  $hasHide,
        'totMap'    =>  $totMap,
        'cntMap'    =>  $cntMap,
        'timeFrom'  =>  $minDayId * 86400,
        'timeTo'    =>  $maxDayId * 86400,
        'extra'     =>  $cox['cnExtT'],  //$extra,
        'tcrTot'    =>  $cox['cnTim'] ,  //$tcrTot,
        'cox'       =>  $cox,
        'days'      =>  $out
    ];

    if ($hasHide) {

        $mixSum = array_pad([],5,0);
        $hSum = 0;

        foreach ($days as $ptr => $day) {

            $hSum+= $day['tot'];
            $day['curTot'] = $hSum;
            $out[$ptr] = $day;

            for ($i = 0; $i < 5; $i++) {
                $mixSum[$i]+=$day['heat'][$i];
            }

        }

        $data['mixAll'] = $mixSum;
        $data['hAll'] = $hSum;

    } else {
        $data['mixAll'] = $data['mix'];
        $data['hAll'] = $data['hSum'];
    }

    $data['cntOut'] = count($out);
    pluginFixArray($data,'calcAllDays',true,$data);

    return $data;

}

function sumProjects($progs, $unique, $verbose) {
    $map = [];
    $skipped = [];
    $added = [];

    if ($verbose) {
        tty(15,0,"Merge di ".count($progs)." progetti:");
        echo "\n";
    }

    $out = [
        'array'     =>  true,
        'uni'       =>  $unique,
        'tag'       =>  [],
        'time'      =>  [],
        'file'      =>  [],
        'totSum'    =>  0,
        'data'      =>  []
    ];

    pluginCall('sumProjectsInit',true,$progs,$unique,$verbose);

    foreach ($progs as $ptr => $prog) {

        $tag = $prog['tag'];
        if (!$tag) $tag = $ptr;

        if (!$unique) {
            if ($verbose) outProjMerge($prog,$tag,'Includi',10);
            $map[] = $prog;
            continue;
        }

        if (isset($map[$tag])) {

            if ($prog['time'] > $map[$tag]['time']) {
                $map[$tag] = $prog;

                if ($verbose) outProjMerge($prog,$tag,'Aggiorna',11);

            } elseif (count($prog['data']) > count($map[$tag]['data'])) {
                $map[$tag] = $prog;

                if ($verbose) outProjMerge($prog,$tag,'Aggiorna',9);

            } else {

                if ($verbose) outProjMerge($prog,$tag,'Salta',7);

                $skipped[] = [
                    'tag'   =>  $tag,
                    'file'  =>  $prog['file'],
                    'cnt'   =>  count($prog['data']),
                    'totSum'=>  $prog['totSum']
                ];
            }

        } else {

            if ($verbose) outProjMerge($prog,$tag,'Includi',10);
            $map[$tag] = $prog;
        }
    }

    foreach ($map as $item) {

        pluginFixArray($item,'sumProject',true, $item);

        $out['totSum'] += $item['totSum'];
        $out['data'] = array_merge($out['data'],$item['data']);
        $out['tag'][] = $item['tag'];
        $out['file'][] = $item['file'];
        $added[] = $item['projData'];

    }

    $out['skipped'] = $skipped;
    $out['added'] = $added;

    if ($verbose) echo "\n";

    pluginFixArray($out,'sumProjectsComplete',true,$out);
    return $out;

}

function getAllData() {
    global $SETTINGS;
    global $OPT;

    if (isset($SETTINGS['projList'])) {

        pluginCall('initProjectList',true,$SETTINGS['projList']);

        $SETTINGS['projArray'] = getProjectsByMapList($SETTINGS['projList']);
        pluginFixArray($SETTINGS['projArray'],'initProjectList',true, $SETTINGS['projArray']);

        $data = [];
        foreach ($SETTINGS['projArray'] as $item) {
            $data[] = getIntervalsFromXML($item['file'],$item);
        }

        $data = sumProjects($data,!isset($OPT['u']),isset($OPT['v']));


    } else {

        $data = getIntervalsFromXML($SETTINGS['project']);

    }

    $cox = createContext();
    $days = [];

    foreach ($data['data'] as $interval) {
        parseInterval($days,$cox, $interval);
    }

    $keys = [
        'array',
        'uni',
        'tag',
        'time',
        'file',
        'totSum',
        'added'
    ];

    $extra = [];
    foreach ($keys as $key) {
        if (isset($data[$key])) $extra[$key] = $data[$key];
    }
    $data = calcAllDays($days, $cox);

    $maxH = ceil($data['hSum']);
    $maxH = $maxH != 0 ? $maxH : 1;
    $data['pMix'] = [];

    for ($i = 0; $i< 5;$i++) {
        $data['pMix'][$i] = intval(100 * ($data['mix'][$i] / $maxH));
    }

    foreach ($extra as $k => $v) {
        if (!isset($data[$k])) $data[$k] = $v;
    }

    pluginFixArray($data,'getAllData',true,$data);

    return $data;
}

function isFestDay($m , $d , $wDay) {
    global $SETTINGS;
    global $WEEK_DAYS;

    $m = intval($m);
    $d = intval($d);

    if (isset($SETTINGS['fest'])) {
        $dayId = ($m << 5) | ($d & 31);
        if (isset($SETTINGS['fest']['day'][$dayId])) return $SETTINGS['fest']['day'][$dayId];

        if (isset($SETTINGS['fest']['int'][$m])) {
            foreach ($SETTINGS['fest']['int'][$m] as $int) {
                if ($d >= $int[0] and $d <= $int[1]) return true;
            }
        }
    }

    if (isset($WEEK_DAYS[$wDay])) return $WEEK_DAYS[$wDay][3];

    return false;
}

function addDayType($m, $d, $len, $mode) {
    global $SETTINGS;

    if (!isset($SETTINGS['fest'])) {
        $SETTINGS['fest'] = [
            'day'   =>  [],
            'int'   =>  []
        ];
    }

    if ($len) {

        if (!isset($SETTINGS['fest']['int'][$m])) $SETTINGS['fest']['int'][$m] = [];
        $SETTINGS['fest']['int'][$m][] = [$d, $d+$len];

    } else {

        $dayId = ($m << 5) | ($d & 31);
        $SETTINGS['fest']['day'][$dayId] = $mode;

    }

}

function calcDay(&$dayData, $symbolsMap, $sidMap) {
    global $HOUR_MAP;
    global $HEAT_MAP;
    global $OPT;

    $m = false;
    $str = '';
    $heat = [4, '█'];

    $byHeat = isset($OPT['H']);
    $byOvr = isset($OPT['O']);
    $byDot = isset($OPT['D']);
    $isHead = !isset($OPT['N']);
    $mapNum = isset($OPT['mapnum']);

    if (isset($OPT['map']) and isset($dayData['pidMap'])) {
        $isMap = true;
    } else {
        $isMap = false;
    }

    $curCol = 12;

    $cProj = [];

    for ($i = 0; $i< 24; $i++) {

        $c = $dayData['map'][$i] > 0;
        if ($c and !$m) $dayData['int']++;
        $m = $c;

        $h = $dayData['map'][$i];

        if ($isHead and isset($HOUR_MAP[$i])) $str.= ttyS(3,0,"{$HOUR_MAP[$i][0]} ");
        if (isset($HOUR_MAP[$i])) $curCol = $HOUR_MAP[$i][1];

        if (isset($HEAT_MAP[$i])) $heat = $HEAT_MAP[$i];

        $doText = true;

        if ($isMap) {

            if (isset($dayData['pidMap'][$i])) {

                $hm = count($dayData['pidMap'][$i]);
                if ($hm == 1) {
                    $x = $dayData['pidMap'][$i][0];
                    $x = isset($sidMap[$x]) ? $sidMap[$x] : null;
                    if ($x and !in_array($x,$cProj)) $cProj[] = $x;
                }

                if (!$mapNum and $hm == 1) {

                    $hm = $dayData['pidMap'][$i][0];

                    if (isset($symbolsMap[$hm])) {

                        $str.=$symbolsMap[$hm];

                    } else {

                        $str.=ttyS(7,8,'!');

                    }

                    $doText = false;

                } elseif ($mapNum or $hm > 1) {

                    if ($mapNum) {
                        $str.= mapNumStr($hm);
                    } else {
                        $str.=ttyS( 0,12,$hm > 9 ? '+' : "$hm");
                    }

                    $doText = false;

                }

            }
        }

        if ($h > 0) {

            if ($doText) {

                if ($byHeat) {

                    $str.= ttyS($heat[2],0,$heat[1]);

                } elseif ($byOvr) {

                    $str .= ttyS($curCol,0,$h < 16 ? strtoupper(dechex($h)) : '█');

                } else {

                    $str.= ttyS($curCol,0,'█');
                }
            }

            $dayData['heat'][$heat[0]]++;

        } else {

            $x = $byDot ? ttyS(8,0,'·') : ttyS(8,0,$i % 10);
            $str.= "$x";

        }

    }

    $dayData['str'] = $str;
    $dayData['prArr'] = $cProj;

    pluginFixArray($dayData,'dayData',true,$dayData, $symbolsMap, $sidMap);

}

function fixCSeq($cSeq, &$dayArray) {

    $j = count($cSeq);
    if ($j == 0) return;

    if ($j == 1) {
        $dayArray[ $cSeq[0] ]['cont'] = 1;
    } else {
        $j--;
        for ($i = 0; $i <= $j; $i++) {

            if ($i == 0) {
                $cont = 2;
            } elseif ($i == $j) {
                $cont = 4;
            } else {
                $cont = 3;
            }

            $dayArray[$cSeq[$i]]['cont'] = $cont;
        }

    }
}

function calcData(&$days, $par) {
    global $OPT;

    ksort($days);
    $cSeq = [];
    $prevId = 0;

    $fTimeStart = isset($par['from']) ? $par['from'] : null;
    $fTimeStop = isset($par['to']) ? $par['to'] : null;

    $hasHeat = isset($par['heat']);
    $canCseq = !$hasHeat && !isset($par['from']) && !isset($par['to']);
    $hSum = 0;
    $symbolsMap = isset($OPT['map']) ? getSymbolsMap() : [];
    $sidMap = isset($OPT['map']) ? getSidMap() : [];

    foreach ($days as $ptr => &$day) {

        $day['visible'] = true;

        if ($fTimeStart and $day['id'] < $fTimeStart) $day['visible'] = false;
        if ($fTimeStop and $day['id'] > $fTimeStop) $day['visible'] = false;

        if ($canCseq) {
            $d = $ptr - $prevId;
            $day['sal'] = $d - 1;

            if ($d == 1) {

                $cSeq[] = $ptr;

            } else {

                if ($cSeq) fixCSeq($cSeq,$days);
                $cSeq = [$ptr];

            }

            $prevId = $ptr;

        }

        calcDay($day, $symbolsMap, $sidMap);

        if ($hasHeat) {
            for ($i = 0; $i<5;$i++) {

                if (!isset($par['heat'][$i])) continue;
                if ($day['heat'][$i] < $par['heat'][$i]) $day['visible'] = false;

            }
        }

        $hSum+= $day['cnSec'];
        $day['totSum'] = ceil($hSum / 3600);

    }

    if ($canCseq && $cSeq) fixCSeq($cSeq,$days);

    unset($day);

}


function getByMonthData($data) {
    global $OPT;
    global $SETTINGS;

    if (isset($OPT['map']) and isset($SETTINGS['projArray'])) $isMap = true; else $isMap = false;

    $month = [];
    foreach ($data['days'] as $day) {

        $monthId = $day['monthId'];
        if (!isset($month[$monthId])) {

            $len = cal_days_in_month(CAL_GREGORIAN,$day['month'],$day['year']);

            $month[$monthId] = [
                'id'    =>  $monthId,
                'date'  =>  "{$day['year']}/".numPad($day['month']),
                'year'  =>  $day['year'],
                'month' =>  $day['month'],
                'salt'  =>  0,
                'min'   =>  null,
                'max'   =>  null,
                'num'   =>  0,
                'hTot'  =>  0,
                'dTot'  =>  0,
                'mFes'  =>  0,
                'hours' =>  0,
                'pidMap'=>  $isMap ? array_pad([],$len+1,null) : null,
                'buf'   =>  [],
                'buf2'  =>  [],
                'len'   =>  $len,
                'tFes'  =>  0,
                'heat'  =>  array_pad([],5,0),
                'heatD' =>  array_pad([],6,0),
                'day'   =>  array_pad([],$len+1,0),
                'fest'  =>  array_pad([],$len+1,false),
                'map'   =>  []
            ];

            for ($i = 1; $i<= $len; $i++) {
                $dayTime = gmmktime(0,0,0,$day['month'],$i,$day['year']);
                $wDay = gmdate('N',$dayTime);
                $month[$monthId]['fest'][$i] = isFestDay($day['month'],$i,$wDay);
                if ($month[$monthId]['fest'][$i]) $month[$monthId]['mFes']++;
            }

            pluginFixArray($month[$monthId],'addMonth',true,$month[$monthId], $data);

        }

        if ($isMap and isset($day['pidMap'])) {
            if (!isset($month[$monthId]['pidMap'][$day['day']])) $month[$monthId]['pidMap'][$day['day']] = [];
            foreach ($day['pidMap'] as $pidH) {
                foreach ($pidH as $pid) {
                    if (!in_array($pid,$month[$monthId]['pidMap'][$day['day']])) {
                        $month[$monthId]['pidMap'][$day['day']][] = $pid;
                    }
                }
            }
        }

        if ($day['fest']) $month[$monthId]['tFes']+= $day['cnSec'];

        if ($month[$monthId]['min'] === null or $month[$monthId]['min'] > $day['day']) $month[$monthId]['min'] = $day['day'];
        if ($month[$monthId]['max'] === null or $month[$monthId]['max'] < $day['day']) $month[$monthId]['max'] = $day['day'];

        $month[$monthId]['hours'] += $day['tot'];
        $month[$monthId]['num']++;

        if ($day['tot'] > 0) {
            if ( $month[$monthId]['day'][$day['day']] == 0) $month[$monthId]['dTot']++;
            $month[$monthId]['day'][$day['day']]+=$day['tot'];
        }

        $month[$monthId]['map'][$day['day']] = $day['heat'];
        //$month[$monthId]['fest'][$day['day']] = $day['fest'];

        $month[$monthId]['map'][$day['day']][0] = (
            $month[$monthId]['map'][$day['day']][1] > 0 ||
            $month[$monthId]['map'][$day['day']][2] > 0 ||
            $month[$monthId]['map'][$day['day']][3] > 0 ||
            $month[$monthId]['map'][$day['day']][4] > 0) ? 1:0
        ;

        foreach ($day['map'] as $hour => $cnt) {

            $hourId = intval($day['day'] * 24) + $hour;

            if (!isset($month[$monthId]['buf'][$hourId])) {
                $month[$monthId]['buf'][$hourId] = true;
                $month[$monthId]['hTot']++;
            }

        }

        $hasH = false;
        foreach ($day['heat'] as $k => $v) {
            $month[$monthId]['heat'][$k]+=$v;
            $month[$monthId]['heatD'][$k]++;
            if ($k > 1 and $v > 0) $hasH = true;
        }

        if ($hasH and !isset($month[$monthId]['buf2'][$day['id']])) {
            $month[$monthId]['buf2'][$day['id']] = true;
            $month[$monthId]['heatD'][5]++;
        }

    }

    foreach ($month as &$byMonth) {
        unset($byMonth['buf']);
        unset($byMonth['buf2']);
    }

    pluginFixArray($month,'completeMonth',true, $month, $data);

    return $month;

}


function mapNumStr($val) {
    return ttyS($val > 9 ? 4 : 0, $val > 9 ? 13 : $val, $val > 9 ? '+' : "$val");
}
