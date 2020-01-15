<?php
require_once (__DIR__.'/../vendor/autoload.php');
require_once (__DIR__.'/../config.php');

use PSQR\Git;


// Initialize the repository
$repo = new Git\Repository($repoGitPath);
$repo->init(false);

$json = new \PSQR\HTML\Json($repo);
$json->generate();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>D3JS</title>

    <!-- Load d3.js -->
    <script src="https://d3js.org/d3.v4.js"></script>
</head>
<body>
<!-- Create a div where the circle will take place -->
<div id="dataviz_brushZoom"></div>

<script>

    var repo = <?php $json->output(); ?>;

    var commitData = Array();

    function getBranchIndx(branch) {
        let i = 0;
        for (let [key, value] of Object.entries(repo.branches)) {
            if(key === branch) {
                return i;
            }
            i++;
        }
        throw Error("Could not find branch: "+branch);
    }

    for (let [sha, commit] of Object.entries(repo.commits)) {
        commitData.push({
            'sha':sha,
            'x': new Date(commit.firstTime*1000),
            'y':getBranchIndx(commit.branch),
            'type':commit.type
        });
    }

    console.log(commitData);


    // set the dimensions and margins of the graph
    var margin = {top: 10, right: 30, bottom: 30, left: 60},
        width = 1800 - margin.left - margin.right,
        height = 1000 - margin.top - margin.bottom;

    // append the svg object to the body of the page
    var Svg = d3.select("#dataviz_brushZoom")
        .append("svg")
        .attr("width", width + margin.left + margin.right)
        .attr("height", height + margin.top + margin.bottom)
        .append("g")
        .attr("transform",
            "translate(" + margin.left + "," + margin.top + ")");

    //Read the data
    {
        // Add X axis
        var x_extend = d3.extent(commitData, d => d.x);
        var x = d3.scaleTime()
            .domain(x_extend)
            .range([ 0, width ]);
        var xAxis = Svg.append("g")
            .attr("transform", "translate(0," + height + ")")
            .call(d3.axisBottom(x).tickFormat(d3.timeFormat("%Y-%m-%d")));

        // Add Y axis
        var y = d3.scaleLinear()
            .domain(d3.extent(commitData, d => d.y))
            .range([ height, 0]);
        //Svg.append("g")
        //    .call(d3.axisLeft(y));

        // Add a clipPath: everything out of this area won't be drawn.
        var clip = Svg.append("defs").append("svg:clipPath")
            .attr("id", "clip")
            .append("svg:rect")
            .attr("width", width )
            .attr("height", height )
            .attr("x", 0)
            .attr("y", 0);

        // Color scale: give me a specie name, I return a color
        var color = d3.scaleOrdinal()
            .domain(["root", "merge", "end", "normal" ])
            .range([ "#ffffff88", "#0000ff88", "#ff000088", "#00ff0088"]);

        // Add brushing
        var brush = d3.brushX()                 // Add the brush feature using the d3.brush function
            .extent( [ [0,0], [width,height] ] ) // initialise the brush area: start at 0,0 and finishes at width,height: it means I select the whole graph area
            .on("end", updateChart); // Each time the brush selection changes, trigger the 'updateChart' function

        // Create the scatter variable: where both the circles and the brush take place
        var scatter = Svg.append('g')
            .attr("clip-path", "url(#clip)");

        // Add circles
        scatter
            .selectAll("circle")
            .data(commitData)
            .enter()
            .append("circle")
            .attr("cx", function (d) { return x(d.x); } )
            .attr("cy", function (d) { return y(d.y); } )
            .attr("r", 4)
            .style("fill", function (d) { return color(d.type) } )
            .style("opacity", 0.5);

        // Add the brushing
        scatter
            .append("g")
            .attr("class", "brush")
            .call(brush);

        // A function that set idleTimeOut to null
        var idleTimeout;
        function idled() { idleTimeout = null; }

        // A function that update the chart for given boundaries
        function updateChart() {

            extent = d3.event.selection;

            // If no selection, back to initial coordinate. Otherwise, update X axis domain
            if(!extent){
                if (!idleTimeout) return idleTimeout = setTimeout(idled, 350); // This allows to wait a little bit
                x.domain(x_extend)
            }else{
                x.domain([ x.invert(extent[0]), x.invert(extent[1]) ]);
                scatter.select(".brush").call(brush.move, null); // This remove the grey brush area as soon as the selection has been done
            }

            // Update axis and circle position
            xAxis.transition().duration(1000).call(d3.axisBottom(x));
            scatter
                .selectAll("circle")
                .transition().duration(1000)
                .attr("cx", function (d) { return x(d.x); } )
                .attr("cy", function (d) { return y(d.y); } )
            ;
        }
    }

</script>
</body>
</html>