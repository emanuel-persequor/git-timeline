<?php

$startDate = '2019-12-01';

function git($cmd, $columns,$delimiter="|") {
    //$gitdir = "/home/emanuel/Development/saga-workbench/.git";
    $gitdir = "/home/emanuel/Development/SAGA/saga/.git";
    exec("git --git-dir=\"$gitdir\" ".$cmd, $output);

    foreach($output as &$line) {
        $line = trim($line);
        if(count($columns) > 1) {
            $line = array_combine($columns, explode($delimiter, $line, count($columns)));
        }
    }
    return $output;
}

// Log
$commits = array();
$lines = git('log --reverse --all --parents --pretty=format:"%h|%p|%an|%cI|%aI|%s"', array('ref','parents','author','commit_date','author_date','short'));
foreach($lines as &$l) {
    if($l['author_date'] > $startDate || $l['commit_date'] > $startDate) {
        $l['branches'] = array();//git('branch -r --contains ' . $l['ref'], array('branch'));

        $branch = git('name-rev '.$l['ref'], array('ref','branch'), ' ');
        $l['branch'] = preg_replace("/^remotes\\/origin\\//", "", preg_replace("/[\\~\\^][0-9]+/", "", $branch[0]['branch']));
        print($l['branch']."\n");

        $commits[$l['ref']] = $l;
    }
}

//uasort($commits, function($v1,$v2){ return strtotime($v1['author_date']) - strtotime($v2['author_date']);});



// Get all branches
$branches = git('for-each-ref --format="%(objectname:short)|%(refname:short)"', array('ref', 'name'));

// Get all refs
foreach($branches as $indx => &$branch) {
    if(preg_match('/^origin\\/(.+)$/', $branch['name'], $matches)) {
        $branch['history'] = git('log --reverse --first-parent --pretty=format:"%h" '.$branch['ref'], array('ref'));
        $branch['short'] = substr($branch['name'], 7);
        foreach($branch['history'] as $ref) {
            if(isset($commits[$ref])) {
                $commits[$ref]['branches'][] = $branch['name'];
                //print_r($commits[$ref]['branches']); die();
            }
        }
    } else {
        unset($branches[$indx]);
    }
}
//print_r($branches); die();

//var_dump($branches);
//var_dump($commits); die();


/*
$csv = array(array('ref','Date'));
foreach($commits as $commit) {
    $csv[$commit['ref']] = array($commit['ref'], $commit['author_date']);
}

foreach($branches as $bIndx => $branch) {
    $csv[0][] = $branch['short'];
    foreach($csv as $ref => &$l) {
        if($l[0] != "ref") {
            $l[] = in_array($ref, $branch['history']) ? $bIndx : '';
        }
    }
}

foreach($csv as $l) {
    print(implode("\t", $l)."\n");
}
*/

$links = array();
/*
foreach($branches as $bIndx => $branch) {
    for($i = 0; $i < count($branch['history'])-1; $i++) {
        $links[] = 'ref'.$branch['history'][$i].' -> '.'ref'.$branch['history'][$i+1]."[color=red];";
    }
}
*/


$dot = "digraph {\nrankdir=LR; splines=polyline; \n";
$days = array();
foreach($commits as $c) {
    $dot .= "  ref".$c['ref']."[label=\"".$c['ref']."\",tooltip=\"".addslashes($c['short'])."\",shape=".("HEAD" == $c['branch'] ? 'doubleoctagon':'oval')."];\n";
    foreach(explode(" ", $c['parents']) as $p) {
        $links[] = 'ref'.$p.' -> '.'ref'.$c['ref'].";";
    }
    //$day = date("Y-m-d", strtotime($c['author_date']));
    $day = date("Y-m-d", strtotime($c['commit_date']));
    if(!isset($days[$day])) {
        $days[$day] = array();
    }
    $days[$day][] = "ref".$c['ref'];
}
$dot .= "\n".implode("\n", array_unique($links));


// Refs
foreach($branches as $bIndx => $branch) {
    if(isset($commits[$branch['ref']])) {
        $dot .= 'b'.md5($branch['name']).'[label="'.$branch['name'].'",shape=box,style=filled,fillcolor=green]'.";\n";
        $dot .= "b".md5($branch['name'])." -> ref".$branch['ref']."[style=dashed];\n";
        $dot .= "{ rank=same; b".md5($branch['name'])." ref".$branch['ref']."}\n";
    }
}

foreach($days as $commits) {
    if(count($commits) > 1) {
        $dot .= "{ rank=same; ".implode(" ", $commits)."}\n";
    }
}


$dot .= "\n}\n";

file_put_contents("test.dot", $dot);