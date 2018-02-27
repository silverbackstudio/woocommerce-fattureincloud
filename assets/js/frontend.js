(function($){
    
    $('#fiscal-code-calculator-open').on( 'click', function( e ){
        e.preventDefault();
        $('#fiscal-code-calculator-container').slideDown();
    } );

    $('#fiscal-code-calculator-close').on( 'click', function( e ){
        e.preventDefault();
        $('#fiscal-code-calculator-container').slideUp();
    } );
    
    $('#fiscal-code-calculator').on( 'fiscal-code-calculated', function(){
        $('#fiscal-code-calculator-container').slideUp();
    } );
    
})(jQuery);