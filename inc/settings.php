<?php

function initProc() {
    global $CONF;
    global $SETTINGS;
    global $OPT;
    global $HEAT_MAP;
    global $PALETTE;
    global $VGA_COLOR;
    global $TTY_PALETTE;
    global $LEGACY_CHAR;
    global $WEEK_DAYS;

    ob_start();
    $doHelp = false;

    $myConf = 'pScan.conf';
    $myDir = 'pScan';

    $OPT = [
        'dal:','al:','salva:','per:','noe','map','mapnum','mesi','colori','mono','hr',
        'ascii','dati','pr:','G2','head','scr','crw:','nomi','fuso:','plugin:','132',
        'palette','tutti']
    ;

    for ($i = 0; $i < 5; $i++) {
        $OPT[] = "H{$i}:";
        $OPT[] = "h{$i}";
    }

    $OPT = getopt('DOHNMhoEeNp:XGP:L:vqu',$OPT);
    $SETTINGS = [];
    $SETTINGS['grp2'] = false;

    $path = dirname(dirname(__FILE__));
    $path = realpath($path);
    if (!$path) quit("Errore interpetazione __FILE__ : ".__FILE__);
    $SETTINGS['myPath'] = fixPath($path);

    if ($OPT === false) {
        $OPT = [];
        $doHelp = true;
    }

    $isWindow = stripos(PHP_OS,'win') !== false;
    $try = [];

    $x = realpath($_SERVER['PHP_SELF']);
    if (!$x) quit("Non riesco a rilevare il realpath: {$_SERVER['PHP_SELF']}");
    $try[] = fixPath(dirname($x)) . "config/$myConf";

    if ($isWindow) {

        if (isset($_SERVER['APPDATA'])) {
            $try[] = "{$_SERVER['APPDATA']}/$myDir/$myConf";
        }
        if (isset($_SERVER['USERPROFILE'])) {
            $try[] = "{$_SERVER['USERPROFILE']}/.{$myDir}/$myConf";
        }
        $encoding = 'CP850';

    } else {

        $try[] = "/etc/$myDir/$myConf";
        $encoding = mb_internal_encoding();
        if (!$encoding) $encoding = 'UTF-8';

    }

    $SETTINGS['encoding'] = $encoding;

    if (isset($_SERVER['HOME'])) {
        $try[] = "{$_SERVER['HOME']}/.{$myDir}/$myConf";
    }

    mb_internal_encoding("UTF-8");

    $CONF = [];
    parseConfigFiles($try);
    $SETTINGS['try'] = $try;

    if (isset($CONF['optModes'])) {

        $keys = [];

        foreach ($CONF['optModes'] as $key => $mode) {
            if (!preg_match('/^[A-Za-z0-9]+$/',$key)) quit("Nome template parametri non valido: $key","optModes.{$key}");
            $keys["@$key"] = $mode;
        }

        $SETTINGS['optModes'] = $keys;
        $keys = array_keys($keys);
        $keys = getopt('',$keys);

        foreach ($keys as $key => $none) {

            $mode = $SETTINGS['optModes'][$key];
            $param = parseInternalOpts($mode,'optModes.'.substr($key,1));

            foreach ($param as $k => $v) {
                if ($k == 'title') continue;
                if (!isset($OPT[$k])) $OPT[$k] = $v;
            }
        }
    }

    if (isset($OPT['mapnum'])) $OPT['map'] = false;
    if (isset($OPT['plugin'])) {
        $SETTINGS['plugins'] = is_array($OPT['plugin']) ? $OPT['plugin'] : [ $OPT['plugin'] ];
        unset($OPT['plugin']);
    }

    if (isset($OPT['crw'])) {

        if (!is_array($OPT['crw'])) $OPT['crw'] = [$OPT['crw']];
        if (!isset($OPT['P'])) $OPT['P'] = [];
        if (!is_array($OPT['P'])) $OPT['P'] = [$OPT['P']];
        if (isset($OPT['p'])) $OPT['P'][] = $OPT['p'];
        unset($OPT['p']);

        foreach ($OPT['crw'] as $item) {

            if (preg_match('/(\\*|\\?|\\[|\\]|\\{|\\}|\\!)+/',$item)) {
                quit("Path non supportato: $item");
            }

            $item = fixPath($item);
            if (!is_dir($item)) quit("Directory errata: $item");
            $item = "{$item}*/.idea/workspace.xml";
            $ls = glob($item);
            $OPT['P'] = array_merge($OPT['P'],$ls);
        }

        $OPT['P'] = array_unique($OPT['P']);
        unset($OPT['crw']);

    }

    if (isset($OPT['per'])) {
        if (
            preg_match(
                '/^(?<y>[0-9]{4})\\/(?<m>[0-9]{1,2})\\/(?<d1>[0-9]{1,2})\\-(?<d2>[0-9]{1,2})$/',
                $OPT['per'],
                $match)
        ) {
            $OPT['dal'] = "{$match['d1']}/{$match['m']}/{$match['y']}";
            $OPT['al'] = "{$match['d2']}/{$match['m']}/{$match['y']}";

            unset($OPT['per']);

        } else {
            quit("Periodo non valido: {$OPT['per']}");
        }
    }

    if (isset($OPT['map'])) {
        if (isset($OPT['D'])) {
            unset($OPT['D']);
        } else {
            $OPT['D'] = false;
        }
    }

    $SETTINGS['head'] = isset($OPT['head']);
    $SETTINGS['scroll'] = isset($OPT['scr']) ? max(25,getConfig('tty.scrollEvery',25)) : 0;
    $SETTINGS['hr'] = !isset($OPT['hr']);

    if (isset($OPT['fuso'])) {

        $OPT['fuso'] = ltrim($OPT['fuso'],'+');

        if (is_numeric($OPT['fuso'])) {

            $SETTINGS['fuso'] = intval($OPT['fuso']) * 3600;

        } else {

            $doHelp = true;

        }

    } else {
        $SETTINGS['fuso'] = 0;
    }

    if ($SETTINGS['scroll']) $SETTINGS['head'] ^= true;

    if (isset($OPT['L'])) {
        if (!is_array($OPT['L']) and !isset($OPT['P']) and !isset($OPT['p'])) {
            $x = file($OPT['L'],FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($x === false) quit("Errore file: {$OPT['L']}");
            unset($OPT['L']);
            $OPT['P'] = $x;
        } else {
            $doHelp = true;
            $OPT = [];
        }
    }

    if (isset($OPT['tutti'])) {
        if (isset($CONF['projectsNameMap']) and count($CONF['projectsNameMap']) > 0) {

            if (!isset($OPT['P'])) $OPT['P'] = [];
            if (!is_array($OPT['P'])) $OPT['P'] = [$OPT['P']];
            if (isset($OPT['p'])) $OPT['P'][] = $OPT['p'];
            unset($OPT['p']);
            $OPT['P'] = array_merge(array_values($CONF['projectsNameMap']),$OPT['P']);
            $OPT['P'] = array_unique($OPT['P']);

        } else {
            quit("Non ci sono progetti configurati",'projectsNameMap');
        }
    }

    if (isset($OPT['P'])) {

        if (!is_array($OPT['P'])) $OPT['P'] = [$OPT['P']];
        if (isset($OPT['p'])) $OPT['P'][]= $OPT['p'];

        $OPT['P'] = array_unique($OPT['P']);

        if ($OPT['P']) {

            $SETTINGS['projList'] = [];

            foreach ($OPT['P'] as $item) {

                if (pathinfo($item,PATHINFO_EXTENSION) != 'xml') {
                    $item = fixPath($item) . '.idea/workspace.xml';
                }

                if (!file_exists($item)) quit("Manca il file: $item");
                $SETTINGS['projList'][] = $item;

            }
        }

        unset($OPT['p']);
        unset($OPT['P']);
    }

    foreach ($OPT as $item) {
        if (is_array($item)) $doHelp = true;
    }

    if (!isset($OPT['p']) and !isset($SETTINGS['projList'])) $doHelp = true;
    if (isset($OPT['G']) and isset($OPT['dati'])) $doHelp = true;

    $useESC = true;

    if (function_exists('stream_isatty')) {
        if (defined('STDOUT')) {
            if (!stream_isatty(STDOUT)) $useESC = false;
        } else {
            $useESC = false;
        }
    }

    if (isset($OPT['p'])) {

        $x = strripos($OPT['p'],'.xml');
        $y = strlen($OPT['p']);

        if ($x !== false and $x == $y-4) {

            $SETTINGS['byFile'] = true;
            $SETTINGS['path'] = dirname($OPT['p']);
            $SETTINGS['path'] = fixPath($SETTINGS['path']);
            $SETTINGS['project'] = $SETTINGS['path'] . basename($OPT['p']);

        } else {

            $SETTINGS['byFile'] = false;
            $SETTINGS['path'] = $OPT['p'];
            $SETTINGS['path'] = fixPath($SETTINGS['path']);
            $SETTINGS['project'] = "{$SETTINGS['path']}.idea/workspace.xml";

        }

    } else {

        $SETTINGS['byFile'] = false;
        $SETTINGS['path'] = realpath('.');
        $SETTINGS['path'] = fixPath($SETTINGS['path']);

    }

    $SETTINGS['grp2'] = getConfig('tty.numGraph',false);
    if (isset($OPT['G2'])) $SETTINGS['grp2'] ^= true;

    $try = [];

    if (isset($SETTINGS['path'])) {
        $try[] = "{$SETTINGS['path']}.idea/.{$myConf}";
        $try[] = "{$SETTINGS['path']}.{$myDir}/{$myConf}";
    }

    parseConfigFiles($try);

    $SETTINGS['try'] = array_merge($SETTINGS['try'],$try);
    $SETTINGS['profile'] = getConfig('main.profile','');

    if (isset($OPT['pr']) and preg_match('/^[A-Za-z0-9]+$/',$OPT['pr'])) {
        $SETTINGS['profile'] = $OPT['pr'];
    }

    $SETTINGS['pro'] = [];

    if ($SETTINGS['profile'] !='') {

        $done = [];
        $find = false;

        while($SETTINGS['profile'] != '') {

            if (!preg_match('/^[A-Za-z0-9]+$/',$SETTINGS['profile'])) {
                quit("Profilo non valido: {$SETTINGS['profile']}");
            }

            if (in_array($SETTINGS['profile'],$done)) {
                quit("Profilo il loop: {$SETTINGS['profile']}");
            }

            $try = $SETTINGS['try'];

            foreach ($try as $item) {

                $item = dirname($item);
                $item = fixPath($item);
                $item.= "profile/{$SETTINGS['profile']}.conf";
                $current = $SETTINGS['profile'];

                if (file_exists($item)) {
                    $done[] = $SETTINGS['profile'];
                    $SETTINGS['profile'] = '';
                    parseConfigFiles([$item]);
                    $SETTINGS['try'][] = $item;
                    $find = true;
                    break;
                }

                if ($find) {
                    break;
                } else {
                    quit("Nessun file profilo trovato per: $current\n");
                }

            }

        }

        $SETTINGS['pro'] = $done;

    }

    $SETTINGS['encoding'] = getConfig('tty.encoding',$encoding);
    $SETTINGS['direct'] = getConfig('tty.noEncode',false);
    $SETTINGS['colors'] = getConfig('tty.colors',$useESC);
    $SETTINGS['palette'] = getConfig('tty.usePalette',true);
    $SETTINGS['stdColors'] = getConfig('tty.standardColors',false);
    $SETTINGS['minLen'] = getConfig('count.minLen',120);
    $SETTINGS['windows'] = $isWindow;

    if (isset($OPT['noe'])) $SETTINGS['direct'] = true;

    $SETTINGS['fSab'] = getConfig('count.sabFest',true);
    $SETTINGS['fDom'] = getConfig('count.domFest',true);

    foreach ($WEEK_DAYS as $id => $data) {
        $WEEK_DAYS[$id][3] = getConfig("count.{$data[1]}Fest",$data[3]);
        $WEEK_DAYS[$id][2] = hexdec(getConfig("count.{$data[1]}Color",$data[2]));
    }

    $validGroup = ['project','work','fest','vacation','useDefault'];

    if (isset($CONF['fest'])) {

        foreach ($CONF['fest'] as $group => $list) {
            if ($group == 'useDefault') continue;

            if (!in_array($group,$validGroup) or !is_array($list)) quit("Errore configurazione: fest.$group");

            foreach ($list as $item) {

                $item = "$item/1/1";
                $item = str_replace(['\\','-',':','.'],'/',$item);
                $item = explode('/',$item);

                foreach ($item as &$x) {
                    $x = intval(trim($x));
                }
                unset($x);

                switch ($group) {

                    case 'work':
                        addDayType($item[1],$item[0],0,false);
                        break;

                    case 'fest':
                        addDayType($item[1],$item[0],0,true);
                        break;

                    case 'vacation':
                        addDayType($item[1],$item[0],$item[2],true);
                        break;

                    case 'project':
                        addDayType($item[1],$item[0],$item[2],false);
                        break;

                }

            }

        }
    }

    if (getConfig('fest.useDefault',true)) {
        addDayType(1,1,0,true);
        addDayType(1,6,0,true);
        addDayType(4,21,0,true);
        addDayType(4,21,0,true);
        addDayType(4,35,0,true);
        addDayType(5,1,0,true);
        addDayType(6,2,0,true);
        addDayType(8,15,0,true);
        addDayType(11,1,0,true);
        addDayType(12,8,0,true);
        addDayType(12,25,0,true);
        addDayType(12,26,0,true);
    }

    if (isset($OPT['colori'])) $SETTINGS['colors'] = true;
    if (isset($OPT['mono'])) $SETTINGS['colors'] = false;

    if ($SETTINGS['stdColors']) {

        $TTY_PALETTE = $VGA_COLOR;
        $SETTINGS['origPalette'] = $TTY_PALETTE;

    } else {

        foreach($PALETTE as $id => $item) {

            $r = 15 & ($item >> 8);
            $g = 15 & ($item >> 4);
            $b = 15 & $item;

            $TTY_PALETTE[$id] = ttyPalette($r,$g,$b);
            if ($id>15) $VGA_COLOR[$id] = $TTY_PALETTE[$id];

        }

        $SETTINGS['origPalette'] = $TTY_PALETTE;

        if (isset($CONF['palette'])) {
            $o = $TTY_PALETTE;
            foreach ($CONF['palette'] as $key => $value) {

                if (!preg_match('/^color(?<id>[0-9a-fA-F]{1,3})$/',$key, $palId)) {
                    quit("Parametro sconosciuto: $key","palette.{$key}");
                }

                $pId = hexdec($palId['id']);

                if (!preg_match('/^(?<id>[0-9a-fA-F]{1,3})$/',$value, $palVal)) {
                    quit("Valore errato: $value","palette.{$key}");
                }

                $pal = hexdec($palVal['id']);
                if (!isset($TTY_PALETTE[$pal])) quit("Colore sconosciuto: {$palVal['id']}","palette.{$key}");
                if (!isset($TTY_PALETTE[$pId])) quit("Colore sconosciuto: {$palId['id']}","palette.{$key}");
                $o[$pId] = $TTY_PALETTE[$pal];

            }
        }

        clearPlugins();

        $list = getConfig('main.plugin',[]);
        if ($list) {
            $list = array_values($list);
            $SETTINGS['plugins'] = isset($SETTINGS['plugins']) ? array_merge($SETTINGS['plugins'],$list) : $list;
        }

        if (isset($SETTINGS['plugins'])) {

            $SETTINGS['plugins'] = array_unique($SETTINGS['plugins']);

            foreach ($SETTINGS['plugins'] as $item) {
                addPlugin($item);
            }

            unset($SETTINGS['plugins']);
            initPlugins();

        }

        if (isset($OPT['132'])) set132Mode();

    }

    if (isset($CONF['heatMap']) and is_array($CONF['heatMap'])) {

        $HEAT_MAP = [];
        $HEAT_CHARS = ['■','░','▒','▓','█'];
        $HEAT_COL   = [15 , 4 , 4 , 13, 12];

        foreach ($CONF['heatMap'] as $key => $value) {

            if (preg_match('/^hour(?<l>(|[0-2].)[0-9].)$/',$key,$match) and is_numeric($value) and $value > -1 and $value < 5) {

                $match = intval($match['l']) % 24;
                $HEAT_MAP[$match] = [ $value , $HEAT_CHARS[$value], $HEAT_COL[$value] ];

            } else {

                quit("Errore livello: heatMap $key");

            }
        }
    }

    if (isset($OPT['dal'])) $SETTINGS['from'] = floor(parseDate($OPT['dal']) / 1440);
    if (isset($OPT['al'])) $SETTINGS['to'] = floor(parseDate($OPT['al']) / 1440);

    for ($i = 0; $i < 5 ; $i++) {

        $key ="H$i";

        if (isset($OPT[$key])) {

            if (!isset($SETTINGS['heat'])) $SETTINGS['heat'] = [];
            $SETTINGS['heat'][$i] = intval($OPT[$key]);

        } else {

            if (isset($OPT["h$i"])) {
                if (!isset($par['heat'])) $SETTINGS['heat'] = [];
                $SETTINGS['heat'][$i] = 1;
            }

        }
    }

    if (!$SETTINGS['direct']) {

        $text = "\x09\x20\x0a\x1b";
        $test = mb_convert_encoding($text,$SETTINGS['encoding'],'UTF-8');
        if ($text != $test) quit("Encoding non supportato: {$SETTINGS['encoding']}");

        $SETTINGS['substChar'] = [];
        foreach ($LEGACY_CHAR as $item) {
            $test = mb_convert_encoding($item[0],$SETTINGS['encoding'],'UTF-8');
            if ($test == '?') $SETTINGS['substChar'][] = $item;
        }

    }

    if (isset($OPT['ascii'])) $SETTINGS['substChar'] = $LEGACY_CHAR;

    pluginCall('onStart',true);
    if (isset($OPT['palette'])) helpPalette(); // Quit
    if ($doHelp) doHelp();
    if (!file_exists($SETTINGS['path']) and !is_dir($SETTINGS['path'])) quit("Errore percorso: {$SETTINGS['path']}");

}

function parseConfigFiles($array) {
    global $CONF;
    if (!is_array($CONF)) $CONF = [];

    foreach ($array as $file) {
        if (file_exists($file)) {
            $ini = parse_ini_file($file,true);
            if (!$ini) quit("Errore lettura: $file");
            $CONF = array_replace_recursive($CONF,$ini);
        }
    }

}

function parseInternalOpts($iParam,$conf = null) {

    $byStr = explode(' ',$iParam);

    $j = count($byStr);
    $par = [];
    $cPar = null;

    for ($i = 0 ; $i < $j; $i++) {

        $str = $byStr[$i];

        if ($str[0] == '-') {

            $cPar = ltrim($str,'-');

            if (isset($par[$cPar])) {

                if (is_array($par[$cPar])) {

                    $par[$cPar][] = '';

                } else {

                    $par[$cPar] = [$par[$cPar]];
                    $par[$cPar][] = '';

                }

            } else {

                $par[$cPar] = false;

            }

        } elseif ($cPar !== null) {

            if ($par[$cPar] === false) {

                $par[$cPar] = $str;

            } else {

                if (is_array($par[$cPar])) {

                    if ($par[$cPar][0] === false) quit("Errore parametri interni: $iParam", $conf);

                    $c = count($par[$cPar]) - 1;

                    if ($par[$cPar][$c] == '') {

                        $par[$cPar][$c] = $str;

                    } else {
                        $par[$cPar][$c] .= " $str";
                    }

                } else {

                    $par[$cPar] .= " $str";

                }

            }

        } else {

            quit("Errore parametri interni: $iParam", $conf);

        }

    }

    return $par;

}

function getConfig($path,$default) {
    global $CONF;

    $cur = $CONF;
    $pathA = explode('.',$path);

    foreach ($pathA as $item) {

        if (isset($cur[$item])) {

            $cur = $cur[$item];

        } else {

            return $default;

        }
    }

    if (is_bool($default)) {

        if (is_bool($cur)) return $cur;

        if (is_numeric($cur)) {
            if ($cur == 0) return false;
            if ($cur == 1) return true;
        }

    } elseif (is_string($default)) {

        if (is_scalar($cur) and !is_bool($cur)) return "$cur";

    } else {

        if (
            is_scalar($cur) and !is_bool($cur) and
            is_numeric($cur) and !is_infinite($cur) and
            !is_nan($cur)
        ) {

            if (is_int($default)) return intval($cur);
            if (is_float($default)) return floatval($cur);
            quit("Errore nel valore di default",$path);

        }

    }

    if (is_bool($cur)) {
        $cur = $cur ? '[true]':'[false]';
    } elseif (is_array($cur)) {
        $cur = '[Array]';
    } elseif (is_null($cur)) {
        $cur = '[null]';
    } elseif (!is_scalar($cur)) {
        $cur = '['.gettype($cur).']';
    }

    quit("Tipo di valore errato: $cur", $path);
    exit(1); // Gia fatto!
}
