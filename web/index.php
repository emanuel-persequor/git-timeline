<?php
require_once (__DIR__.'/../vendor/autoload.php');
require_once (__DIR__.'/../config.php');

use PSQR\Git;


// Initialize the repository
$repo = new Git\Repository($repoGitPath);
$repo->init(false);


// Output as HTML and SVG
$html = new \PSQR\HTML\Timeline($repo);
$html->generate();
$html->output();