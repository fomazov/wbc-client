
define( [
    'assets/js/plugins/jquery-isotope/isotope.pkgd.min'
], function( Isotope ) {

        var iso = {
            render: function(){
                //console.log('isotope');

                var iso = new Isotope( '.terminal-list', {
                    itemSelector: 'figure',
                    percentPosition: true
                });

                return iso;
            }
        };

        return iso;

});