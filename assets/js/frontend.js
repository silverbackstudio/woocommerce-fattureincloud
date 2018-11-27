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

     $('#fiscal-code-calculator-open').one( 'click', function(){
        var form = $('#fiscal-code-calculator');
        var cod_cat = CodiceFiscale.CODICI_CATASTALI;
        
        var cities = [{
            id : '',
            text: '',
            provincia: ''
        }];
        
        var estero = cod_cat.EE;
        delete cod_cat.EE;
        cod_cat.EE = estero;
        
        for (var provincia in cod_cat) {
           if ( cod_cat.hasOwnProperty( provincia ) ) {
                for (var i = 0; i < cod_cat[provincia].length; i++) {
                    cities.push( {
                      id: cod_cat[provincia][i][0],
                      text: cod_cat[provincia][i][0] + ' (' + provincia + ')',
                      province: provincia
                    } );
                }
           }
        }
        
        $('#billing_birth_city').select2( { 
            placeholder: fiscalCodeCalculator.birthCityPlaceholder,
            allowClear: true,
            data: cities,
            templateSelection: function( state ) {
                $('#billing_birth_province').val( state.province );
                return state.text;
            }
        } );
        
    } );    
    
    $('#fiscal-code-calculate').on('click', function(e){
        e.preventDefault();

        var fieldsMap = {
            name: 'billing_first_name',
            surname: 'billing_last_name',
            gender: 'fiscal_code_gender',
            day: 'fiscal_code_birth_day',
            month: 'fiscal_code_birth_month',
            year: 'fiscal_code_birth_year',
            birthplace: 'fiscal_code_birth_city', 
            birthplaceProvincia: 'fiscal_code_birth_province'
        };
        
        var data = {};
        var field, label, validate = true;
        
        $('#fiscal-code-calculator ul.errors').empty();
        
        for (var cfProperty in fieldsMap) {
            field = $( ':input[name="' + fieldsMap[cfProperty] + '"]' );
            label =  $('label[for="' + field.attr('id') +'"]').text();
            
            if( 'radio' === field.attr('type') ) {
                data[cfProperty] = $( ':input[name=' + fieldsMap[cfProperty] + ']:checked' ).val();
                label = $('label[for="' + fieldsMap[cfProperty] +'"]').text();
            } else {
                data[cfProperty] = field.val();
            }
            
            if( !data[cfProperty] ) {
                if( label ) {
                    $('#fiscal-code-calculator ul.errors').append('<li>' + fiscalCodeCalculator.errorPrefix + ' <em>' + label + '</em></li>');
                }
                validate = false;
            }           
            
        }
        
        if ( validate ) {
            var cf = CodiceFiscale.compute( data );
            $('#billing_fiscal_code').val(cf);    
            $('#fiscal-code-calculator').trigger('fiscal-code-calculated', [ cf, data ] );
        } else {
            $('#fiscal-code-calculator').trigger('fiscal-code-calculate-error', [ cf, data ] );
        }
        
        
    });
    
})(jQuery);
