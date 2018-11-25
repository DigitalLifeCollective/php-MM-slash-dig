// https://stackoverflow.com/questions/40874162/d3-js-force-directed-graph-using-images-instead-of-circles-for-the-nodes
// https://codepen.io/smlo/pen/JdMOej (for the tooltip)
// http://www.coppelia.io/2014/07/an-a-to-z-of-extra-features-for-the-d3-force-layout/ (with names)
// https://bl.ocks.org/mindrones/5a20e38c9654f540497754566d089c4d (pulsation)


var svg = d3.select("svg"),
    width = +svg.attr("width"),
    height = +svg.attr("height");

var graph = $("#graph"),
    aspect = graph.width() / graph.height(),
    container = graph.parent();

$(window).on("resize", function() {
    var targetWidth = container.width();
    graph.attr("width", targetWidth);
    graph.attr("height", Math.round(targetWidth / aspect));
}).trigger("resize");

var color = d3.scaleOrdinal(d3.schemeCategory10);

var simulation = d3.forceSimulation()
    .force("link", d3.forceLink().id(function(d) {
        return d.id;
    }).distance(30))
    .force("collide", d3.forceCollide(20))
    .force("charge", d3.forceManyBody())
    .force("center", d3.forceCenter(width / 2, height / 2));

d3.json("/webhooks/data/teams.json", function(error, graph) {
  if (error) throw error;

var nodes = graph.nodes;
var links = graph.links;


var defs = svg.append('svg:defs');

for(var i=0;i<graph.nodes.length;i++){

            defs.append('svg:pattern')
                .data(graph.nodes)
                .attr('id', function(d) { return("avatar_"+graph.nodes[i].id); })
                .attr('width', '1')
                .attr('height', '1')
                .append('svg:image')
                .attr('xlink:href', function(d) { return("https://www.diglife.com/images/avatar_"+graph.nodes[i].id+".png"); })
                .attr('x', 0)
                .attr('y', 0)
                .attr('width', 30)
                .attr('height', 30);
}


defs.append("svg:pattern")
    .attr("id", "myPattern")
    .attr("width", 1)
    .attr("height", 1)
    .append("svg:image")
    .attr("xlink:href", function(d) { return "https://www.diglife.com/brand/logo_secondary_background.png"; })
    .attr("width", 10)
    .attr("height", 10)
    .attr("x", 5)
    .attr("y", 5);



link = svg.selectAll(".link")
    .data(links, function(d) { return d.target.id; })

link = link.enter()
    .append("line")
    //.attr("stroke-width", function(d) { return Math.sqrt(d.value); })
    .attr("class", "link");

node = svg.selectAll(".node")
    .data(nodes, function(d) { return d.id; })

node = node.enter()
    .append("g")
    .attr("class", "node")
    .call(d3.drag()
        .on("start", dragstarted)
        .on("drag", dragged)
        .on("end", dragended));

node.append("circle")
    .attr("r", 15)
    .attr("stroke", function(d) { return color(d.group); } )
    //.style("fill", "url(#myPattern)")
    .style("fill", function(d) { return "url(#avatar_"+d.id+")"; } )

node.append("title")
      .text(function(d) { return d.id; });


simulation
    .nodes(nodes)
    .on("tick", ticked);

simulation.force("link")
    .links(links);

});

function ticked() {
    link
        .attr("x1", function(d) {
            return d.source.x;
        })
        .attr("y1", function(d) {
            return d.source.y;
        })
        .attr("x2", function(d) {
            return d.target.x;
        })
        .attr("y2", function(d) {
            return d.target.y;
        });

    node
        .attr("transform", function(d) {
            return "translate(" + d.x + ", " + d.y + ")";
        });
}

function dragstarted(d) {
    if (!d3.event.active) simulation.alphaTarget(0.3).restart()
}

function dragged(d) {
    d.fx = d3.event.x;
    d.fy = d3.event.y;
}

function dragended(d) {
    if (!d3.event.active) simulation.alphaTarget(0);
    d.fx = undefined;
    d.fy = undefined;
}
