<?php

function doHelpSyntax($tokenList) {

    $text = [];
    $line = [];
    $len = 0;

    foreach ($tokenList as $token) {
        $line[] = $token;
        $len+= 1 + strlen($token);
        if ($len > 75) {
            $text[] = implode(' ',$line);
            $line = [];
            $len = 0;
        }
    }

    if ($line) $text[] = implode(' ',$line);

    $out = '';
    foreach ($text as $line) {
        $out.="~$line\n";
    }

    return $out;

}

function doHelpParameters($paramArray) {
    $out = '';
    foreach ($paramArray as $key => $value) {
        $out.="   {$key} ~$value\n";
    }
    return $out;
}

function doHelp() {
    global $argv;
    global $SETTINGS;

    $syntaxToken = [];
    $paramMap = [];
    if (isset($SETTINGS['optModes'])) {
        foreach ($SETTINGS['optModes'] as $key => $data) {
            $data = parseInternalOpts($data);
            $syntaxToken[] = "[ --{$key} ]";
            if (isset($data['title'])) {
                $paramMap["--{$key}"] = $data['title'];
            }
        }
    }

    $data = pluginCall('getHelpSyntaxToken',true);
    foreach ($data as $datum) {
        $syntaxToken = array_merge($syntaxToken,$datum);
    }

    $data = pluginCall('getHelpParamMap',true);
    foreach ($data as $datum) {
        $paramMap = array_merge($paramMap,$datum);
    }

    $map = [
        'SYNTAX'        =>  doHelpSyntax($syntaxToken),
        'PARAMETERS'    =>  doHelpParameters($paramMap)
    ];

    outPres();

    $text= <<<HELP
~    { -p <progetto> | -L <file> [ -v ] [ -u ] | -P <progetto> ... [ -v ] [ -u ]  }  
~    [ --dal <data> ] [ --al <data> ] [ { --colori | --mono } ] [ --dati ] [ --plugin <nome> ]
~    [ -D ] [ { -E | -e } ] [ { --mesi [ --h ] [ -X ] | [ -H ] [ -o ] [ -N ] } ] [ -O ]
~    [ -T ] { [ --h0 ] [ --h1 ] [ --h2 ] [ --h3 ] [ --h4 ] | [ --H0 <num> ] [ --H1 <num> ] 
~    [ --H2 <num> ] [ --H3 <num> ] [ --H4 <num> ] } [ --salva <file> ] [ --ascii ] [ --G2 ]
~    [ --pr <profilo> ] [ --head ] [ --scr ] [ --per <peridodo> ] [ --noe ] [ --132 ]
~    [ --crw <path> ] ... [ -q ] [ { --map | --mapnum } ] [ --nomi ] [ --fuso <ore> ]
~    [ --palette ] [ --tutti ]
%%SYNTAX%%

~    Analizza un progetto PhpStorm e ne estrae le statistiche:
    
    -p      	~Specifica il progetto da analizzare.
    -q          ~Toglie la presentazione.
    -P          ~Specifica più progetti.\\n(usa più volte l'opzione).
    -L          ~Specifica unn file con la lista dei progetti.
    -v          ~Imposta il verbose durante il merge dei progetti.
    -u          ~Somma i progetti più volte\\n(N.B.: Invalida i calcoli, contano solo le caselle).
    --map       ~Visualizzazione avanzata con simboli di progetto.
    --mapnum    ~Visualizzazione avanzata con contantori per casella\\ndi progetto.
    --noe       ~Codifica l'output in UTF-8.
    --per       ~Filtra per mese nel formato: aaaa/mm/gg-gg\\n(Specifica un intervallo di giorni in un mese).
    --crw       ~Scannerizza una o più directory cercando progetti.\\n(Usabile più volte).
    --salva     ~Salva i dati in json o json.gz
    --colori	~Crea il report a colori.
    --ascii     ~Usa solo caratteri ASCII.
    --mono		~Crea il report senza colori.
    --dal       ~Filtra iniziando dalla data.
    --al    	~Filtra finendo con la data.
    --mesi		~Crea report mensile.
    --G2        ~Usa i grafici con i numeri dentro.
    --dati      ~Visualizza solo il report finale.
    --pr        ~Carica un profilo di configurazione.
    --head      ~Reimposta l'intestazione iniziale.
    --scr       ~In base alla configurazione ripete l'intestazione.
    --nomi      ~Visualizza a destra il NomeId del progetto.\\n(Se ci sono più progetti visualizza un asterico).
    --fuso      ~Imposta un fuso orario in ore.
    --plugin    ~Attiva plugin (anche multipli).
    --132       ~Imposta il terminale a 132 caratteri.
    --palette   ~Visualizza la tavolozza dei colori ed esce.\\n(Visualizza anche la tabella codici colore per\\ncreare i simboli colorati).
    --tutti     ~Elabora tutti i progetti.\\n(Richiede una configurazione della sezione "projectsNameMap").
    -T          ~Visualizza solo le tabelle.
    -D		    ~Usa i puntini anzichè il numero del giorno/ore\\n(Se usato con --map inverte l'impostazione).
    -H		    ~Visualizza la mappa di calore nel modo giorni.
    -h		    ~Visualizza la mappa di calore nel modo mesi.
    -X          ~Visualizza dati extra nella modalità mensile.
    -o		    ~Toglie la colonna override.
    -O          ~Visualizza gli override nella griglia giornaliera.
    -e		    ~Inserisce i giorni mancanti come numeri.
    -E		    ~Inserisce tutte le parti mancanti.\\n(Nella modalità --mesi le opzioni -E ed -e\\nhanno lo stesso effetto).
    -N		    ~Toglie le suddivisioni delle giornate.\\n(Le suddivisioni M P S N ).
%%PARAMETERS%%

HELP;

    $name = pathinfo($argv[0],PATHINFO_FILENAME);
    foreach ($map as $k => $v) {
        $text = str_replace("%%{$k}%%",$v,$text);
    }
    $map = null;

    $text = preg_replace('/[\x20\x09]+/',' ',$text);

    $text.="Filtri:\n";

    for ($i = 0; $i< 5; $i++) {
        $text.="--h{$i}~Filtra per livello $i.\n";
    }

    $text.="\nFiltri estesi:\n";

    for ($i = 0; $i< 5; $i++) {
        $text.="--H{$i} ~Filtra per livello $i con valore minimo.\n";
    }

    $text = trim($text);
    $text = explode("\n",$text);

    $HILITE = [
        ['/\\x5b|\\x5d|\\x7b|\\x7d|\\x7c/', 3],
        ['/\\<[A-Za-z]+\\>/', 15],
        ['/\\.\\.\\./',3],
        ['/\\-{1,2}\\@{0,1}[A-Za-z0-9]+/',10]
    ];

    foreach ($text as $ptr => $line) {
        $line = trim($line);

        if ($line != '' and $line[0] == '.') {
            $line = substr($line,1);


            pluginCall('doHelp'. ucfirst($line),true);
            continue;
        }

        $tok = explode('~',$line);
        if (count($tok) == 1) {
            $tok[0] = trim($tok[0]);
            echo str_repeat(' ',16);
            tty(10,0,$tok[0]);
            echo "\n";
        } else {
            $tok[0] = trim($tok[0]);
            $tok[1] = trim($tok[1]);
            if ($tok[0] == '') {
                $tok = explode(' ',$tok[1]);
                if ($ptr == 0) tty(15,0,"$name "); else echo "    ";
                foreach ($tok as $item) {
                    $d = false;
                    foreach ($HILITE as $mode) {
                        if (preg_match($mode[0],$item)) {
                            tty($mode[1],0,"$item ");
                            $d = true;
                            break;
                        }
                    }
                    if (!$d) tty(11,0, "$item ");
                }
                echo "\n";
            } else {

                echo '    ';
                tty(10,0,fixLen($tok[0],12));
                $subText = str_replace("\\n","\n",$tok[1]);
                $subText = trim($subText);
                $subText = explode("\n",$subText);
                foreach ($subText as $sPtr => $subLine) {
                    if ($sPtr > 0) echo str_repeat(' ',16);
                    tty(7,0,$subLine);
                    echo "\n";
                }
                if ($sPtr > 0) echo "\n";

            }
        }
    }

    $list = getPluginsList();
    if ($list) {
        echo "\n    ";
        tty(10,0,"Plugin disponibili:");
        echo "\n";

        foreach ($list as $item) {
            tty(7,0,"     {$item}");
            echo "\n";
        }
        echo "\n";
    }

    echo "\n    ";
    tty(10,0,"File di impostazioni:");
    echo "\n";
    foreach ($SETTINGS['try'] as $try) {
        echo "      ";
        $ok = file_exists($try);
        $char = $ok ? '√' : '·';
        tty($ok ? 3 : 8,0, "$char $try");
        echo "\n";
    }

    echo "\n";
    tty(13,0,"N.B.: ");
    tty(15,0,"Questo programma richiede un terminale con almeno 132 colonne.");
    echo "\n";
    tty(13,0,"N.B.: ");
    tty(15,0,"Tutte le opzioni con più progetti potrebbero falsare i conti.");
    echo "\n";
    pluginCall('doHelpExtraText',true);

    echo "\n";
    dispatch();
    exit;
}

function helpPalette() {
    global $SETTINGS;
    global $TTY_PALETTE;
    global $SYMBOLS;

    if (!$SYMBOLS) initSymbols();

    if (!$SETTINGS['colors']) {
        echo "\nColori disabilitati!\n";
        dispatch();
        exit(1);
    }

    tty(15,0,'Colori abilitati:');
    echo "\n\n";
    helpPaletteSection($TTY_PALETTE,true);
    echo "\n";
    tty(15,0,'Colori palette:');
    echo "\n\n";
    helpPaletteSection($SETTINGS['origPalette'],false);
    echo "\n";

    $text = [];
    $line = [];
    $len = 0;
    tty(15,0,'Test combinazioni:');
    echo "\n\n";
    foreach ($SYMBOLS['goodCombo'] as $combo) {

        $id = dechex($combo);
        $id = strtoupper($id);
        $id = str_pad($id,2,'0',STR_PAD_LEFT);

        $code = ttyS($combo >> 4, $combo & 15,'■■');
        $code.= ttyS(3,0,' = ');
        $code.= ttyS(15,0, fixLen($id,3));
        $text[] = $code;
        $len+=8;

        if ($len > 60) {
            $len = 0;
            $line[] = implode(' ',$text);
            $text = [];
        }

    }

    if ($text) $line[] = implode(' ',$text);
    foreach ($line as $text) {
        echo "  $text\n\n";
    }

    echo "\n";
    tty(11,0, '  * ');
    tty(3,0,"Per creare i simboli puoi usare un carattere, seguito da uno di\n    questi codici.");
    echo "\n\n";
    tty(15,0,'Test colori:');
    echo "\n\n";
    echo '  '. ttyS(13,0,fixLen('Gradiente rosso:',24)) . createGrad(1,0,0)."\n";
    echo '  '. ttyS(10,0,fixLen('Gradiente verde:',24)) . createGrad(0,1,0)."\n";
    echo '  '. ttyS(11,0,fixLen('Gradiente blu:',24)) . createGrad(0,0,1)."\n";
    echo '  '. ttyS(15,0,fixLen('Gradiente Bianco:',24)) . createGrad(1,1,1)."\n";
    echo "\n";

    dispatch();
    exit(0);

}

function createGrad($fR,$fG,$fB) {

    $o = '';
    for ($i =0 ;$i < 6; $i++) {
        $h = intval(($i / 5) * 15);
        $r = intval($h * $fR);
        $g = intval($h * $fG);
        $b = intval($h * $fB);
        if ($r>15) $r = 15;
        if ($g>15) $g = 15;
        if ($b>15) $b = 15;

        $str = ttyPalette($r,$g,$b);
        $o.= "\x1b[38;5;{$str}m█\x1b[m";
    }

    return $o;

}

function helpPaletteSection($palette, $pad) {

    $text = [];
    $line = [];
    $len = 0;

    foreach ($palette as $id => $color) {

        if ($pad and $id > 15) break;

        $id = dechex($id);
        $id = strtoupper($id);
        $id = $pad ? $id : str_pad($id,2,'0',STR_PAD_LEFT);

        $code = "\x1b[38;5;{$color}m██\x1b[m ";
        $code.= ttyS(3,0,'= ');
        $code.= ttyS(15,0, fixLen($id,3));
        $text[] = $code;
        $len+=8;

        if ($len > 60) {
            $len = 0;
            $line[] = implode(' ',$text);
            $text = [];
        }

    }

    if ($text) $line[] = implode(' ',$text);
    foreach ($line as $text) {
        echo "  $text\n\n";
    }

}