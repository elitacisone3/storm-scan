<?php

function initSymbols($reset = false) {
    global $SYMBOLS;
    global $SETTINGS;

    if (!is_array($SYMBOLS)) $SYMBOLS = [];

    $SETTINGS['symbolEGAColors'] = getConfig('symbols.EGAColors', false);
    $SETTINGS['symbolColors'] = getConfig('symbols.colors', $SETTINGS['colors']);
    $SETTINGS['symbolAutoColor'] = getConfig('symbols.autoColor', $SETTINGS['symbolColors']);
    $SETTINGS['symbolMoreCalc'] = getConfig('symbols.moreCalc',true);

    if ($SETTINGS['symbolColors']) {

        if ($SETTINGS['symbolEGAColors']) {

            $goodCombo =
                '31617181a1c1c1e1f1024272a2b2e2f2034373a3b3c3e'.
                '3f3047484a4b4e4f4056575a5b5d5e5f5064676a6b6e6'.
                'f6073747f7084878a8b8e8f81'
            ;

        } else {

            $goodCombo =
                '31718191a1b1c1d1e1f10272a2b2e2f2047484a4b4c4e'.
                '4f40525758595a5b5d5e5f5064676a6b6e6f607374787'.
                'd7f7084878a8b8e8f8094979a9b9d9e9f90a3a9adafa0'.
                'c4c7cbcecfc0d4d7dbdedfd0a2e8ece0f3f8fcf'
            ;
        }

    } else {
        $goodCombo = 'f0';
    }

    $goodCombo = hex2bin($goodCombo);
    $goodCombo = unpack('C*',$goodCombo);
    $SYMBOLS['goodCombo'] = array_values($goodCombo);

    if ($reset or !isset($SYMBOLS['cnt'])) $SYMBOLS['cnt'] = 0;
    if ($reset or !isset($SYMBOLS['char'])) $SYMBOLS['char'] = [];
    if ($reset or !isset($SYMBOLS['list'])) $SYMBOLS['list'] = [];
    if ($reset or !isset($SYMBOLS['uChr'])) $SYMBOLS['uChr'] = [];
    if ($reset or !isset($SYMBOLS['id'])) $SYMBOLS['id'] = [];

    if (!isset($SYMBOLS['no'])) {
        $SYMBOLS['no'] = explode(' ','& ! ? * + - . # @ § | ░ ▒ ▓ █ ■ ┌ ┬ ┐ ├ ┼ ┤ └ ┴ ┘ │ ─ € _ · : , ;');
        $SYMBOLS['no'][] = utf8_decode("\xa0");
    }

}

function createProjectSymbolByName($pid) {
    global $SYMBOLS;
    global $SETTINGS;
    if (!$SYMBOLS) initSymbols();
    $name = $pid;
    $name = strtoupper($name);
    $name = preg_replace('/[^A-Z]+/','',$name);

    if (!$SETTINGS['symbolMoreCalc']) {
        $ori = $name;
        if ($SYMBOLS['uChr']) $name = str_replace($SYMBOLS['uChr'],'',$name);

        if ($name == '') {
            $name = strtolower($ori);
            if ($SYMBOLS['uChr']) $name = str_replace($SYMBOLS['uChr'],'',$name);
        }

        if ($name == '') return createProjectSymbol($name);
    }

    $color = count($SYMBOLS['list']);
    $color+= 8;
    $color = $color % count($SYMBOLS['goodCombo']);
    $color = $SYMBOLS['goodCombo'][$color];

    return createProjectSymbol($pid,$name[0],$color);

}

function _createProjectSymbol($pid, $char, $color) {
    global $SYMBOLS;
    global $SETTINGS;

    if (is_string($color)) $color = hexdec($color);
    $color &= 0xff;

    if ($color == 0 or in_array($char,$SYMBOLS['no']) or ord($char) < 33 or is_numeric($char)) return false;

    $char = mb_substr("{$char}?",0,1);

    $j = $SETTINGS['symbolAutoColor'] ? count($SYMBOLS['goodCombo']) : 0;

    for ($i = -1; $i < $j;$i++) {

        if ($i > -1) $color = $SYMBOLS['goodCombo'][$i];
        if ($color == 0) continue;

        $str = ttyS($color & 15,$color >> 4, $char);

        if (!in_array($str,$SYMBOLS['char'])) {
            $SYMBOLS['char'][$pid] = $str;
            $SYMBOLS['list'][$pid] = [$char,$color];
            if (!in_array($char,$SYMBOLS['uChr'])) $SYMBOLS['uChr'][] = $char;
            return $str;
        }

    }

    return false;
}

function getSymbolsMap() {
    global $SYMBOLS;
    if (!$SYMBOLS) initSymbols();

    $o = [];
    foreach ($SYMBOLS['id'] as $sid => $id) {
        $o[$id] = isset($SYMBOLS['char'][$sid]) ? $SYMBOLS['char'][$sid] : ttyS(8,7,'!');
    }

    return $o;
}

