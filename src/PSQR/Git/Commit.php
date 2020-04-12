<?php

namespace PSQR\Git;


class Commit
{
    private $repository;
    public $sha;
    public $short;
    public $subject;
    public $nameRev;
    public $data;
    /**
     * @var Branch
     */
    public $branch;
    /**
     * @var Commit[]
     */
    private $parents = array();
    /**
     * @var Commit[]
     */
    private $children = array();

    public function __construct(Repository $repository, $logData)
    {
        $this->repository = $repository;
        $this->sha = $logData['sha'];
        $this->short = $logData['short'];
        $this->subject = $logData['subject'];
        $this->data = $logData;
        foreach(array('files_changed', 'lines_added', 'lines_deleted') as $f) {
            $this->data[$f] = 0;
        }
    }

    public function isDefaultBranch()
    {
        return $this->branch->isDefaultBranch();
    }

    public function initLinks()
    {
        if(is_null($this->branch))
        {
            if(is_null($this->nameRev))
            {
                $branch = $this->repository->git('name-rev '.$this->sha, array('sha','name-rev'), ' ');
                $this->nameRev = $branch[0]['name-rev'];
            }
            $this->setBranch($this->repository->findBranch(preg_replace("/[\\~\\^][0-9\\~\\^]+\$/", "", $this->nameRev)));
        }

        if($this->data['parents'] != "")
        {
            foreach (explode(" ", $this->data['parents']) as $p)
            {
                $parent = $this->repository->getCommit($p);
                $this->parents[] = $parent;
                $parent->children[] = $this;
            }
        }
    }

    public function setBranch(Branch $branch)
    {
        if(is_null($this->branch))
        {
            $this->branch = $branch;
            $branch->addCommit($this);
        }
    }


    public function getParents()
    {
        return $this->parents;
    }

    public function getAuthorDate($format=null)
    {
        if(is_null($format)) {
            return $this->data['author_date'];
        }
        return date($format, strtotime($this->data['author_date']));
    }

    public function getCommitDate($format=null)
    {
        if(is_null($format)) {
            return $this->data['commit_date'];
        }
        return date($format, strtotime($this->data['commit_date']));
    }

    /**
     * @return int
     */
    public function getFirstTime()
    {
        return min(strtotime($this->getCommitDate()), strtotime($this->getAuthorDate()));
    }

    /**
     * @return int
     */
    public function getLastTime()
    {
        return max(strtotime($this->getCommitDate()), strtotime($this->getAuthorDate()));
    }

    public function isEndCommit()
    {
        return count($this->children) == 0;
    }

    public function getAuthor()
    {
        return $this->data['author'];
    }

    public function getAuthorEmail()
    {
        return $this->data['author_email'];
    }

    public function getSubject()
    {
        return $this->data['subject'];
    }

    public function getFakeCommitCmd($msg)
    {
        $cmd =  'GIT_AUTHOR_NAME="'.$this->getAuthor().'" '; // is the human-readable name in the “author” field.
        $cmd .= 'GIT_AUTHOR_EMAIL="'.$this->getAuthorEmail().'" '; // is the email for the “author” field.
        $cmd .= 'GIT_AUTHOR_DATE="'.$this->getAuthorDate().'" '; // is the timestamp used for the “author” field.
        $cmd .= 'GIT_COMMITTER_NAME="PSQR" '; // sets the human name for the “committer” field.
        $cmd .= 'GIT_COMMITTER_EMAIL="devteam@psqr.dev" '; // is the email address for the “committer” field.
        $cmd .= 'GIT_COMMITTER_DATE="'.$this->getCommitDate().'" '; // is used for the timestamp in the “committer” field.
        return $cmd." git commit -m \"".$msg."\"";
    }
}