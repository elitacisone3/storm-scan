<?php

function betweenGetAndReplace(&$ioText, $leftString, $rightString, $replaceString='') {
    $ii=strpos($ioText,$leftString);
    if ($ii===false) return false;
    $buffer=substr($ioText,$ii+strlen($leftString));
    $ff=strpos($buffer,$rightString);
    if ($ff===false) return false;
    $nw=substr($ioText,0,$ii);
    $nw.=$replaceString.substr($ioText,$ii+strlen($leftString)+strlen($rightString)+$ff);
    $ioText=$nw;
    return substr($buffer,0,$ff);
}

function set132Mode() {
    global $SETTINGS;
    if (@$SETTINGS['colors']) {

        echo "\n\x1b[?3h";
        echo "\x1b[2J\n";

    } else {

        echo "\n";

    }
}

function secondsToTime($seconds) {
    return floor($seconds / 3600).':'.numPad(floor($seconds / 60) % 60);
}

function simplify($str) {
    $str = preg_replace('/\\s+/',' ',$str);
    $str = preg_replace('/\\s*=\\s*/','=',$str);
    return trim($str,' ');
}

function numPad($st) { return str_pad($st,2,'0',STR_PAD_LEFT); }

function fixLen($text, $len = 8) {

    $strLen = mb_strlen($text);

    if ($strLen > $len) {
        return mb_substr($text,0,$len);
    } else {
        return $text . str_repeat(' ',$len - $strLen);
    }
}

function graph($num, $max, $width, $color = 3, $threshold = null, $colorHi = 10 , $colorOver = 13, $paper = 0, $zeroCol = null, $zeroVal = 0) {
    global $TTY_PALETTE;
    global $SETTINGS;

    $max = $max > 0 ? $max : 1;

    if (!$SETTINGS['colors']) return graphG($num,$max,$width);

    if ($zeroCol !== null and $num <= $zeroVal) $color = $zeroCol;
    if ($SETTINGS['grp2']) return graph2($num,$max,$width,$color,$threshold,$colorHi,$colorOver,$paper);

    if ($num > $max) {
        return ttyS($colorOver,$colorOver,str_repeat('█',$width-1) . '■');
    }

    $p = intval(($num / $max) * $width);
    if ($threshold !== null) {
        $t = intval(($threshold /$max) * $width);
        if ($t > $width) $t = $width;
    } else {
        $t= $width+1;
    }

    if ($p > $width) $p = $width;

    $m = count($TTY_PALETTE);
    $color = $TTY_PALETTE[abs($p >= $t ? $colorHi : $color) % $m];
    $paper = $TTY_PALETTE[abs($paper) % $m];

    $str = "\x1b[38;5;{$color}m";
    $str.=str_repeat('█',$p);
    $str.="\x1b[38;5;{$paper}m";
    $str.=str_repeat(' ',$width - $p);
    $str.="\x1b[m";

    return $str;

}

function graph2($num, $max, $width, $color = 3, $threshold = null, $colorHi = 10 , $colorOver = 13, $paper = 0) {

    $inStr = str_pad($num,$width,' ',STR_PAD_RIGHT);
    if (strlen($inStr) > $width) $inStr = str_repeat(' ',$width);

    if ($threshold !==null) {
        $p = intval(($threshold / $max) * $width);
        if ($p > $width) $p = $width;
        if ($p>0 and $p < $width and $inStr[$p] == ' ') {
            $inStr=substr($inStr,0,$p).'|'.str_repeat(' ',$width-$p-1);
        }
    }

    if ($num > $max) {

        $inStr=substr($inStr,0,-1).'■';
        $col = $colorOver;

        return ttyS(0,$col,$inStr);

    } else {
        $col = $num >= $threshold ? $colorHi : $color;
    }

    $p = intval(($num / $max) * $width);
    if ($p > $width) $p = $width;

    $out='';
    for ($i =0 ;$i<$width;$i++) {
        $pap = $i <= $p ? $col : $paper;
        $ink = $i <= $p ? $paper : $col;
        $out.= ttyS($ink,$pap,$inStr[$i]);
    }

    return $out;

}


function graphG($num, $max, $width) {
    if ($num > $max) return str_repeat('█',$width-1) .'■';

    $p = intval(($num / $max) * $width);

    return
        str_repeat('█',$p).
        str_repeat(' ',$width - $p)
        ;
}

