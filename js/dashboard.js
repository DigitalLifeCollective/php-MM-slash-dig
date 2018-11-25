// external js: isotope.pkgd.js




// external js: isotope.pkgd.js

// init Isotope
var $grid = $('.grid').isotope({
  itemSelector: '.color-shape'
});

// store filter for each group
var filters = [];

// change is-checked class on buttons
$('.filters').on( 'click', 'button', function( event ) {
  var $target = $( event.currentTarget );
  $target.toggleClass('is-checked');
  var isChecked = $target.hasClass('is-checked');
  var filter = $target.attr('data-filter');
  if ( isChecked ) {
    addFilter( filter );
  } else {
    removeFilter( filter );
  }
  // filter isotope
  // group filters together, inclusive
  $grid.isotope({ filter: filters.join(',') });
});
  
function addFilter( filter ) {
  if ( filters.indexOf( filter ) == -1 ) {
    filters.push( filter );
  }
}

function removeFilter( filter ) {
  var index = filters.indexOf( filter);
  if ( index != -1 ) {
    filters.splice( index, 1 );
  }
}
