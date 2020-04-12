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
    /**
     * @var bool
     */
    private $bareRepository;
    public $defaultBranch;
    /**
     * @var Commit[]
     */
    public $tags = array();

    public function __construct($gitdir)
    {
        $this->gitdir = $gitdir;
    }

    public function git($cmd, $columns,$delimiter="|")
    {
        exec("git --git-dir=\"$this->gitdir\" ".$cmd, $output);

        foreach($output as &$line)
        {
            $line = trim($line);
            if(count($columns) > 1)
            {
                $line = array_combine($columns, explode($delimiter, $line, count($columns)));
            }
        }
        return $output;
    }

    private function gitRaw($cmd)
    {
        return implode("\n", $this->git($cmd, array()));
    }

    private function log($str)
    {
        if($this->verbose)
        {
            print($str);
        }
        else if($str != ".")
        {
            file_put_contents('php://stderr', "[".date("Y-m-d H:i:s")."]: ".$str."\n");
        }
    }

    private function error($str)
    {
        file_put_contents('php://stderr', "[".date("Y-m-d H:i:s")."]: ".$str."\n");
    }

    public function init($verbose=true) {
        $this->verbose = $verbose;

        // Is this a bare repository
        $this->bareRepository = ($this->gitRaw("rev-parse --is-bare-repository") === "true");

        // What is my default branch
        $this->defaultBranch = str_replace("origin/", "", $this->gitRaw("symbolic-ref --short ".($this->bareRepository?"HEAD":"refs/remotes/origin/HEAD")));

        // Commits
        $lines = $this->git('log --reverse --all --parents --pretty=format:"%H|%h|%P|%an|%ae|%cI|%aI|%s"', array('sha','short','parents','author','author_email','commit_date','author_date','subject'));
        $this->log("Loading ".count($lines)." commits: ");
        foreach($lines as $l) {
            $this->commits[$l['sha']] = new Commit($this, $l);
            $this->log(".");
        }
        $this->log("Done\n");

        // Add modified/added/deleted stats
        $this->log("Reading changed/added/deleted for the ".count($lines)." commits: ");
        $lastSha = null;
        foreach($this->git("log --reverse --all --shortstat --pretty=%H", array()) as $line) {
            $line = trim($line);
            if($line == "") {
                // DO Nothing
            } else if(preg_match('/^[0-9a-f]{40}$/', $line)) {
                $lastSha = $line;
            } elseif(preg_match('/(?<files_changed>[0-9]+) files? changed(, (?<lines_added>[0-9]+) insertion[^,]+)?(, (?<lines_deleted>[0-9]+) deletion.*)?$/', $line, $matches)) {
                $c = $this->getCommit($lastSha);
                foreach(array('files_changed', 'lines_added', 'lines_deleted') as $f) {
                    $c->data[$f] = (int)@$matches[$f];
                }
            } else {
                die("$line\n");
            }
        }
        $this->log("Done\n");

        // Get all branches
        $branches = $this->git('for-each-ref --format="%(objectname)|%(refname)|%(objecttype)"', array('objectname', 'refname', 'objecttype'));
        foreach($branches as $l)
        {
            if($this->bareRepository ||
                preg_match('/^refs\\/remotes\\/origin\\/(.+)$/', $l['refname'], $matches) ||
                preg_match('/^refs\\/tags\\/(.+)$/', $l['refname'], $matches))
            {
                $branch = new Branch($this, $l);
                if($branch->getVeryShortName() != 'HEAD')
                {
                    if($branch->isDefaultBranch())
                    {
                        // All commits in this branch live here
                        $history = $this->git('log --reverse --first-parent --pretty=format:"%H" '.$branch->refname, array('ref'));
                        foreach($history as $item)
                        {
                            //$branch->addCommit();
                            $this->commits[$item]->setBranch($branch);
                        }
                    }
                    $this->branches[] = $branch;
                }
            }
        }

        // Read 'name-rev' for all commits
        $lines = $this->git("name-rev --refs \"".($this->bareRepository?"*heads*":"*refs/remotes/origin*")."\" --all --always", array("sha", "name"), " ");
        $this->log("Set name-rev on ".count($lines)." commits: ");
        foreach($lines as $i)
        {
            if($i['name'] == "undefined")
            {
                $this->log(print_r($i, true));
                $this->log("could not find branch for: ".$i['sha']);
            }
            else
            {
                $this->commits[$i['sha']]->nameRev = $i['name'];
                $this->log(".");
            }
        }
        $this->log("Done\n");

        // Link Children/Parent/Branches
        $this->log("Linking ".count($lines)." commits: ");
        foreach($this->commits as $commit)
        {
            $commit->initLinks();
            $this->log(".");
        }
        $this->log("Done\n");

        // Prune empty branches
        $this->branches = array_filter($this->branches, function($b){return count($b->history) > 0;});

        /*
        print("<pre>");
        foreach($this->branches as $b) {
            print($b->refname." (".$b->getType().")\n");
        }
        die();
        */

        // Tags
        $tagList = $this->git("tag --list", array("tag"));
        foreach($tagList as $t)
        {
            $c = $this->gitRaw("rev-parse \"".$t."\"");
            if(isset($this->commits[$c])) {
                $this->tags[$t] = $this->commits[$c];
            } else {
                file_put_contents('php://stderr', "ERROR: Could not find commit for tag: \"".$t."\"\n");
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
        if(!isset($this->commits[$sha]))
        {
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
        if(substr_compare($name, "/HEAD", -5) === 0)
        {
            $name = $this->defaultBranch;
        }
        $branchNames = array();
        foreach ($this->branches as $branch)
        {
            if($branch->matchName($name))
            {
                return $branch;
            }
            $branchNames[] = $branch->refname;
        }
        print_r($branchNames);
        throw new \Exception("No such branch: ".$name." we have (".count($branchNames).")");
    }
}