function fixPath($path) {
    $path = str_replace('\\','/',$path);
    $path = rtrim($path,'/');
    $path.= '/';
    return $path;
}

function ttyPalette($r,$g,$b) {
    $r = 15 & $r;
    $g = 15 & $g;
    $b = 15 & $b;

    $r = intval(5.0 * ($r / 15));
    $g = intval(5.0 * ($g / 15));
    $b = intval(5.0 * ($b / 15));

    return 16 + 36 * $r + 6 * $g + $b;
}

function tty($ink, $paper, $string) {
    global $SETTINGS;
    global $TTY_PALETTE;

    if (@$SETTINGS['colors']) {

        $m = count($TTY_PALETTE);
        $inkC = $TTY_PALETTE[abs($ink) % $m];
        $papC = $TTY_PALETTE[abs($paper) % $m];
        $string = "\x1b[38;5;{$inkC}m\x1b[48;5;{$papC}m{$string}\x1b[m";

    }

    echo $string;

}

function ttyS($ink, $paper, $string) {
    global $SETTINGS;
    global $TTY_PALETTE;

    if (@$SETTINGS['colors']) {

        $m = count($TTY_PALETTE);
        $inkC = $TTY_PALETTE[abs($ink) % $m];
        $papC = $TTY_PALETTE[abs($paper) % $m];
        $string = "\x1b[38;5;{$inkC}m\x1b[48;5;{$papC}m{$string}\x1b[m";

    }

    return $string;

}

function ttyRGB($r,$g,$b, $paper, $string) {
    global $SETTINGS;
    global $TTY_PALETTE;

    if ($SETTINGS['colors']) {
        $m = count($TTY_PALETTE);
        $papC = $TTY_PALETTE[abs($paper) % $m];
        $inkC = ttyPalette($r,$g,$b);
        $string = "\x1b[38;5;{$inkC}m\x1b[48;5;{$papC}m{$string}\x1b[m";
    }

    return $string;
}

function quit($error, $confSetting = null) {
    global $SETTINGS;
    echo "\n";
    tty(13,0,$error);
    echo "\n";

    if ($confSetting) {

        tty(13,0," Parametro di configurazione: $confSetting");
        echo "\n";

        $list = [];
        foreach ($SETTINGS['try'] as $file) {
            if (file_exists($file)) $list[] = $file;
        }

        if ($list) {
            echo "\n";
            tty(4,0," File inclusi:");

            foreach ($list as $file) {
                tty(4,0," $file");
                echo "\n";
            }
        }

        echo "\n";

    }
    dispatch();
    exit(1);
}

function dispatch() {
    global $SETTINGS;

    $text = ob_get_clean();

    if (isset($SETTINGS['substChar'])) {
        foreach ($SETTINGS['substChar'] as $item) {
            $text = str_replace($item[0],$item[1],$text);
        }
    }

    $x = pluginCall('dispatch',false,$text);
    if ($x) $text = $x;

    if (isset($SETTINGS['direct'])) {
        if (!$SETTINGS['direct']) $text = mb_convert_encoding($text,$SETTINGS['encoding'],'UTF-8');
    }

    echo $text;

}

function tcrToTime($tcr) {
    return numPad(floor($tcr / 60) % 24) . ':' . numPad($tcr % 60);
}

function time2Tcr($d,$m,$y,$H, $i) {
    $tcr = floor(gmmktime(0,0,0,$m,$d,$y) / 86400);
    return (1440 * $tcr) + ($H * 60) + $i;
}

function parseDate($str) {
    $str = str_replace(['\\','-','.'],'/',$str);
    $str = trim($str,'/ ');
    $str = explode('/',$str);
    if (!isset($str[1])) $str[1] = date('m');
    if (!isset($str[2])) $str[2] = date('Y');
    return time2Tcr(intval($str[0]),intval($str[1]),intval($str[2]),0,0);
}

function getPerc($val, $max, $perc = 100) {
    if ($val > $max) return $val;
    if ($val == 0 and $max == 0) return 0;
    return ($val / $max) * $perc;
}

function hr($ink = 1, $paper = 0, $char = '─') {
    tty($ink,$paper,str_repeat($char,132));
    echo "\n";
}
