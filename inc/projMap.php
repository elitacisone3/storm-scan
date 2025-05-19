<?php

function getXMLFileName($pathOrName) {

    if (pathinfo($pathOrName,PATHINFO_EXTENSION) != 'xml') {
        $pathOrName = fixPath($pathOrName);
        $pathOrName.= '.idea/workspace.xml';
    } else {
        $pathOrName = str_replace('\\','/',$pathOrName);
    }

    if (!file_exists($pathOrName) or !is_file($pathOrName)) {
        quit("Errore file di progetto: $pathOrName");
    }

    return $pathOrName;

}

function getProjectNameByPath($pathOrName, $normalize = true) {

    if (pathinfo($pathOrName,PATHINFO_EXTENSION) == 'xml') {
        $pathOrName = dirname($pathOrName);
        $pathOrName = dirname($pathOrName);
    }

    $name = basename($pathOrName);
    if ($normalize) {

        $name = trim($name);
        $name = preg_replace('/[^A-Za-z0-9\\-_\\.]+/','_',$name);

        if ($name == '') {
            $pathOrName = fixPath($pathOrName);
            $name = crc32($pathOrName);
            $name^= $name >> 1;
            $name&= 0x7FFFFFFF;
            $name = base_convert($name,10,36);
            $name = strtoupper($name);
            return '@'.str_pad($name,6,'0',STR_PAD_LEFT);
        }
    }

    return $name;

}

function getProjectPath($pathOrName, $noError = false) {

    $x = realpath($pathOrName);
    if (!$x or !file_exists($x)) {
        if ($noError) return false;
        quit("Errore percorso: $pathOrName");
    }
    $pathOrName = $x;

    if (pathinfo($pathOrName,PATHINFO_EXTENSION) == 'xml') {
        $pathOrName = dirname($pathOrName);
        $pathOrName = dirname($pathOrName);

    }

    $pathOrName = fixPath($pathOrName);

    if (!is_dir($pathOrName)) {
        if ($noError) return false;
        quit("Errore percorso: $pathOrName");
    }

    return $pathOrName;

}

function getProjectConfigPath($pathOrName, $ofFile = null) {

    if (pathinfo($pathOrName,PATHINFO_EXTENSION) == 'xml') {
        $pathOrName = dirname($pathOrName);
        $pathOrName = dirname($pathOrName);

    }

    $pathOrName = fixPath($pathOrName);
    $pathOrName.= '.' . CONFIG_DIR_NAME . '/';

    if (!file_exists($pathOrName) or !is_dir($pathOrName)) return false;

    if ($ofFile) {
        $pathOrName.=$ofFile;
        if (!file_exists($ofFile) or is_dir($ofFile)) return false;
    }

    return $pathOrName;

}

function getOptProjectFile($path, $file) {
    if (!$path) return false;
    $path = fixPath($path);
    $file = $path . $file;
    if (file_exists($file) and !is_dir($file)) return $file;
    return null;
}
