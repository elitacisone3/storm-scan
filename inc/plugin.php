<?php
function addPlugin($name, $bySetting = null) {
    global $SETTINGS;
    global $PLUGIN;

    $name = ucfirst($name);
    if (!preg_match('/^[A-Za-z]{1}[A-Za-z0-9]{0,39}$/',$name)) quit("Nome plugin non valido: $name",$bySetting);

    if (isset($PLUGIN[$name])) quit("Plugin $name gia attivato",$bySetting);

    $file = "{$SETTINGS['myPath']}plugins/{$name}.php";
    if (!file_exists($file) or !is_file($file)) quit("Errore caricamento plugin: $file",$bySetting);

    call_user_func(function ($_PLUGIN_FILE_) { require $_PLUGIN_FILE_;  }, $file);
    $class = "pScan\\plugins\\$name";
    if (!class_exists($class)) quit("Errore parsing plugin: $name",$bySetting);

    $PLUGIN[$name] = new $class();

}

function getPluginsList() {
    global $SETTINGS;
    $pattern = "{$SETTINGS['myPath']}plugins/*.php";
    $list = glob($pattern);
    $o = [];
    foreach ($list as $item) {
        $name = pathinfo($item,PATHINFO_FILENAME);
        $name = ucfirst($name);
        $o[] = $name;
    }

    return $o;
}

function clearPlugins() {
    global $PLUGIN;
    $PLUGIN = [];
}

function initPlugins() {
    global $PLUGIN;
    $ptr = 0;

    $tmp = [];
    foreach ($PLUGIN as $name => $obj) {

        if (method_exists($obj,'getPriority')) {

            $pri = $obj->getPriority();
            $pri = is_numeric($pri) ? intval($pri) : 0;

        } else {

            $pri = 0;

        }

        $sid = base_convert($pri,10,36);
        $sid = str_pad($sid,6,'0',STR_PAD_LEFT);
        $sid.= str_pad(base_convert($ptr,10,36),2,'0',STR_PAD_LEFT);
        $tmp[$sid] = [ $name, $obj ];
        $ptr++;
    }

    krsort($tmp);
    $PLUGIN = [];
    foreach ($tmp as $item) {
        $PLUGIN[$item[0]] = $item[1];
    }

    $chanMap = pluginCall('getChannels',true);
    foreach ($chanMap as &$item) {
        if (!is_array($item)) $item = [$item];
    }
    unset($item);

    foreach ($PLUGIN as $name => $obj) {

        if (isset($chanMap[$name])) {

            $testMap = $chanMap;
            unset($testMap[$name]);
            foreach ($chanMap[$name] as $reqChannel) {
                foreach ($testMap as $conflict => $channels) {
                    if (in_array($reqChannel,$channels)) {
                        quit("Il plugin $conflict va in conflitto con il plugin $name");
                    }
                }
            }

        }

    }

}

function getPlugins() {
    global $PLUGIN;
    return $PLUGIN;
}

function pluginFixArray(&$array, $method, $forAll, ...$params) {
    $x = _pluginCall($method,$forAll,$params);

    if ($forAll) {

        foreach ($x as $item) {
            if (is_array($item)) $array = array_merge_recursive($array,$item);
        }

    } else {
        if (is_array($x)) $array = array_merge_recursive($array,$x);
    }

}

function pluginCall($method, $forAll, ...$params) {
    return _pluginCall($method,$forAll,$params);
}

function _pluginCall($method, $forAll, $params) {
    global $PLUGIN;

    $params = func_get_args();

    $out = $forAll ? [] : null;

    foreach ($PLUGIN as $name => $obj) {
        try {

            if (method_exists($obj,$method)) {

                $x = call_user_func_array([$obj,$method],$params);
                if ($x !== null) {

                    if ($forAll) {
                        $out[$name] = $x;
                    } else {
                        return $x;
                    }

                }

            }

        } catch (Exception $error) {

            echo "\n";
            tty(12,0,$error->getMessage());
            tty(4,0," dal plugin $name al metodo $method");
            echo "\n";
            tty(4,0,$error->getTraceAsString());
            echo "\n";
            quit("Errore plugin");

        }

    }

    return $out;
}