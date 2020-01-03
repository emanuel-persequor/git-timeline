<?php
namespace PSQR\Git;

require_once (__DIR__.'/Commit.php');
require_once (__DIR__.'/Branch.php');


class Repository
{
    private $gitdir;
    /**
     * @var Commit[]
     */
    private $commits = array();
    /**
     * @var Branch[]
     */
    private $branches = array();

    public function __construct($gitdir)
    {
        $this->gitdir = $gitdir;
    }

    public function git($cmd, $columns,$delimiter="|") {
        //$gitdir = "/home/emanuel/Development/saga-workbench/.git";
        //$gitdir = "/home/emanuel/Development/SAGA/saga/.git";
        exec("git --git-dir=\"$this->gitdir\" ".$cmd, $output);

        foreach($output as &$line) {
            $line = trim($line);
            if(count($columns) > 1) {
                $line = array_combine($columns, explode($delimiter, $line, count($columns)));
            }
        }
        return $output;
    }

    public function init() {
        // Commits
        $lines = $this->git('log --reverse --all --parents --pretty=format:"%H|%h|%p|%an|%cI|%aI|%s"', array('sha','short','parents','author','commit_date','author_date','subject'));
        print("Loading ".count($lines)." commits: ");
        foreach($lines as $l) {
            $this->commits[$l['short']] = new Commit($this, $l);
            print(".");
        }
        print("Done\n");

        // TODO: Link Children/Parent
        foreach($this->commits as $commit) {
            $commit->initLinks();
        }

        // Get all branches
        $branches = $this->git('for-each-ref --format="%(objectname:short)|%(refname:short)"', array('ref', 'name'));

        // Get all refs
        foreach($branches as $indx => $l) {
            if(preg_match('/^origin\\/(.+)$/', $l['name'], $matches)) {
                $branch = new Branch($this, $l);
                $history = $this->git('log --reverse --first-parent --pretty=format:"%h" '.$branch->getRef(), array('ref'));
                foreach($history as $item) {
                    $branch->addCommit($this->commits[$item]);
                }
                $this->branches[] = $branch;
            }
        }

    }

    /**
     * @return Commit[]
     */
    public function getCommits()
    {
        return $this->commits;
    }

    /**
     * @param $sha
     * @return Commit
     */
    public function getCommit($sha)
    {
        if(!isset($this->commits[$sha])) {
            throw new \Exception("No such commit: ".$sha);
        }
        return $this->commits[$sha];
    }

    public function getBranches()
    {
        return $this->branches;
    }

}