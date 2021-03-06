<?php
namespace PSQR\HTML;


use PSQR\Git\Branch;
use PSQR\Git\Commit;

class YAxisBranch
{
    /**
     * @var Branch
     */
    public $branch;
    public $y;

    public function __construct(Branch $branch, $y)
    {
        $this->branch = $branch;
        $this->y = $y;
    }
}

class Timeline
{
    private $svg;

    /**
     * @var \PSQR\Git\Repository
     */
    private $repository;

    // Time
    private $firstTime, $lastTime;

    // Sizes
    private $xScale;
    private $width = 1580;
    private $xMargin = 50;
    private $yScale;
    private $height = 800;
    private $yMargin = 0;
    private $commitRadius = 6;
    /**
     * @var YAxisBranch[]
     */
    private $yAxis = array();

    public function __construct(\PSQR\Git\Repository $repository)
    {
        $this->repository = $repository;
    }

    public function generate()
    {
        $this->svg = "";


        // Calculate timeline params
        $this->firstTime = $this->repository->getFirstCommit()->getFirstTime();
        $this->lastTime = $this->repository->getLastCommit()->getLastTime();
        $this->firstTime = strtotime("2019-11-01");
        //$this->lastTime = strtotime("2020-01-04");
        $this->xScale = $this->width / ($this->lastTime - $this->firstTime);
        $branches = array_filter($this->repository->getBranches(), function($b) { return !$b->isPull() && $b->overlapDateRange($this->firstTime, $this->lastTime);});
        $this->yScale = $this->height / count($branches);

        // Y-axis
        usort($branches, function($b1,$b2){return $b1->getFirstCommit()->getFirstTime()-$b2->getFirstCommit()->getFirstTime();});
        foreach($branches as $branch)
        {
            $this->yAxis[$branch->refname] = new YAxisBranch($branch, count($this->yAxis) * $this->yScale);
        }
        //print_r($this->yAxis); die();

        // Draw weekends
        $date = new \DateTime();
        $date->setTimestamp($this->firstTime);
        $date->modify('next saturday');
        for($s = $date->getTimestamp(); $s < $this->lastTime; $s += 3600*24*7)
        {
            $x = $this->getXFromTime($s);
            $w = $this->getXFromTime($s + 3600*24*2) - $x;
            $this->svg .= "<rect x=\"$x\" y=\"0\" width=\"$w\" height=\"".($this->height+$this->yMargin*2)."\" style=\"fill:rgb(200,220,240); stroke:none\" />\n";
        }


        // Print the swim-lanes
        $odd = true;
        foreach($this->yAxis as $yAxisBranch)
        {
            $y = $yAxisBranch->y;
            if(!$odd)
            {
                $this->svg .= "<rect x=\"0\" y=\"$y\" width=\"".($this->width+$this->xMargin*2)."\" height=\"$this->yScale\" style=\"fill:rgb(230,230,230); stroke:none; fill-opacity:0.6;\" />\n";
            }

            // Period of time
            $x = $this->getXFromTime($yAxisBranch->branch->getFirstCommit()->getFirstTime()) - $this->commitRadius*2;
            $x2 = $this->getXFromTime($yAxisBranch->branch->getLastCommit()->getLastTime()) + $this->commitRadius*2;
            $this->svg .= "<rect x=\"$x\" y=\"".($y+$this->commitRadius*0.25)."\" width=\"".($x2 - $x)."\" rx=\"".($this->commitRadius/2)."\" ry=\"".($this->commitRadius/2)."\" height=\"".($this->yScale-$this->commitRadius*0.5)."\" ".
                "style=\"fill:".$this->getBranchColor($yAxisBranch->branch)."; fill-opacity:0.4; stroke-width: 1; stroke: rgb(100,100,100); stroke-dasharray: 5 2; \" />\n";


            // Label
            $fontSize = 8;
            $this->svg .= "<text x=\"0\" y=\"".($y + ($this->yScale+$fontSize)/2.0)."\" fill=\"black\" font-size=\"$fontSize\">".$yAxisBranch->branch->getVeryShortName()."</text>\n";
            $odd = !$odd;
        }

        // Print all commits
        foreach($this->repository->getCommits() as $c)
        {
            list($x,$y) = $this->getXY($c);
            if($c->isEndCommit())
            {
                $this->svg .= "<circle cx=\"".$x."\" cy=\"".$y."\" r=\"".($this->commitRadius)."\" stroke=\"black\" stroke-width=\"0.5\" fill=\"red\" filter=\"url(#commit_shadow)\" />\n";
            }
            else
            {
                $this->svg .= "<circle cx=\"".$x."\" cy=\"".$y."\" r=\"$this->commitRadius\" stroke=\"yellow\" stroke-width=\"0.5\" fill=\"green\" filter=\"url(#commit_shadow)\" />\n";
            }

            foreach($c->getParents() as $p)
            {
                $this->svg .= $this->makeLink($c, $p);
            }
        }

        // Print tags
        foreach($this->repository->tags as $tag => $commit)
        {
            list($x,$y) = $this->getXY($commit);
            $y -= 1;
            $x -= 4;
            $this->svg .= "<text x=\"$x\" y=\"$y\" style=\"stroke:yellow; stroke-width:0.4em\" font-size='10' transform=\"rotate(-45 $x,$y)\">".$tag."</text>\n";
            $this->svg .= "<text x=\"$x\" y=\"$y\" style=\"stroke:black;\" font-size='10' transform=\"rotate(-45 $x,$y)\">".$tag."</text>\n";
        }
    }

