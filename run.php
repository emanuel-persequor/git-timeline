<?php
require_once (__DIR__.'/vendor/autoload.php');
require_once (__DIR__.'/config.php');

use PSQR\Git;


$repo = new Git\Repository($repoGitPath);
$repo->init(true);


$digraph = new \PSQR\Graphviz\DiGraph($repo);
$digraph->generate();
$digraph->output("pdf", __DIR__.'/test.pdf');

$html = new \PSQR\HTML\Timeline($repo);
$html->generate();
$html->output(__DIR__.'/test.html');