function getSidMap() {
    global $SYMBOLS;
    if (!$SYMBOLS) initSymbols();
    $o = [];
    foreach ($SYMBOLS['id'] as $sid => $id) {
        $o[$id] = $sid;
    }

    return $o;
}

function setProjectId($pid) {
    global $SYMBOLS;
    if (!$SYMBOLS) initSymbols();
    if (!isset($SYMBOLS['id'][$pid])) $SYMBOLS['id'][$pid] = count($SYMBOLS['id']) + 1;
    return $SYMBOLS['id'][$pid];
}

function getProjectId($pid) {
    global $SYMBOLS;
    if (isset($SYMBOLS['id']) and isset($SYMBOLS['id'][$pid])) return $SYMBOLS['id'][$pid];
    return 0;
}

function getProjectById($id) {
    global $SYMBOLS;
    if (isset($SYMBOLS['id'])) return array_search($id,$SYMBOLS['id']);
    return false;
}

function getAllProjectsId() {
    global $SYMBOLS;
    if (isset($SYMBOLS['id'])) return $SYMBOLS['id'];
    return [];
}

function createProjectSymbol($pid, $char = null, $color = 0) {
    global $SETTINGS;
    global $SYMBOLS;
    global $OPT;

    if (!$SYMBOLS) initSymbols();

    if ($color == 0) $color = 0x70;

    if ($char) {
        if (isset($OPT['ascii']) and ord($char) > 127) {
            $char = null;
        } elseif (!$SETTINGS['direct']) {
            $text = mb_convert_encoding($char,$SETTINGS['encoding'],'UTF-8');
            $text = mb_convert_encoding($text,'UTF-8', $SETTINGS['encoding']);
            if ($text != $char) $char = null;
        }
    }

    if ($char) {

        $x = _createProjectSymbol($pid,$char,$color);
        if ($x) return $x;

        if (!is_numeric($char)) {
            $char = mb_strtolower($char);
            $x = _createProjectSymbol($pid,$char,$color);
            if ($x) return $x;
        }

    }

    for ($i = $SETTINGS['symbolMoreCalc'] ? 0 : $SYMBOLS['cnt']; $i < 52; $i++) {

        $char = $i % 26;
        $char += ($i > 26 ? 0x61 : 0x41);
        $char = chr($char);

        $x = _createProjectSymbol($pid,$char,$color);
        if ($x) return $x;
        $SYMBOLS['cnt']++;

    }

    $SYMBOLS['char'][$pid] = ttyS(8,7,'?');
    return $SYMBOLS['char'][$pid];

}

function getProjectSymbol($pid) {
    global $SYMBOLS;
    if (!isset($SYMBOLS['char']) or !isset($SYMBOLS['char'][$pid])) return ttyS(8,7,'!');
    return $SYMBOLS['char'][$pid];
}

function getProjectSymbolSource($pid) {
    global $SYMBOLS;

    if (!isset($SYMBOLS['char']) or !isset($SYMBOLS['char'][$pid])) return null;
    $code =  $SYMBOLS['list'][$pid];
    $raw = mb_convert_encoding($code[0],'UTF-16BE','UTF-8');
    $raw = unpack('n',$raw);

    $o= str_pad(dechex($code[1] & 0xFF ),2,'0',STR_PAD_LEFT);
    $o.= str_pad(dechex($raw[1] & 0xFFFF),4,'0',STR_PAD_LEFT);
    return '&H' . strtoupper($o);

}

function setProjectSymbolFromSource($pid, $code) {

    if (preg_match('/^&H(?<c>[0-9A-F]{2})(?<h>[0-9A-F]{4})$/',$code, $match)) {

        $char = hex2bin($match['h']);
        $color = hexdec($match['c']);
        $char = mb_convert_encoding($char,'UTF-8','UTF-16BE');
        if (!$char or $char == '?' or mb_strlen($char) > 1) $char = null;

    } elseif (preg_match('/^(?<h>.+)(?<c>[0-9A-Fa-f]{2})$/', $code,$match)) {

        $char = $match['h'];
        $color = hexdec($match['c']);
        if (mb_strlen($char,'UTF-8') != 1) $char = null;

    } else {

        $char = mb_substr("$code ",0,1,'UTF-8');
        $color = 0;

    }

    if (!$char) $color = 0;
    return createProjectSymbol($pid,$char,$color);

}

function getAllSymbolsSources() {
    global $SYMBOLS;
    $pids = array_keys($SYMBOLS['list']);
    $o = [];

    foreach ($pids as $pid) {
        $o[$pid] = getProjectSymbolSource($pid);
    }

    return $o;
}