    public function output($file=null)
    {
        $html = "<!DOCTYPE html>\n".
            "<html>\n".
            "<body>\n".
            "<svg width=\"".($this->width+$this->xMargin*2)."\" height=\"".($this->height+$this->yMargin*2)."\">
              <defs>
                <filter id=\"commit_shadow\" x=\"-25%\" y=\"-25%\" width=\"200%\" height=\"200%\">
                  <feOffset result=\"offOut\" in=\"SourceAlpha\" dx=\"2\" dy=\"2\" />
                  <feGaussianBlur result=\"blurOut\" in=\"offOut\" stdDeviation=\"2\" />
                  <feBlend in=\"SourceGraphic\" in2=\"blurOut\" mode=\"normal\" />
                </filter>
              </defs>\n".
            $this->svg."\n".
            "</svg>\n".
            "</body>\n".
            "</html>\n";
        if(is_null($file)) {
            print($html);
        } else {
            file_put_contents($file, $html);
        }
    }

    private function getXFromTime($time)
    {
        return ($time - $this->firstTime) * $this->xScale + $this->xMargin;
    }

    private function getXY(Commit $c)
    {
        $x = $this->getXFromTime($c->getFirstTime());
        if(isset($this->yAxis[$c->branch->refname]))
        {
            $y = $this->yAxis[$c->branch->refname]->y + $this->yScale * 0.5 + $this->yMargin;
        }
        else
        {
            $y = $this->yScale * 0.5 + $this->yMargin;
        }
        //srand(hexdec($c->short));
        //$y += ($this->yScale*0.5)*(rand(0,1000000)/1000000) - $this->yScale * 0.25;

        return array($x,$y);
    }

    private function makeLink(Commit $c, Commit $p)
    {
        list($x1, $y1) = $this->getXY($p);
        list($x2, $y2) = $this->getXY($c);
        $x1 = ($x1+$this->commitRadius);
        $x2 = ($x2-$this->commitRadius);

        $xd = abs($x2 - $x1);
        $yd = $y2 - $y1;

        //if($y1 < $y2)
        {
            $x1c = $x1 + $xd * 0.9;
            $y1c = $y1 + $yd * 0.25;
            $x2c = $x2 - $xd * 0.9;
            $y2c = $y2 - $yd * 0.25;
        }
        if($x2 < $x1 && $yd == 0)
        {
            $y1c = $y1 + $this->commitRadius*2;
            $y2c = $y2 + $this->commitRadius*2;
        }
        $stroke = $p->getLastTime() <= $c->getFirstTime()  ? "stroke:rgb(100,100,100);stroke-width:1":"stroke:rgb(200,0,0);stroke-width:2";
        //return "<line x1=\"$x\" y1=\"$y\" x2=\"$x2\" y2=\"$y2\" style=\"stroke:rgb(100,100,100);stroke-width:1\" />";
        return "<path d=\"M $x1 $y1 C $x1c $y1c, $x2c $y2c, $x2 $y2\"  style=\"$stroke;fill:none;\" />".
            "<circle r='1.5' cx='$x2'  cy='$y2' style='fill:rgb(0,0,0);stoke:none;' />";
    }

    private function getBranchColor(Branch $branch)
    {
        if($branch->isDefaultBranch())
        {
            return "rgb(0,200,0)";
        }
        if($branch->isRelease())
        {
            return "rgb(200,250,220)";
        }
        if($branch->isTag())
        {
            return "rgb(250,250,0)";
        }
        if($branch->isUnmerged())
        {
            return "rgb(240,150,120)";
        }

        return "rgb(50,100,250)";
    }
}