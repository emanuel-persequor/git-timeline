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
    /**
     * @var Commit
     */
    private $_firstCommit = null, $_lastCommit = null;
    /**
     * @var bool
     */
    private $verbose = true;
    public $defaultBranch;

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

    private function log($str)
    {
        if($this->verbose)
        {
            print($str);
        }
    }

    public function init($verbose=true) {
        $this->verbose = $verbose;

        // What is my default branch
        $this->defaultBranch = str_replace("origin/", "", $this->git("symbolic-ref --short refs/remotes/origin/HEAD", array())[0]);


        // Commits
        $lines = $this->git('log --reverse --all --parents --pretty=format:"%H|%h|%p|%an|%cI|%aI|%s"', array('sha','short','parents','author','commit_date','author_date','subject'));
        $this->log("Loading ".count($lines)." commits: ");
        foreach($lines as $l) {
            $this->commits[$l['short']] = new Commit($this, $l);
            $this->log(".");
        }
        $this->log("Done\n");

        // Get all branches
        $branches = $this->git('for-each-ref --format="%(objectname:short)|%(refname)|%(objecttype)"', array('ref', 'name', 'type'));
        foreach($branches as $l) {
            if(preg_match('/^refs\\/remotes\\/origin\\/(.+)$/', $l['name'], $matches) ||
                preg_match('/^refs\\/tags\\/(.+)$/', $l['name'], $matches))
            {
                $branch = new Branch($this, $l);
                if($branch->getVeryShortName() != 'HEAD')
                {
                    if($branch->isDefaultBranch())
                    {
                        // All commits in this branch live here
                        $history = $this->git('log --reverse --first-parent --pretty=format:"%h" '.$branch->getRef(), array('ref'));
                        foreach($history as $item) {
                            //$branch->addCommit();
                            $this->commits[$item]->setBranch($branch);
                        }
                    }
                    $this->branches[] = $branch;
                }
            }
            else
            {
                //
            }
        }

        // Link Children/Parent/Branches
        foreach($this->commits as $commit)
        {
            $commit->initLinks();
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

    public function getFirstCommit()
    {
        if(is_null($this->_firstCommit))
        {
            foreach($this->commits as $c)
            {
                if(is_null($this->_firstCommit) ||
                    $c->getAuthorDate() < $this->_firstCommit->getAuthorDate() ||
                    $c->getCommitDate() < $this->_firstCommit->getCommitDate()
                )
                {
                    $this->_firstCommit = $c;
                }
            }
        }
        return $this->_firstCommit;
    }

    public function getLastCommit()
    {
        if(is_null($this->_lastCommit))
        {
            foreach($this->commits as $c)
            {
                if(is_null($this->_lastCommit) ||
                    $c->getAuthorDate() > $this->_lastCommit->getAuthorDate() ||
                    $c->getCommitDate() > $this->_lastCommit->getCommitDate()
                )
                {
                    $this->_lastCommit = $c;
                }
            }
        }
        return $this->_lastCommit;
    }


    /**
     * @param $name
     * @return Branch
     * @throws \Exception
     */
    public function findBranch($name)
    {
        $branchNames = array();
        foreach ($this->branches as $branch)
        {
            if($branch->matchName($name))
            {
                return $branch;
            }
            $branchNames[] = $branch->name;
        }
        print_r($branchNames);
        throw new \Exception("No such branch: ".$name." we have (".count($branchNames).")");
    }
}